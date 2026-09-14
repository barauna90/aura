<?php

namespace App\Services\Essay;

use App\Support\Enem;

/**
 * Agregação das avaliações A/B/C e auditor de consistência.
 *
 * Avaliação: ['evaluator'=>'A','zero_score'=>bool,'zero_reason'=>?string,
 *   'competencies'=>[['competency'=>1,'score'=>160,'justification'=>'...','problematic_excerpts'=>[]],...],
 *   'positives'=>[], 'improvements'=>[]]
 */
final class ScoringRules
{
    public static function normalizeScore(int|float $n): int
    {
        $clamped = max(0, min(200, (int) round($n)));
        $best = 0;
        foreach (Enem::ESSAY_LEVELS as $lvl) {
            if (abs($lvl - $clamped) < abs($best - $clamped)) {
                $best = $lvl;
            }
        }

        return $best;
    }

    public static function scoreOf(array $e, int $c): int
    {
        foreach ($e['competencies'] as $x) {
            if ((int) $x['competency'] === $c) {
                return (int) $x['score'];
            }
        }

        return 0;
    }

    public static function total(array $e): int
    {
        return $e['zero_score'] ? 0 : array_sum(array_column($e['competencies'], 'score'));
    }

    /** @return array{total:int, per_competency:int} */
    public static function divergence(array $a, array $b): array
    {
        $per = max(array_map(fn ($c) => abs(self::scoreOf($a, $c) - self::scoreOf($b, $c)), [1, 2, 3, 4, 5]));

        return ['total' => abs(self::total($a) - self::total($b)), 'per_competency' => $per];
    }

    public static function needsThirdEvaluator(array $a, array $b, int $totalThreshold, int $competencyThreshold): bool
    {
        if ($a['zero_score'] !== $b['zero_score']) {
            return true;
        }
        $d = self::divergence($a, $b);

        return $d['total'] > $totalThreshold || $d['per_competency'] > $competencyThreshold;
    }

    /**
     * 2 avaliadores: média por competência. 3: média dos dois mais próximos.
     * Zero só com maioria.
     *
     * @return array{total:int, competency_scores:array<int,int>, zero:bool, zero_reason:?string}
     */
    public static function aggregate(array $evals): array
    {
        $zeros = array_values(array_filter($evals, fn ($e) => $e['zero_score']));
        if (count($zeros) * 2 > count($evals)) {
            return ['total' => 0, 'competency_scores' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0], 'zero' => true, 'zero_reason' => $zeros[0]['zero_reason'] ?? null];
        }
        $graded = array_values(array_filter($evals, fn ($e) => ! $e['zero_score']));
        $scores = [];
        foreach ([1, 2, 3, 4, 5] as $c) {
            $values = array_map(fn ($e) => self::scoreOf($e, $c), $graded);
            sort($values);
            if (count($values) >= 3) {
                $best = [$values[0], $values[1]];
                for ($i = 1; $i < count($values) - 1; $i++) {
                    if ($values[$i + 1] - $values[$i] < $best[1] - $best[0]) {
                        $best = [$values[$i], $values[$i + 1]];
                    }
                }
                $value = ($best[0] + $best[1]) / 2;
            } else {
                $value = array_sum($values) / max(1, count($values));
            }
            $scores[$c] = (int) (round($value / 20) * 20);
        }

        return ['total' => array_sum($scores), 'competency_scores' => $scores, 'zero' => false, 'zero_reason' => null];
    }

    /** REDACTION CONSISTENCY AUDITOR. @return array<int,string> */
    public static function auditConsistency(array $evals, int $wordCount): array
    {
        $flags = [];
        foreach ($evals as $e) {
            if ($e['zero_score']) {
                continue;
            }
            $ev = $e['evaluator'];
            foreach ($e['competencies'] as $c) {
                if ($c['score'] === 200 && ! empty($c['problematic_excerpts'])) {
                    $flags[] = "{$ev}:C{$c['competency']}:NOTA_MAXIMA_COM_PROBLEMAS";
                }
                if ($c['score'] === 0 && empty($c['problematic_excerpts']) && trim((string) $c['justification']) === '') {
                    $flags[] = "{$ev}:C{$c['competency']}:ZERO_SEM_JUSTIFICATIVA";
                }
                if (! in_array($c['score'], Enem::ESSAY_LEVELS, true)) {
                    $flags[] = "{$ev}:C{$c['competency']}:NIVEL_INVALIDO";
                }
            }
            if (self::scoreOf($e, 2) === 0 && self::scoreOf($e, 3) >= 120) {
                $flags[] = "{$ev}:C2_ZERO_C3_ALTA";
            }
            if (self::total($e) === 1000 && $wordCount < 200) {
                $flags[] = "{$ev}:NOTA_MAXIMA_TEXTO_CURTO";
            }
        }
        if (count($evals) >= 2 && self::divergence($evals[0], $evals[1])['per_competency'] >= 120) {
            $flags[] = 'DIVERGENCIA_EXTREMA_AB';
        }

        return $flags;
    }
}
