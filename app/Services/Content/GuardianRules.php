<?php

namespace App\Services\Content;

use App\Support\Enem;

/**
 * ENEM OFFICIAL CONTENT GUARDIAN — regras puras (sem banco), testáveis.
 * Define O QUE é aceitável como conteúdo oficial.
 */
final class GuardianRules
{
    public static function sha256(string $data): string
    {
        return hash('sha256', $data);
    }

    /**
     * Checksum determinístico de um gabarito (número → letra) para detectar alterações.
     *
     * @param  array<int, array{number:int, correct:?string, annulled?:bool, foreign_language?:?string}>  $answers
     */
    public static function answerKeyChecksum(array $answers): string
    {
        $key = fn ($a) => $a['number'].(! empty($a['foreign_language']) ? '/'.$a['foreign_language'] : '');
        usort($answers, fn ($a, $b) => [$a['number'], $a['foreign_language'] ?? ''] <=> [$b['number'], $b['foreign_language'] ?? '']);
        $canonical = implode('|', array_map(
            fn ($a) => $key($a).':'.(($a['annulled'] ?? false) ? 'X' : ($a['correct'] ?? '-')),
            $answers,
        ));

        return self::sha256($canonical);
    }

    /**
     * Valida se uma prova pode ser publicada como "Prova oficial do ENEM".
     *
     * @return array<int, array{code:string, message:string}> vazio = publicável
     */
    public static function validateExamForPublication(array $exam): array
    {
        $v = [];
        $src = $exam['source'];
        if (($src['source_type'] ?? null) !== 'OFFICIAL_INEP') {
            $v[] = ['code' => 'SOURCE_NOT_OFFICIAL', 'message' => 'A fonte da prova não é OFFICIAL_INEP.'];
        }
        if (empty($src['source_url']) || ! preg_match('#^https?://#', $src['source_url'])) {
            $v[] = ['code' => 'SOURCE_URL_MISSING', 'message' => 'SOURCE_URL ausente ou inválida.'];
        }
        if (empty($src['checksum'])) {
            $v[] = ['code' => 'SOURCE_CHECKSUM_MISSING', 'message' => 'CHECKSUM do documento oficial ausente.'];
        }
        if (! is_int($exam['duration_minutes'] ?? null) || $exam['duration_minutes'] <= 0) {
            $v[] = ['code' => 'DURATION_INVALID', 'message' => 'Duração oficial da edição não cadastrada.'];
        }
        if (empty($exam['booklets'])) {
            $v[] = ['code' => 'NO_BOOKLET', 'message' => 'Nenhum caderno oficial cadastrado.'];
        }

        foreach ($exam['booklets'] ?? [] as $b) {
            $label = $b['label'] ?? $b['id'];
            if (empty($b['pdf_checksum'])) {
                $v[] = ['code' => 'BOOKLET_CHECKSUM_MISSING', 'message' => "Caderno {$label} sem checksum do PDF."];
            }
            if (($b['page_count'] ?? 0) <= 0) {
                $v[] = ['code' => 'BOOKLET_NO_PAGES', 'message' => "Caderno {$label} sem páginas."];
            }
            if (empty($b['questions'])) {
                $v[] = ['code' => 'BOOKLET_NO_QUESTIONS', 'message' => "Caderno {$label} sem questões."];
            }
            if (empty($b['answer_sets'])) {
                $v[] = ['code' => 'ANSWER_SET_MISSING', 'message' => "Caderno {$label} sem gabarito oficial importado."];
            }
            foreach ($b['questions'] ?? [] as $q) {
                $n = $q['original_number'];
                if (($q['source_type'] ?? null) !== 'OFFICIAL_INEP') {
                    $v[] = ['code' => 'QUESTION_NOT_OFFICIAL', 'message' => "Questão {$n} não é oficial."];
                }
                $ans = $q['official_answer'] ?? null;
                if (! $ans) {
                    $v[] = ['code' => 'ANSWER_MISSING', 'message' => "Questão {$n} sem gabarito oficial."];
                } elseif (! ($ans['annulled'] ?? false) && ! in_array($ans['correct'] ?? null, Enem::OPTIONS, true)) {
                    $v[] = ['code' => 'ANSWER_EMPTY', 'message' => "Questão {$n} sem alternativa correta e não anulada."];
                }
            }
        }

        return $v;
    }

    public static function nextStage(string $current): ?string
    {
        $i = array_search($current, Enem::PIPELINE_STAGES, true);

        return $i !== false && $i < count(Enem::PIPELINE_STAGES) - 1 ? Enem::PIPELINE_STAGES[$i + 1] : null;
    }

    /** @return array{code:string, message:string}|null */
    public static function canAdvance(string $current, string $target, ?int $review1By, int $actorId): ?array
    {
        if (self::nextStage($current) !== $target) {
            return ['code' => 'INVALID_TRANSITION', 'message' => "Transição {$current} → {$target} não permitida."];
        }
        if ($target === 'HUMAN_REVIEW_2' && $review1By === $actorId) {
            return ['code' => 'SAME_REVIEWER', 'message' => 'A segunda revisão deve ser feita por outra pessoa.'];
        }

        return null;
    }

    public static function isPubliclyOfficial(string $reviewStatus, ?string $sourceType = null, ?string $pipelineStage = null): bool
    {
        if ($reviewStatus !== 'VERIFIED') {
            return false;
        }
        if ($sourceType !== null && $sourceType !== 'OFFICIAL_INEP') {
            return false;
        }
        if ($pipelineStage !== null && $pipelineStage !== 'PUBLISHED') {
            return false;
        }

        return true;
    }
}
