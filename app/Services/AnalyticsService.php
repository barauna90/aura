<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\Essay;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\StudyPlan;
use App\Models\User;
use App\Support\Disclaimers;
use App\Support\Enem;

/**
 * ANALYTICS AGENT. Tudo deriva de resultados reais; nenhum número é suavizado.
 */
class AnalyticsService
{
    public function overview(User $user, string $period = '30D'): array
    {
        $days = ['7D' => 7, '30D' => 30, '90D' => 90, 'ALL' => 0][$period] ?? 30;
        $since = $days ? now()->subDays($days) : null;

        $sessions = ExamSession::with('result')->where('user_id', $user->id)->whereIn('status', ['FINISHED', 'EXPIRED'])
            ->when($since, fn ($q) => $q->where('finished_at', '>=', $since))->orderBy('finished_at')->get();
        $essays = Essay::with('finalResult')->where('user_id', $user->id)->where('status', 'EVALUATED')
            ->when($since, fn ($q) => $q->where('submitted_at', '>=', $since))->orderBy('submitted_at')->get();

        $agg = [];
        foreach ($sessions as $s) {
            foreach ($s->result?->by_area ?? [] as $r) {
                $agg[$r['area']] ??= ['total' => 0, 'correct' => 0, 'blank' => 0];
                $agg[$r['area']]['total'] += $r['total'];
                $agg[$r['area']]['correct'] += $r['correct'];
                $agg[$r['area']]['blank'] += $r['blank'];
            }
        }
        $byArea = [];
        foreach (Enem::OBJECTIVE_AREAS as $area) {
            $e = $agg[$area] ?? null;
            $byArea[] = [
                'area' => $area,
                'total' => $e['total'] ?? 0,
                'correct' => $e['correct'] ?? 0,
                'percent' => $e && $e['total'] ? round($e['correct'] / $e['total'] * 100, 1) : null,
                'blank_rate' => $e && $e['total'] ? $e['blank'] / $e['total'] : 0,
            ];
        }

        $totalQ = $sessions->sum(fn ($s) => $s->result?->total_questions ?? 0);
        $totalC = $sessions->sum(fn ($s) => $s->result?->correct ?? 0);
        $totalB = $sessions->sum(fn ($s) => $s->result?->blank ?? 0);
        $totalSec = $sessions->sum('time_used_seconds');

        $series = $sessions->map(fn ($s) => [
            'date' => $s->finished_at?->toDateString(), 'session_id' => $s->id, 'percent' => (float) ($s->result?->percent ?? 0),
            'by_area' => collect($s->result?->by_area ?? [])->mapWithKeys(fn ($r) => [$r['area'] => $r['total'] ? round($r['correct'] / $r['total'] * 100, 1) : 0])->all(),
        ])->values()->all();

        $comp = [];
        foreach ($essays as $e) {
            foreach ($e->finalResult?->competency_scores ?? [] as $k => $v) {
                $comp[(int) $k][] = $v;
            }
        }
        $weakComp = collect($comp)->map(fn ($v, $k) => ['c' => $k, 'avg' => array_sum($v) / count($v)])->sortBy('avg')->pluck('c')->values()->all();

        $ranked = collect($byArea)->whereNotNull('percent');

        return [
            'period' => $period,
            'exams_completed' => $sessions->count(),
            'full_exams' => $sessions->where('mode', 'PROVA_REAL')->count(),
            'partial_exams' => $sessions->where('mode', 'ESTUDO')->count(),
            'questions_answered' => $totalQ,
            'accuracy' => $totalQ ? round($totalC / $totalQ * 100, 1) : null,
            'blank_rate' => $totalQ ? round($totalB / $totalQ * 100, 1) : null,
            'changed_answers' => $sessions->sum(fn ($s) => $s->result?->changed_answers ?? 0),
            'hours_studied' => round($totalSec / 3600, 1),
            'study_days' => $sessions->map(fn ($s) => $s->finished_at?->toDateString())->unique()->count(),
            'by_area' => $byArea,
            'strongest_area' => $ranked->sortByDesc('percent')->first()['area'] ?? null,
            'weakest_area' => $ranked->sortBy('percent')->first()['area'] ?? null,
            'series' => $series,
            'moving_average' => self::movingAverage(array_column($series, 'percent'), 3),
            'essay' => [
                'count' => $essays->count(),
                'average' => $essays->count() ? (int) round($essays->avg(fn ($e) => $e->finalResult?->total ?? 0)) : null,
                'series' => $essays->map(fn ($e) => ['date' => $e->submitted_at?->toDateString(), 'total' => $e->finalResult?->total ?? 0])->values()->all(),
                'weak_competencies' => $weakComp,
                'label' => Disclaimers::ESSAY_SCORE_LABEL,
                'notice' => Disclaimers::ESSAY_EVALUATION,
            ],
            'score_notice' => Disclaimers::SCORE_ESTIMATE,
        ];
    }

    /** Assuntos com mais erros (somente classificações VERIFIED). */
    public function weakTopics(User $user, int $limit = 5): array
    {
        $errors = Answer::selectRaw('question_id, count(*) as errors')
            ->whereHas('sheet.session', fn ($q) => $q->where('user_id', $user->id))->where('is_correct', false)
            ->groupBy('question_id')->pluck('errors', 'question_id');
        if ($errors->isEmpty()) {
            return [];
        }
        $questions = Question::with('classification.topic')->whereIn('id', $errors->keys())
            ->whereHas('classification', fn ($q) => $q->where('review_status', 'VERIFIED')->whereNotNull('study_topic_id'))->get();
        $counts = [];
        foreach ($questions as $q) {
            $t = $q->classification->topic;
            $counts[$t->slug] ??= ['slug' => $t->slug, 'name' => $t->name, 'area' => $t->area, 'errors' => 0];
            $counts[$t->slug]['errors'] += (int) $errors[$q->id];
        }
        usort($counts, fn ($a, $b) => $b['errors'] <=> $a['errors']);

        return array_slice(array_values($counts), 0, $limit);
    }

    public function dashboard(User $user): array
    {
        $overview = $this->overview($user, 'ALL');
        $last = ExamSession::with(['exam.edition', 'result'])->where('user_id', $user->id)->latest()->first();
        $plan = StudyPlan::with(['tasks' => fn ($q) => $q->whereDate('scheduled_on', today())])->where('user_id', $user->id)->where('active', true)->latest()->first();

        return [
            'overview' => $overview,
            'first_access' => $overview['exams_completed'] === 0,
            'last_session' => $last,
            'continue_session' => $last && in_array($last->status, ['CREATED', 'IN_PROGRESS', 'PAUSED'], true) ? $last : null,
            'today_tasks' => $plan?->tasks ?? collect(),
            'goals' => $user->goals()->where('active', true)->get(),
            'start_here' => [
                'Faça seu primeiro diagnóstico.', 'Veja seus pontos fortes.', 'Descubra o que precisa estudar.', 'Receba seu plano.',
                'Faça provas completas.', 'Treine sua redação.', 'Acompanhe sua evolução.',
            ],
        ];
    }

    private static function movingAverage(array $values, int $window): array
    {
        $out = [];
        foreach ($values as $i => $_) {
            $slice = array_slice($values, max(0, $i - $window + 1), $i - max(0, $i - $window + 1) + 1);
            $out[] = round(array_sum($slice) / count($slice), 1);
        }

        return $out;
    }
}
