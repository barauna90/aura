<?php

namespace App\Services\Essay;

/**
 * ZERO SCORE VALIDATOR — só aplica verificações cujo código exista no conjunto
 * de regras VERIFIED da edição. Regras semânticas (fuga ao tema etc.) são
 * delegadas aos avaliadores e só valem com concordância.
 */
final class ZeroScoreRules
{
    private const PT = ['de', 'que', 'não', 'para', 'com', 'uma', 'os', 'as', 'do', 'da', 'em', 'um', 'é', 'se', 'por', 'mais', 'como', 'mas', 'ao', 'dos', 'das', 'sua', 'seu', 'são', 'também'];

    private const FOREIGN = ['the', 'and', 'with', 'that', 'this', 'for', 'are', 'los', 'las', 'con', 'una', 'pero', 'sobre', 'está'];

    public static function countLines(string $text): int
    {
        return count(array_filter(preg_split('/\r?\n/', $text), fn ($l) => trim($l) !== ''));
    }

    /**
     * @param  array<int, array{code:string, description:string}>  $editionRules
     * @return array{zero:bool, code?:string, description?:string, deferred:array<int,string>}
     */
    public static function check(string $text, array $editionRules, int $minLines = 8): array
    {
        $enabled = [];
        foreach ($editionRules as $r) {
            $enabled[$r['code']] = $r['description'];
        }
        $deferred = array_values(array_filter(['FUGA_TEMA', 'NAO_DISSERTATIVO', 'DESCONECTADO'], fn ($c) => isset($enabled[$c])));
        $hit = fn (string $code) => ['zero' => true, 'code' => $code, 'description' => $enabled[$code], 'deferred' => $deferred];

        $trimmed = trim($text);
        if (isset($enabled['EM_BRANCO']) && $trimmed === '') {
            return $hit('EM_BRANCO');
        }
        if (isset($enabled['INSUFICIENTE']) && self::countLines($trimmed) < $minLines) {
            return $hit('INSUFICIENTE');
        }
        if (isset($enabled['ANULACAO_DELIBERADA'])) {
            if (preg_match('/^\s*[\W_]{5,}\s*$/m', $trimmed) || preg_match('/(.)\1{30,}/u', $trimmed) || preg_match('/(hino nacional|receita de bolo)/iu', $trimmed)) {
                return $hit('ANULACAO_DELIBERADA');
            }
        }
        if (isset($enabled['IDENTIFICACAO'])) {
            if (preg_match('/(?:^|\n)\s*(?:assinado|ass\.|nome:|inscri[cç][aã]o|cpf|matr[ií]cula)\b/iu', $trimmed) || preg_match('/[\w.+-]+@[\w-]+\.[\w.]+/', $trimmed)) {
                return $hit('IDENTIFICACAO');
            }
        }
        if (isset($enabled['LINGUA_ESTRANGEIRA'])) {
            $words = array_filter(preg_split('/[^a-záéíóúâêôãõç]+/u', mb_strtolower($trimmed)));
            if (count($words) > 30) {
                $pt = count(array_intersect($words, self::PT));
                $foreign = count(array_filter($words, fn ($w) => in_array($w, self::FOREIGN, true) && ! in_array($w, self::PT, true)));
                if ($foreign > $pt * 1.5 && $foreign > 5) {
                    return $hit('LINGUA_ESTRANGEIRA');
                }
            }
        }

        return ['zero' => false, 'deferred' => $deferred];
    }
}
