<?php

namespace App\Services\Content;

use App\Models\ContentSource;
use App\Models\EssayPrompt;
use App\Models\EssayZeroRule;
use App\Models\Exam;
use App\Models\ExamBooklet;
use App\Models\ExamEdition;
use App\Models\OfficialAnswer;
use App\Models\OfficialAnswerSet;
use App\Models\Question;
use App\Services\AuditService;
use App\Support\Enem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Painel de importação. Tudo entra como PENDING / IMPORTED.
 * Nada aqui publica conteúdo — publicação é exclusiva do GuardianService.
 */
class ContentImportService
{
    public function __construct(private readonly AuditService $audit) {}

    public function createExam(array $data, ?UploadedFile $sourcePdf, int $actorId): Exam
    {
        $edition = ExamEdition::firstOrCreate(['year' => $data['year']], ['name' => "ENEM {$data['year']}"]);
        if (Exam::where('exam_edition_id', $edition->id)->where('application', $data['application'])->where('day', $data['day'])->exists()) {
            throw ValidationException::withMessages(['day' => 'Prova já cadastrada para esta edição/aplicação/dia.']);
        }
        $source = ContentSource::create([
            'source_type' => 'OFFICIAL_INEP',
            'source_url' => $data['source_url'],
            'source_year' => $data['year'],
            'document_version' => $data['document_version'],
            'checksum' => $sourcePdf ? hash_file('sha256', $sourcePdf->getRealPath()) : hash('sha256', $data['source_url']),
            'description' => $data['title'],
        ]);
        $areas = array_values($data['areas']);
        $exam = Exam::create([
            'exam_edition_id' => $edition->id,
            'application' => $data['application'],
            'day' => $data['day'],
            'title' => $data['title'],
            'duration_minutes' => $data['duration_minutes'],
            'areas' => $areas,
            'has_essay' => in_array('REDACAO', $areas, true),
            'has_foreign_language' => in_array('LINGUAGENS', $areas, true),
            'structure_note' => $data['structure_note'] ?? null,
            'content_source_id' => $source->id,
            'is_free_sample' => (bool) ($data['is_free_sample'] ?? false),
        ]);
        $this->audit->log('content.exam.imported', $actorId, 'Exam', $exam->id, ['source_url' => $data['source_url']]);

        return $exam;
    }

    public function addBooklet(Exam $exam, array $data, UploadedFile $pdf, int $actorId): ExamBooklet
    {
        $raw = file_get_contents($pdf->getRealPath());
        if (! str_starts_with($raw, '%PDF-')) {
            throw ValidationException::withMessages(['pdf' => 'O arquivo não é um PDF válido.']);
        }
        $checksum = hash('sha256', $raw);
        $path = sprintf('%d/%d/%s-%s.pdf', $exam->exam_edition_id, $exam->id, strtolower($data['color']), substr($checksum, 0, 12));
        Storage::disk('official')->put($path, $raw);

        $source = ContentSource::create([
            'source_type' => 'OFFICIAL_INEP',
            'source_url' => $data['source_url'],
            'source_year' => $exam->edition->year,
            'document_version' => $data['document_version'],
            'checksum' => $checksum,
            'description' => "{$exam->title} — {$data['label']}",
        ]);
        $booklet = ExamBooklet::create([
            'exam_id' => $exam->id,
            'color' => $data['color'],
            'label' => $data['label'],
            'pdf_path' => $path,
            'pdf_checksum' => $checksum,
            'page_count' => $data['page_count'],
            'content_source_id' => $source->id,
        ]);
        $booklet->pages()->createMany(array_map(fn ($n) => ['page_number' => $n], range(1, (int) $data['page_count'])));
        $exam->update(['review_status' => 'PENDING', 'pipeline_stage' => 'IMPORTED']);
        $this->audit->log('content.booklet.imported', $actorId, 'ExamBooklet', $booklet->id, ['checksum' => $checksum]);

        return $booklet;
    }

    /**
     * Registra o gabarito oficial: cria as questões e o OfficialAnswerSet com
     * checksum canônico (usado pelo Guardian para detectar alterações silenciosas).
     *
     * @param  array<int, array{number:int, area:string, correct?:?string, annulled?:bool, foreign_language?:?string, page?:?int}>  $answers
     */
    public function registerAnswerKey(ExamBooklet $booklet, array $meta, array $answers, ?UploadedFile $gabaritoPdf, int $actorId): OfficialAnswerSet
    {
        $numbers = [];
        foreach ($answers as $a) {
            $key = $a['number'].'/'.($a['foreign_language'] ?? '');
            if (isset($numbers[$key])) {
                throw ValidationException::withMessages(['answers' => "Questão {$a['number']} duplicada."]);
            }
            $numbers[$key] = true;
            $annulled = (bool) ($a['annulled'] ?? false);
            if (! $annulled && ! in_array($a['correct'] ?? null, Enem::OPTIONS, true)) {
                throw ValidationException::withMessages(['answers' => "Questão {$a['number']} sem alternativa correta."]);
            }
        }
        $checksum = GuardianRules::answerKeyChecksum(array_map(fn ($a) => [
            'number' => (int) $a['number'], 'correct' => $a['correct'] ?? null, 'annulled' => (bool) ($a['annulled'] ?? false), 'foreign_language' => $a['foreign_language'] ?? null,
        ], $answers));

        $pdfPath = null;
        if ($gabaritoPdf) {
            $pdfPath = sprintf('%d/%d/gabarito-%s-%s.pdf', $booklet->exam->exam_edition_id, $booklet->exam_id, strtolower($booklet->color), substr($checksum, 0, 12));
            Storage::disk('official')->put($pdfPath, file_get_contents($gabaritoPdf->getRealPath()));
        }

        return DB::transaction(function () use ($booklet, $meta, $answers, $gabaritoPdf, $pdfPath, $checksum, $actorId) {
            $source = ContentSource::create([
                'source_type' => 'OFFICIAL_INEP',
                'source_url' => $meta['source_url'],
                'source_year' => $booklet->exam->edition->year,
                'document_version' => $meta['document_version'],
                'checksum' => $gabaritoPdf ? hash_file('sha256', $gabaritoPdf->getRealPath()) : $checksum,
                'description' => "Gabarito — {$booklet->label}",
            ]);
            $set = OfficialAnswerSet::create([
                'exam_booklet_id' => $booklet->id, 'content_source_id' => $source->id, 'pdf_path' => $pdfPath, 'checksum' => $checksum,
            ]);
            foreach ($answers as $a) {
                $annulled = (bool) ($a['annulled'] ?? false);
                $question = Question::updateOrCreate(
                    ['exam_booklet_id' => $booklet->id, 'original_number' => (int) $a['number'], 'foreign_language' => $a['foreign_language'] ?? null],
                    ['area' => $a['area'], 'page_number' => $a['page'] ?? null],
                );
                if ($question->wasRecentlyCreated) {
                    $question->options()->createMany(array_map(fn ($l) => ['letter' => $l], Enem::OPTIONS));
                }
                OfficialAnswer::updateOrCreate(
                    ['question_id' => $question->id],
                    ['official_answer_set_id' => $set->id, 'correct' => $annulled ? null : $a['correct'], 'annulled' => $annulled, 'review_status' => 'PENDING'],
                );
            }
            $booklet->exam->update(['review_status' => 'PENDING', 'pipeline_stage' => 'IMPORTED']);
            $this->audit->log('content.answer_key.imported', $actorId, 'OfficialAnswerSet', $set->id, ['checksum' => $checksum, 'count' => count($answers)]);

            return $set;
        });
    }

    public function registerEssayPrompt(Exam $exam, array $data, int $actorId): EssayPrompt
    {
        $source = ContentSource::create([
            'source_type' => 'OFFICIAL_INEP',
            'source_url' => $data['source_url'],
            'source_year' => $exam->edition->year,
            'document_version' => $data['document_version'],
            'checksum' => hash('sha256', json_encode(['theme' => $data['theme'], 'texts' => $data['motivating_texts']])),
            'description' => "Proposta de redação — {$exam->title}",
        ]);
        $prompt = EssayPrompt::updateOrCreate(['exam_id' => $exam->id], [
            'theme' => $data['theme'],
            'motivating_texts' => $data['motivating_texts'],
            'max_lines' => $data['max_lines'] ?? 30,
            'exam_booklet_id' => $data['exam_booklet_id'] ?? null,
            'pdf_page' => $data['pdf_page'] ?? null,
            'content_source_id' => $source->id,
            'review_status' => 'PENDING',
        ]);
        $exam->update(['has_essay' => true, 'review_status' => 'PENDING', 'pipeline_stage' => 'IMPORTED']);
        $this->audit->log('content.essay_prompt.imported', $actorId, 'EssayPrompt', $prompt->id);

        return $prompt;
    }

    /** Regras de nota zero versionadas por edição. */
    public function registerZeroRules(int $year, array $rules, int $actorId, bool $verified = false): void
    {
        $edition = ExamEdition::firstOrCreate(['year' => $year], ['name' => "ENEM {$year}"]);
        foreach ($rules as $r) {
            EssayZeroRule::updateOrCreate(
                ['exam_edition_id' => $edition->id, 'code' => $r['code']],
                ['description' => $r['description'], 'source_url' => $r['source_url'], 'review_status' => $verified ? 'VERIFIED' : 'PENDING'],
            );
        }
        $this->audit->log('content.zero_rules.imported', $actorId, 'ExamEdition', $edition->id, ['count' => count($rules)]);
    }
}
