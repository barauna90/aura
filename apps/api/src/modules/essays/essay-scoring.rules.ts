/**
 * Regras puras de agregação das avaliações A/B/C e do auditor de consistência
 * (seções 14, 16, 18).
 */

export interface CompetencyEval {
  competency: number; // 1..5
  score: number; // 0..200
  justification: string;
  problematicExcerpts: string[];
}

export interface EvaluatorOutput {
  evaluator: 'A' | 'B' | 'C';
  zeroScore: boolean;
  zeroReason?: string;
  competencies: CompetencyEval[];
  positives: string[];
  improvements: string[];
}

export interface DivergenceThresholds {
  /** Diferença máxima aceitável no total entre dois avaliadores. */
  total: number;
  /** Diferença máxima aceitável por competência. */
  perCompetency: number;
}

export const VALID_LEVELS = [0, 40, 80, 120, 160, 200];

export function normalizeScore(n: number): number {
  const clamped = Math.max(0, Math.min(200, Math.round(n)));
  return VALID_LEVELS.reduce((best, lvl) => (Math.abs(lvl - clamped) < Math.abs(best - clamped) ? lvl : best), 0);
}

export function total(e: EvaluatorOutput): number {
  if (e.zeroScore) return 0;
  return e.competencies.reduce((a, c) => a + c.score, 0);
}

/** Diverge se o total ou qualquer competência ultrapassa o limite. */
export function divergence(a: EvaluatorOutput, b: EvaluatorOutput): { total: number; perCompetency: number } {
  const per = Math.max(
    ...[1, 2, 3, 4, 5].map((c) => Math.abs(scoreOf(a, c) - scoreOf(b, c))),
  );
  return { total: Math.abs(total(a) - total(b)), perCompetency: per };
}

export function needsThirdEvaluator(a: EvaluatorOutput, b: EvaluatorOutput, t: DivergenceThresholds): boolean {
  if (a.zeroScore !== b.zeroScore) return true;
  const d = divergence(a, b);
  return d.total > t.total || d.perCompetency > t.perCompetency;
}

/**
 * Nota final:
 *  - 2 avaliadores: média por competência.
 *  - 3 avaliadores: por competência, média dos dois mais próximos entre si.
 *  - Zero: só se a maioria dos avaliadores apontar zero.
 */
export function aggregate(evals: EvaluatorOutput[]): { total: number; competencyScores: Record<number, number>; zero: boolean; zeroReason?: string } {
  const zeros = evals.filter((e) => e.zeroScore);
  if (zeros.length * 2 > evals.length) {
    return { total: 0, competencyScores: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 }, zero: true, zeroReason: zeros[0].zeroReason };
  }
  const graded = evals.filter((e) => !e.zeroScore);
  const scores: Record<number, number> = {};
  for (const c of [1, 2, 3, 4, 5]) {
    const values = graded.map((e) => scoreOf(e, c)).sort((x, y) => x - y);
    let value: number;
    if (values.length >= 3) {
      let bestPair = [values[0], values[1]];
      for (let i = 1; i < values.length - 1; i++) {
        if (values[i + 1] - values[i] < bestPair[1] - bestPair[0]) bestPair = [values[i], values[i + 1]];
      }
      value = (bestPair[0] + bestPair[1]) / 2;
    } else {
      value = values.reduce((a, b) => a + b, 0) / values.length;
    }
    scores[c] = Math.round(value / 20) * 20;
  }
  return { total: Object.values(scores).reduce((a, b) => a + b, 0), competencyScores: scores, zero: false };
}

/** REDACTION CONSISTENCY AUDITOR — sinaliza avaliações internamente incoerentes. */
export function auditConsistency(evals: EvaluatorOutput[], wordCount: number): string[] {
  const flags: string[] = [];
  for (const e of evals) {
    if (e.zeroScore) continue;
    for (const c of e.competencies) {
      if (c.score === 200 && c.problematicExcerpts.length > 0) flags.push(`${e.evaluator}:C${c.competency}:NOTA_MAXIMA_COM_PROBLEMAS`);
      if (c.score === 0 && c.problematicExcerpts.length === 0 && !c.justification) flags.push(`${e.evaluator}:C${c.competency}:ZERO_SEM_JUSTIFICATIVA`);
      if (!VALID_LEVELS.includes(c.score)) flags.push(`${e.evaluator}:C${c.competency}:NIVEL_INVALIDO`);
    }
    const c2 = scoreOf(e, 2);
    const c3 = scoreOf(e, 3);
    if (c2 === 0 && c3 >= 120) flags.push(`${e.evaluator}:C2_ZERO_C3_ALTA`);
    if (total(e) === 1000 && wordCount < 200) flags.push(`${e.evaluator}:NOTA_MAXIMA_TEXTO_CURTO`);
  }
  if (evals.length >= 2) {
    const d = divergence(evals[0], evals[1]);
    if (d.perCompetency >= 120) flags.push('DIVERGENCIA_EXTREMA_AB');
  }
  return flags;
}

export function scoreOf(e: EvaluatorOutput, competency: number): number {
  return e.competencies.find((c) => c.competency === competency)?.score ?? 0;
}
