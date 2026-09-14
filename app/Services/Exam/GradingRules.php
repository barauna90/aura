<?php

namespace App\Services\Exam;

/**
 * OBJECTIVE GRADING — regras puras.
 * - Compara SOMENTE o cartão-resposta com o gabarito oficial.
 * - Filtra língua estrangeira pela opção escolhida.
 * - Anuladas não contam. NUNCA converte acertos em "nota ENEM".
 */
final class GradingRules
{
    /**
     * @param  array<int, array{foreign_language:?string, area:string}>  $questions
     */
    public static function applicable(array $questions, ?string $language, array $selectedAreas = []): array
    {
        return array_values(array_filter($questions, function ($q) use ($language, $selectedAreas) {
            if ($selectedAreas && ! in_array($q['area'], $selectedAreas, true)) {
                return false;
            }
            if (! empty($q['foreign_language']) && $q['foreign_language'] !== $language) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  array<int, array{question_id:int, original_number:int, area:string, foreign_language:?string, official:?array{correct:?string, annulled:bool}, discipline?:?string}>  $questions
     * @param  array<int, array{question_id:int, option:?string, change_count:int}>  $sheet
     */
    public static function grade(array $questions, array $sheet, ?string $language, array $selectedAreas = []): array
    {
        $applicable = self::applicable($questions, $language, $selectedAreas);
        usort($applicable, fn ($a, $b) => $a['original_number'] <=> $b['original_number']);
        $byId = [];
        foreach ($sheet as $s) {
            $byId[$s['question_id']] = $s;
        }

        $graded = [];
        foreach ($applicable as $q) {
            $marked = $byId[$q['question_id']]['option'] ?? null;
            $official = $q['official'] ?? null;
            if ($official && ($official['annulled'] ?? false)) {
                $status = 'ANNULLED';
            } elseif ($marked === null) {
                $status = 'BLANK';
            } elseif ($official && $official['correct'] && $marked === $official['correct']) {
                $status = 'CORRECT';
            } else {
                $status = 'WRONG';
            }
            $graded[] = [
                'question_id' => $q['question_id'],
                'original_number' => $q['original_number'],
                'area' => $q['area'],
                'discipline' => $q['discipline'] ?? null,
                'marked' => $marked,
                'official' => $official['correct'] ?? null,
                'status' => $status,
                'change_count' => $byId[$q['question_id']]['change_count'] ?? 0,
            ];
        }

        $counted = array_values(array_filter($graded, fn ($g) => $g['status'] !== 'ANNULLED'));
        $count = fn (string $st) => count(array_filter($counted, fn ($g) => $g['status'] === $st));
        $correct = $count('CORRECT');

        $byArea = [];
        foreach (array_unique(array_column($counted, 'area')) as $area) {
            $list = array_filter($counted, fn ($g) => $g['area'] === $area);
            $c = count(array_filter($list, fn ($g) => $g['status'] === 'CORRECT'));
            $byArea[] = [
                'area' => $area,
                'total' => count($list),
                'correct' => $c,
                'wrong' => count(array_filter($list, fn ($g) => $g['status'] === 'WRONG')),
                'blank' => count(array_filter($list, fn ($g) => $g['status'] === 'BLANK')),
                'percent' => count($list) ? round($c / count($list) * 100, 1) : 0,
            ];
        }

        $byDiscipline = [];
        foreach ($counted as $g) {
            if (! $g['discipline']) {
                continue;
            }
            $byDiscipline[$g['discipline']] ??= ['discipline' => $g['discipline'], 'total' => 0, 'correct' => 0];
            $byDiscipline[$g['discipline']]['total']++;
            if ($g['status'] === 'CORRECT') {
                $byDiscipline[$g['discipline']]['correct']++;
            }
        }
        $byDiscipline = array_values(array_map(fn ($d) => $d + ['percent' => round($d['correct'] / $d['total'] * 100, 1)], $byDiscipline));

        return [
            'total_questions' => count($counted),
            'answered' => count(array_filter($counted, fn ($g) => $g['marked'] !== null)),
            'correct' => $correct,
            'wrong' => $count('WRONG'),
            'blank' => $count('BLANK'),
            'annulled' => count($graded) - count($counted),
            'percent' => $counted ? round($correct / count($counted) * 100, 1) : 0,
            'changed_answers' => count(array_filter($graded, fn ($g) => $g['change_count'] > 1)),
            'by_area' => $byArea,
            'by_discipline' => $byDiscipline,
            'questions' => $graded,
        ];
    }
}
