<?php

namespace App\Services\Content;

use App\Models\AuditLog;
use App\Models\ContentSource;
use App\Models\ContentVersion;
use App\Models\Exam;
use App\Models\ExamBooklet;
use App\Models\OfficialAnswer;
use App\Models\OfficialAnswerSet;
use App\Models\Question;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * ENEM OFFICIAL CONTENT GUARDIAN — único caminho autorizado para alterar conteúdo
 * oficial (versão + motivo + autor), avançar o fluxo de auditoria e verificar
 * integridade contra o documento oficial. Nenhum outro serviço (muito menos a IA)
 * escreve em Question / OfficialAnswer / Exam.
 */
class GuardianService
{
    public function __construct(private readonly AuditService $audit) {}

    // ------------------------------------------------------------------
    //  Alteração versionada
    // ------------------------------------------------------------------

    public function changeOfficialAnswer(Question $question, ?string $correct, bool $annulled, string $reason, int $actorId): OfficialAnswer
    {
        $this->requireReason($reason);
        $current = $question->officialAnswer()->firstOrFail();

        return DB::transaction(function () use ($question, $current, $correct, $annulled, $reason, $actorId) {
            $question->increment('version');
            $question->update(['review_status' => 'PENDING', 'pipeline_stage' => 'IMPORTED']);
            $current->update(['correct' => $annulled ? null : $correct, 'annulled' => $annulled, 'review_status' => 'PENDING']);
            ContentVersion::create([
                'entity_type' => 'OfficialAnswer',
                'entity_id' => $current->id,
                'version' => $question->fresh()->version,
                'previous_data' => ['correct' => $current->getOriginal('correct'), 'annulled' => $current->getOriginal('annulled')],
                'new_data' => ['correct' => $correct, 'annulled' => $annulled],
                'reason' => $reason,
                'author_id' => $actorId,
            ]);
            // A prova volta a PENDING — some do catálogo até nova auditoria.
            $this->invalidateExam($question->booklet->exam);
            $this->audit->log('content.official_answer.changed', $actorId, 'OfficialAnswer', $current->id, ['reason' => $reason]);

            return $current->fresh();
        });
    }

    public function changeExamMetadata(Exam $exam, array $data, string $reason, int $actorId): Exam
    {
        $this->requireReason($reason);
        $data = array_intersect_key($data, array_flip(['duration_minutes', 'title', 'structure_note', 'is_free_sample']));

        return DB::transaction(function () use ($exam, $data, $reason, $actorId) {
            $previous = array_intersect_key($exam->getAttributes(), $data);
            $exam->update($data + ['review_status' => 'PENDING', 'pipeline_stage' => 'IMPORTED', 'version' => $exam->version + 1]);
            ContentVersion::create([
                'entity_type' => 'Exam',
                'entity_id' => $exam->id,
                'version' => $exam->version,
                'previous_data' => $previous,
                'new_data' => $data,
                'reason' => $reason,
                'author_id' => $actorId,
            ]);
            $this->audit->log('content.exam.changed', $actorId, 'Exam', $exam->id, ['reason' => $reason, 'data' => $data]);

            return $exam;
        });
    }

    // ------------------------------------------------------------------
    //  Fluxo de auditoria
    // ------------------------------------------------------------------

    public function advanceExam(Exam $exam, string $target, int $actorId): Exam
    {
        $review1By = AuditLog::where('entity_type', 'Exam')->where('entity_id', $exam->id)
            ->where('action', 'content.exam.stage.HUMAN_REVIEW_1')->latest('id')->value('actor_id');

        if ($violation = GuardianRules::canAdvance($exam->pipeline_stage, $target, $review1By, $actorId)) {
            throw ValidationException::withMessages(['stage' => $violation['message']]);
        }

        if ($target === 'AUTO_VALIDATED' || $target === 'PUBLISHED') {
            $problems = $this->autoValidate($exam);
            if ($target === 'PUBLISHED') {
                $problems = [...$problems, ...$this->verifyIntegrity($exam)];
            }
            if ($problems) {
                $this->audit->alert('WARN', 'guardian', "Validação bloqueou a prova {$exam->id} ({$target})", ['problems' => $problems]);
                throw ValidationException::withMessages(['stage' => array_column($problems, 'message')]);
            }
        }

        $reviewStatus = $target === 'PUBLISHED' ? 'VERIFIED' : 'VALIDATING';

        DB::transaction(function () use ($exam, $target, $reviewStatus, $actorId) {
            $exam->update(['pipeline_stage' => $target, 'review_status' => $reviewStatus]);
            if ($target === 'PUBLISHED') {
                $bookletIds = $exam->booklets()->pluck('id');
                ExamBooklet::whereIn('id', $bookletIds)->update(['review_status' => 'VERIFIED']);
                Question::whereIn('exam_booklet_id', $bookletIds)->update(['review_status' => 'VERIFIED', 'pipeline_stage' => 'PUBLISHED']);
                OfficialAnswerSet::whereIn('exam_booklet_id', $bookletIds)->update(['review_status' => 'VERIFIED']);
                OfficialAnswer::whereIn('question_id', Question::whereIn('exam_booklet_id', $bookletIds)->select('id'))->update(['review_status' => 'VERIFIED']);
                $exam->essayPrompt()->update(['review_status' => 'VERIFIED']);
                $sourceIds = [$exam->content_source_id, ...$exam->booklets()->pluck('content_source_id')->all()];
                ContentSource::whereIn('id', $sourceIds)->update(['review_status' => 'VERIFIED', 'last_validation' => now()]);
            }
            $this->audit->log("content.exam.stage.{$target}", $actorId, 'Exam', $exam->id);
        });

        return $exam->fresh();
    }

    public function rejectExam(Exam $exam, string $reason, int $actorId): void
    {
        $this->requireReason($reason);
        $exam->update(['review_status' => 'REJECTED']);
        $this->audit->log('content.exam.rejected', $actorId, 'Exam', $exam->id, ['reason' => $reason]);
    }

    // ------------------------------------------------------------------
    //  Validações
    // ------------------------------------------------------------------

    /** Validação automática estrutural (não depende de arquivos). */
    public function autoValidate(Exam $exam): array
    {
        $exam->load(['source', 'booklets.questions.officialAnswer', 'booklets.answerSets']);
        $snapshot = [
            'duration_minutes' => (int) $exam->duration_minutes,
            'source' => $exam->source->only(['source_type', 'source_url', 'checksum']),
            'booklets' => $exam->booklets->map(fn (ExamBooklet $b) => [
                'id' => $b->id,
                'label' => $b->label,
                'pdf_checksum' => $b->pdf_checksum,
                'page_count' => $b->page_count,
                'answer_sets' => $b->answerSets->map(fn ($s) => ['checksum' => $s->checksum])->all(),
                'questions' => $b->questions->map(fn (Question $q) => [
                    'original_number' => $q->original_number,
                    'source_type' => $q->source_type,
                    'official_answer' => $q->officialAnswer ? ['correct' => $q->officialAnswer->correct, 'annulled' => $q->officialAnswer->annulled] : null,
                ])->all(),
            ])->all(),
        ];

        return GuardianRules::validateExamForPublication($snapshot);
    }

    /**
     * REGRA DE OURO DE QA: compara o conteúdo cadastrado com o documento oficial
     * armazenado. Qualquer divergência bloqueia a publicação.
     */
    public function verifyIntegrity(Exam $exam): array
    {
        $problems = [];
        $disk = Storage::disk('official');
        foreach ($exam->booklets()->with(['questions.officialAnswer', 'answerSets'])->get() as $booklet) {
            if (! $disk->exists($booklet->pdf_path)) {
                $problems[] = ['code' => 'PDF_MISSING', 'message' => "PDF do caderno {$booklet->label} não encontrado no storage."];
            } elseif (hash('sha256', $disk->get($booklet->pdf_path)) !== $booklet->pdf_checksum) {
                $problems[] = ['code' => 'PDF_CHECKSUM_MISMATCH', 'message' => "PDF do caderno {$booklet->label} foi alterado após a importação."];
            }
            $computed = GuardianRules::answerKeyChecksum($booklet->questions->map(fn (Question $q) => [
                'number' => $q->original_number,
                'correct' => $q->officialAnswer?->correct,
                'annulled' => (bool) $q->officialAnswer?->annulled,
            ])->all());
            if (! $booklet->answerSets->contains('checksum', $computed)) {
                $problems[] = ['code' => 'ANSWER_KEY_MISMATCH', 'message' => "Gabarito do caderno {$booklet->label} diverge do gabarito oficial importado."];
            }
        }
        if ($problems) {
            $this->audit->alert('ERROR', 'guardian.integrity', "Divergência de integridade na prova {$exam->id}", ['problems' => $problems]);
        }

        return $problems;
    }

    private function invalidateExam(Exam $exam): void
    {
        $exam->update(['review_status' => 'PENDING', 'pipeline_stage' => 'IMPORTED']);
    }

    private function requireReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Motivo obrigatório para alterar conteúdo oficial.']);
        }
    }
}
