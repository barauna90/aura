<?php

namespace App\Services\Exam;

use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamBooklet;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\SessionResult;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Billing\AccessService;
use App\Services\Study\ErrorNotebookService;
use App\Support\Disclaimers;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * EXAM ENGINE + ANSWER SHEET: sessões, cronômetro do servidor, cartão-resposta
 * com autosave, encerramento (manual ou por expiração) e correção.
 */
class ExamEngineService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly AccessService $access,
        private readonly ErrorNotebookService $notebook,
    ) {}

    public function create(User $user, Exam $exam, ExamBooklet $booklet, string $mode, ?string $language, array $selectedAreas, ?string $device): ExamSession
    {
        if ($exam->has_foreign_language && ! $language) {
            throw ValidationException::withMessages(['language' => 'Escolha a língua estrangeira: Inglês ou Espanhol.']);
        }
        if ($mode === 'PROVA_REAL' && $selectedAreas) {
            throw ValidationException::withMessages(['areas' => 'O Modo Prova Real não permite selecionar áreas específicas.']);
        }
        if ($mode === 'PROVA_REAL') {
            $this->access->assertCanStartFullExam($user, $exam);
        }

        $questions = $booklet->questions()->where('review_status', 'VERIFIED')->get(['id', 'original_number', 'area', 'foreign_language']);
        $applicable = GradingRules::applicable($questions->toArray(), $language, $selectedAreas);
        if (! $applicable) {
            throw ValidationException::withMessages(['areas' => 'Nenhuma questão aplicável para a seleção feita.']);
        }

        return DB::transaction(function () use ($user, $exam, $booklet, $mode, $language, $selectedAreas, $device, $applicable) {
            $session = ExamSession::create([
                'user_id' => $user->id, 'exam_id' => $exam->id, 'exam_booklet_id' => $booklet->id, 'mode' => $mode,
                'language' => $language, 'selected_areas' => $selectedAreas ?: null, 'device' => $device,
            ]);
            $sheet = $session->answerSheet()->create();
            $sheet->answers()->createMany(array_map(fn ($q) => ['question_id' => $q['id'], 'question_number' => $q['original_number']], $applicable));
            $this->audit->log('session.created', $user->id, 'ExamSession', $session->id, ['mode' => $mode, 'exam_id' => $exam->id]);

            return $session;
        });
    }

    public function start(ExamSession $session): ExamSession
    {
        if ($session->status !== 'CREATED') {
            return $session;
        }
        $now = CarbonImmutable::now();
        $session->update([
            'status' => 'IN_PROGRESS', 'started_at' => $now,
            'expected_end_at' => TimerRules::expectedEnd($now, $session->exam->duration_minutes),
        ]);
        $this->audit->log('session.started', $session->user_id, 'ExamSession', $session->id);

        return $session;
    }

    /** Estado atual; expira automaticamente se o tempo terminou. */
    public function state(ExamSession $session): array
    {
        if (TimerRules::isExpired($this->timer($session))) {
            $this->finalize($session, 'EXPIRED');
            $session->refresh();
        }
        $answers = Answer::where('answer_sheet_id', $session->answerSheet->id)->orderBy('question_number')
            ->get(['question_id', 'question_number', 'option', 'change_count']);
        $answered = $answers->whereNotNull('option')->count();

        return [
            'id' => $session->id,
            'mode' => $session->mode,
            'status' => $session->status,
            'language' => $session->language,
            'remaining_seconds' => TimerRules::remainingSeconds($this->timer($session)),
            'server_time' => now()->toIso8601String(),
            'answer_sheet' => ['answered' => $answered, 'blank' => $answers->count() - $answered, 'total' => $answers->count(), 'answers' => $answers],
            'finished' => $session->isFinished(),
            'notices' => [
                'answer_sheet_only' => Disclaimers::ANSWER_SHEET_ONLY,
                'study_mode' => $session->mode === 'ESTUDO' ? Disclaimers::STUDY_MODE_NOTICE : null,
            ],
        ];
    }

    /** @param array<int, array{question_id:int, option:?string, time_spent_sec?:?int}> $entries */
    public function saveAnswers(ExamSession $session, array $entries): array
    {
        if ($session->status === 'CREATED') {
            throw ValidationException::withMessages(['session' => 'Inicie a prova antes de marcar o cartão-resposta.']);
        }
        if ($session->status === 'PAUSED') {
            throw ValidationException::withMessages(['session' => 'Sessão pausada.']);
        }
        if ($session->status !== 'IN_PROGRESS') {
            throw ValidationException::withMessages(['session' => 'Cartão-resposta bloqueado: a prova já foi encerrada.']);
        }
        if (TimerRules::isExpired($this->timer($session))) {
            $this->finalize($session, 'EXPIRED');
            throw ValidationException::withMessages(['session' => 'Tempo esgotado. A prova foi encerrada automaticamente.']);
        }
        $sheet = $session->answerSheet;
        $current = Answer::where('answer_sheet_id', $sheet->id)->get()->keyBy('question_id');
        DB::transaction(function () use ($entries, $current) {
            foreach ($entries as $e) {
                $row = $current->get((int) $e['question_id']);
                if (! $row) {
                    continue;
                }
                $option = $e['option'] ?: null;
                $row->update([
                    'option' => $option,
                    'change_count' => $row->change_count + ($row->option !== $option ? 1 : 0),
                    'answered_at' => $option ? now() : null,
                    'time_spent_sec' => $e['time_spent_sec'] ?? $row->time_spent_sec,
                ]);
            }
        });

        return ['saved' => count($entries), 'remaining_seconds' => TimerRules::remainingSeconds($this->timer($session))];
    }

    public function pause(ExamSession $session): void
    {
        $check = TimerRules::canPause($this->timer($session));
        if (! $check['ok']) {
            throw ValidationException::withMessages(['session' => $check['reason']]);
        }
        $session->update(['status' => 'PAUSED', 'paused_at' => now()]);
    }

    public function resume(ExamSession $session): void
    {
        if ($session->status !== 'PAUSED') {
            throw ValidationException::withMessages(['session' => 'Sessão não está pausada.']);
        }
        $r = TimerRules::resume($this->timer($session));
        $session->update(['status' => 'IN_PROGRESS', 'paused_at' => null, 'expected_end_at' => $r['expected_end_at'], 'paused_seconds' => $r['paused_seconds']]);
    }

    public function finish(ExamSession $session): ExamSession
    {
        if ($session->isFinished()) {
            return $session;
        }
        if ($session->status === 'CREATED') {
            throw ValidationException::withMessages(['session' => 'A prova não foi iniciada.']);
        }
        $this->finalize($session, 'FINISHED');

        return $session->refresh();
    }

    /** Cron: encerra sessões expiradas mesmo sem o cliente chamar o estado. */
    public function expireStale(): int
    {
        $stale = ExamSession::with('exam')->where('status', 'IN_PROGRESS')->where('expected_end_at', '<', now())->get();
        foreach ($stale as $s) {
            $this->finalize($s, 'EXPIRED');
        }

        return $stale->count();
    }

    private function finalize(ExamSession $session, string $status): void
    {
        $now = CarbonImmutable::now();
        $finishedAt = $status === 'EXPIRED' && $session->expected_end_at ? CarbonImmutable::instance($session->expected_end_at) : $now;
        $used = TimerRules::timeUsedSeconds($this->timer($session), $finishedAt);

        $sheet = $session->answerSheet()->with('answers')->firstOrFail();
        $questions = Question::with(['officialAnswer', 'classification'])->whereIn('id', $sheet->answers->pluck('question_id'))->get();
        $result = GradingRules::grade(
            $questions->map(fn (Question $q) => [
                'question_id' => $q->id,
                'original_number' => $q->original_number,
                'area' => $q->area,
                'foreign_language' => $q->foreign_language,
                'official' => $q->officialAnswer && $q->officialAnswer->review_status === 'VERIFIED'
                    ? ['correct' => $q->officialAnswer->correct, 'annulled' => $q->officialAnswer->annulled] : null,
                'discipline' => $q->classification?->review_status === 'VERIFIED' ? $q->classification->discipline : null,
            ])->all(),
            $sheet->answers->map(fn (Answer $a) => ['question_id' => $a->question_id, 'option' => $a->option, 'change_count' => $a->change_count])->all(),
            $session->language,
            $session->selected_areas ?? [],
        );

        DB::transaction(function () use ($session, $sheet, $status, $finishedAt, $used, $result, $now) {
            $session->update(['status' => $status, 'finished_at' => $finishedAt, 'time_used_seconds' => $used]);
            $sheet->update(['locked_at' => $now]);
            foreach ($result['questions'] as $g) {
                Answer::where('answer_sheet_id', $sheet->id)->where('question_id', $g['question_id'])
                    ->update(['is_correct' => $g['status'] === 'CORRECT' ? true : ($g['status'] === 'WRONG' ? false : null)]);
            }
            SessionResult::updateOrCreate(['exam_session_id' => $session->id], [
                'total_questions' => $result['total_questions'],
                'correct' => $result['correct'],
                'wrong' => $result['wrong'],
                'blank' => $result['blank'],
                'percent' => $result['percent'],
                'time_used_seconds' => $used,
                'avg_seconds_per_question' => $result['total_questions'] ? round($used / $result['total_questions'], 1) : 0,
                'changed_answers' => $result['changed_answers'],
                'by_area' => $result['by_area'],
                'by_discipline' => $result['by_discipline'],
            ]);
            $this->audit->log('session.'.strtolower($status), $session->user_id, 'ExamSession', $session->id);
        });

        $wrong = array_column(array_filter($result['questions'], fn ($g) => $g['status'] === 'WRONG'), 'question_id');
        $this->notebook->addMany($session->user_id, $wrong);
    }

    /** Relatório de questões (após encerramento). */
    public function questionReport(ExamSession $session): array
    {
        $answers = Answer::with(['question.officialAnswer', 'question.classification.topic', 'question.resolution'])
            ->where('answer_sheet_id', $session->answerSheet->id)->orderBy('question_number')->get();

        return $answers->map(function (Answer $a) {
            $q = $a->question;
            $official = $q->officialAnswer?->review_status === 'VERIFIED' ? $q->officialAnswer : null;
            $annulled = (bool) $official?->annulled;
            $status = $annulled ? 'ANNULLED' : ($a->option === null ? 'BLANK' : ($a->is_correct ? 'CORRECT' : 'WRONG'));
            $cls = $q->classification?->review_status === 'VERIFIED' ? $q->classification : null;
            $res = $q->resolution?->review_status === 'VERIFIED' ? $q->resolution : null;

            return [
                'question_id' => $q->id, 'number' => $q->original_number, 'area' => $q->area, 'page' => $q->page_number,
                'marked' => $a->option, 'official' => $official?->correct, 'annulled' => $annulled, 'status' => $status,
                'change_count' => $a->change_count, 'time_spent_sec' => $a->time_spent_sec,
                'topic' => $cls?->topic?->name, 'discipline' => $cls?->discipline, 'skill' => $cls?->skill,
                'resolution' => $res?->body, 'resolution_notice' => $res ? null : Disclaimers::NO_RESOLUTION,
            ];
        })->all();
    }

    private function timer(ExamSession $s): array
    {
        return [
            'mode' => $s->mode, 'status' => $s->status, 'started_at' => $s->started_at, 'expected_end_at' => $s->expected_end_at,
            'paused_at' => $s->paused_at, 'paused_seconds' => $s->paused_seconds,
        ];
    }
}
