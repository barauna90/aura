<?php

namespace App\Services\Essay;

use App\Support\Enem;

/**
 * Prompts dos avaliadores. A, B e C recebem a MESMA rubrica, mas cada chamada é
 * independente (sem acesso à avaliação dos demais). A variante muda apenas a
 * ordem de análise e a persona, para reduzir correlação.
 */
final class EvaluatorPrompts
{
    private const VARIANTS = [
        'A' => 'Você é o Avaliador A. Analise as competências na ordem 1 → 5. Seja rigoroso e objetivo.',
        'B' => 'Você é o Avaliador B. Analise primeiro a competência 2 (tema e tipo textual), depois 5, 3, 4 e por fim 1. Seja criterioso e justo.',
        'C' => 'Você é o Avaliador C, acionado por divergência. Faça uma avaliação totalmente independente, sem tentar "conciliar" avaliações anteriores (você não as conhece). Comece pela competência 3.',
    ];

    public static function system(string $variant, int $year, array $zeroRules, array $deferredCodes): string
    {
        $rubric = implode("\n", array_map(fn ($c, $d) => "COMPETÊNCIA {$c}: {$d}", array_keys(Enem::ESSAY_COMPETENCIES), Enem::ESSAY_COMPETENCIES));
        $zero = implode("\n", array_map(
            fn ($r) => "- {$r['code']}: {$r['description']}",
            array_filter($zeroRules, fn ($r) => in_array($r['code'], $deferredCodes, true)),
        )) ?: '- (nenhuma verificação semântica de zero delegada)';

        return self::VARIANTS[$variant]."

Você avalia redações de treino para o ENEM com base nos critérios publicados para a edição {$year}. Esta é uma correção SIMULADA de caráter educacional; não é uma correção oficial do Inep.

REGRAS ABSOLUTAS:
- Pontue cada competência EXCLUSIVAMENTE com um destes valores: 0, 40, 80, 120, 160, 200.
- Não invente critérios além da rubrica abaixo. Não cite \"notas oficiais\" nem afirme o que o Inep daria.
- Cite trechos problemáticos copiando-os literalmente do texto do aluno (sem reescrever).
- Não reescreva a redação. Não sugira um texto completo. Aponte melhorias.
- Responda SOMENTE com um objeto JSON válido, sem texto antes ou depois.

RUBRICA:
{$rubric}

SITUAÇÕES DE NOTA ZERO desta edição que você deve verificar (somente estas):
{$zero}

FORMATO DE SAÍDA (JSON):
{
  \"zeroScore\": boolean,
  \"zeroReason\": \"código da regra de zero ou null\",
  \"competencies\": [
    { \"competency\": 1, \"score\": 0|40|80|120|160|200, \"justification\": \"...\", \"problematicExcerpts\": [\"trecho literal\"] },
    ... até a competência 5
  ],
  \"positives\": [\"...\"],
  \"improvements\": [\"...\"]
}";
    }

    public static function user(string $theme, array $motivatingTexts, string $essay): string
    {
        $texts = implode("\n\n", array_map(
            fn ($i, $t) => 'TEXTO '.($i + 1).(! empty($t['title']) ? " — {$t['title']}" : '')."\n{$t['body']}",
            array_keys($motivatingTexts), $motivatingTexts,
        ));

        return "=== PROPOSTA OFICIAL ===\nTEMA: {$theme}\n\n=== TEXTOS MOTIVADORES ===\n{$texts}\n\n=== TEXTO DO ALUNO ===\n{$essay}";
    }
}
