<?php

namespace App\Services\Study;

use App\Support\Enem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * STUDY PLAN ORCHESTRATOR — regras puras. Priorização determinística e auditável
 * a partir do desempenho real; só questões oficiais.
 */
final class StudyPlanRules
{
    /** @param array<int, array{area:string, percent:?float, blank_rate:float}> $areas */
    public static function prioritize(array $areas): array
    {
        $objective = array_values(array_filter($areas, fn ($a) => in_array($a['area'], Enem::OBJECTIVE_AREAS, true)));
        usort($objective, function ($a, $b) {
            $score = fn ($x) => $x['percent'] === null ? -1 : $x['percent'] - $x['blank_rate'] * 20;

            return $score($a) <=> $score($b);
        });

        return array_column($objective, 'area');
    }

    public static function allocateWeeklyMinutes(int $weeklyHours, array $prioritized, float $essayShare = 0.15): array
    {
        $total = max(1, $weeklyHours * 60);
        $essay = (int) round($total * $essayShare);
        $remaining = $total - $essay;
        $n = count($prioritized);
        $weights = array_map(fn ($i) => $n - $i, array_keys($prioritized));
        $sum = max(1, array_sum($weights));
        $out = ['REDACAO' => $essay];
        foreach ($prioritized as $i => $area) {
            $out[$area] = (int) round($remaining * $weights[$i] / $sum);
        }

        return $out;
    }

    /**
     * @param  array{kind:string, start_date:CarbonInterface, target_date:?CarbonInterface, weekly_hours:int, areas:array, essay_weak_competencies:array, error_notebook_due:int, weak_topics:array}  $in
     */
    public static function buildWeek(array $in, int $weekIndex = 0): array
    {
        $tasks = [];
        $prioritized = self::prioritize($in['areas']);
        $minutes = self::allocateWeeklyMinutes($in['weekly_hours'], $prioritized, $in['kind'] === 'INTENSIVO' ? 0.2 : 0.15);
        $start = CarbonImmutable::instance($in['start_date'])->startOfDay()->addWeeks($weekIndex);
        $day = fn (int $offset) => $start->addDays($offset);
        $daysPerWeek = $in['kind'] === 'INTENSIVO' ? 6 : 5;

        $slot = 0;
        foreach ($prioritized as $area) {
            $areaMinutes = $minutes[$area] ?? 0;
            if ($areaMinutes <= 0) {
                continue;
            }
            $blocks = max(1, (int) round($areaMinutes / 50));
            $per = (int) round($areaMinutes / $blocks);
            $weakTopic = null;
            foreach ($in['weak_topics'] as $t) {
                if ($t['area'] === $area) {
                    $weakTopic = $t;
                    break;
                }
            }
            for ($b = 0; $b < $blocks; $b++) {
                $tasks[] = [
                    'scheduled_on' => $day($slot % $daysPerWeek), 'kind' => 'QUESTOES', 'area' => $area, 'topic_slug' => $weakTopic['slug'] ?? null,
                    'title' => $weakTopic ? "Questões oficiais de {$weakTopic['name']}" : 'Questões oficiais — '.Enem::AREA_SHORT[$area],
                    'minutes' => $per, 'payload' => ['area' => $area, 'topic' => $weakTopic['slug'] ?? null, 'source' => 'OFFICIAL_INEP'],
                ];
                $slot++;
            }
        }
        if ($in['error_notebook_due'] > 0) {
            $n = min($in['error_notebook_due'], 20);
            $tasks[] = ['scheduled_on' => $day(2), 'kind' => 'REVISAO', 'area' => null, 'topic_slug' => null, 'title' => "Revisar {$n} questões do caderno de erros", 'minutes' => min(60, 3 * $n), 'payload' => ['due' => $in['error_notebook_due']]];
        }
        $essayMinutes = $minutes['REDACAO'] ?? 0;
        if ($essayMinutes > 0) {
            $focus = $in['essay_weak_competencies'][0] ?? null;
            $tasks[] = ['scheduled_on' => $day($daysPerWeek - 1), 'kind' => 'REDACAO', 'area' => 'REDACAO', 'topic_slug' => null, 'title' => $focus ? "Redação com foco na competência {$focus}" : 'Redação — proposta oficial', 'minutes' => max(60, $essayMinutes), 'payload' => ['focus_competency' => $focus]];
        }
        if ($in['kind'] === 'INTENSIVO' || $weekIndex % 2 === 1) {
            $tasks[] = ['scheduled_on' => $day($daysPerWeek), 'kind' => 'PROVA', 'area' => null, 'topic_slug' => null, 'title' => 'Prova completa oficial — Modo Prova Real', 'minutes' => 300, 'payload' => ['mode' => 'PROVA_REAL']];
        }

        return $tasks;
    }

    public static function buildPlan(array $in): array
    {
        $weeks = 4;
        if ($in['target_date'] && $in['target_date']->gt($in['start_date'])) {
            $weeks = min(16, max(1, (int) ceil($in['start_date']->diffInDays($in['target_date']) / 7)));
        }
        $tasks = [];
        for ($w = 0; $w < $weeks; $w++) {
            $tasks = [...$tasks, ...self::buildWeek($in, $w)];
        }

        return array_values(array_filter($tasks, fn ($t) => ! $in['target_date'] || $t['scheduled_on']->lte($in['target_date'])));
    }
}
