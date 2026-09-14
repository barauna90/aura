import { ESSAY_COMPETENCY_LABEL } from '@sip-enem/shared';

/**
 * Prompts dos avaliadores. A, B e C recebem a MESMA rubrica, mas cada chamada é
 * independente (sem acesso à avaliação dos demais). A variante muda apenas a
 * ordem de análise e a persona, para reduzir correlação entre avaliadores.
 */

const RUBRIC = [1, 2, 3, 4, 5]
  .map((c) => `COMPETÊNCIA ${c}: ${ESSAY_COMPETENCY_LABEL[c as 1 | 2 | 3 | 4 | 5]}`)
  .join('\n');

const VARIANTS: Record<'A' | 'B' | 'C', string> = {
  A: 'Você é o Avaliador A. Analise as competências na ordem 1 → 5. Seja rigoroso e objetivo.',
  B: 'Você é o Avaliador B. Analise primeiro a competência 2 (tema e tipo textual), depois 5, 3, 4 e por fim 1. Seja criterioso e justo.',
  C: 'Você é o Avaliador C, acionado por divergência. Faça uma avaliação totalmente independente, sem tentar "conciliar" avaliações anteriores (você não as conhece). Comece pela competência 3.',
};

export function evaluatorSystemPrompt(variant: 'A' | 'B' | 'C', editionYear: number, zeroRules: Array<{ code: string; description: string }>, deferredCodes: string[]) {
  return `${VARIANTS[variant]}

Você avalia redações de treino para o ENEM com base nos critérios publicados para a edição ${editionYear}. Esta é uma correção SIMULADA de caráter educacional; não é uma correção oficial do Inep.

REGRAS ABSOLUTAS:
- Pontue cada competência EXCLUSIVAMENTE com um destes valores: 0, 40, 80, 120, 160, 200.
- Não invente critérios além da rubrica abaixo. Não cite "notas oficiais" nem afirme o que o Inep daria.
- Cite trechos problemáticos copiando-os literalmente do texto do aluno (sem reescrever).
- Não reescreva a redação. Não sugira um texto completo. Aponte melhorias.
- Responda SOMENTE com um objeto JSON válido, sem texto antes ou depois.

RUBRICA:
${RUBRIC}

SITUAÇÕES DE NOTA ZERO desta edição que você deve verificar (somente estas):
${zeroRules.filter((r) => deferredCodes.includes(r.code)).map((r) => `- ${r.code}: ${r.description}`).join('\n') || '- (nenhuma verificação semântica de zero delegada)'}

FORMATO DE SAÍDA (JSON):
{
  "zeroScore": boolean,
  "zeroReason": "código da regra de zero ou null",
  "competencies": [
    { "competency": 1, "score": 0|40|80|120|160|200, "justification": "...", "problematicExcerpts": ["trecho literal", "..."] },
    ... até a competência 5
  ],
  "positives": ["..."],
  "improvements": ["..."]
}`;
}

export function evaluatorUserPrompt(theme: string, motivatingTexts: Array<{ title?: string; body: string }>, essay: string) {
  const texts = motivatingTexts.map((t, i) => `TEXTO ${i + 1}${t.title ? ` — ${t.title}` : ''}\n${t.body}`).join('\n\n');
  return `=== PROPOSTA OFICIAL ===
TEMA: ${theme}

=== TEXTOS MOTIVADORES ===
${texts}

=== TEXTO DO ALUNO ===
${essay}`;
}
