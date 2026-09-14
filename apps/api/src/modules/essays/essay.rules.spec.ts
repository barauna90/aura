import { deterministicZeroCheck } from './zero-score.rules';
import { aggregate, auditConsistency, EvaluatorOutput, needsThirdEvaluator, normalizeScore } from './essay-scoring.rules';

const rules = [
  { code: 'EM_BRANCO', description: 'Folha em branco' },
  { code: 'INSUFICIENTE', description: 'Texto insuficiente' },
  { code: 'IDENTIFICACAO', description: 'Identificação indevida' },
  { code: 'LINGUA_ESTRANGEIRA', description: 'Predominância de língua estrangeira' },
  { code: 'FUGA_TEMA', description: 'Fuga ao tema' },
];

const longText = Array.from({ length: 20 }, (_, i) => `Linha ${i + 1} de um texto dissertativo que discute o tema proposto com argumentos e organização, sem desvios.`).join('\n');

const ev = (evaluator: 'A' | 'B' | 'C', scores: number[], zero = false): EvaluatorOutput => ({
  evaluator,
  zeroScore: zero,
  competencies: scores.map((score, i) => ({ competency: i + 1, score, justification: 'ok', problematicExcerpts: [] })),
  positives: [],
  improvements: [],
});

describe('ZERO SCORE VALIDATOR (seção 15)', () => {
  it('texto em branco zera', () => {
    expect(deterministicZeroCheck('   ', rules).code).toBe('EM_BRANCO');
  });
  it('texto insuficiente usa o mínimo de linhas da edição', () => {
    expect(deterministicZeroCheck('uma linha\nduas linhas', rules, { minLines: 8 }).code).toBe('INSUFICIENTE');
    expect(deterministicZeroCheck('uma linha\nduas linhas', rules, { minLines: 2 }).zero).toBe(false);
  });
  it('identificação indevida zera', () => {
    expect(deterministicZeroCheck(`${longText}\nAssinado: João`, rules).code).toBe('IDENTIFICACAO');
  });
  it('só aplica regras cadastradas para a edição', () => {
    const r = deterministicZeroCheck('   ', [{ code: 'FUGA_TEMA', description: 'x' }]);
    expect(r.zero).toBe(false);
    expect(r.deferredToEvaluators).toEqual(['FUGA_TEMA']);
  });
  it('texto válido não zera e delega verificações semânticas', () => {
    const r = deterministicZeroCheck(longText, rules);
    expect(r.zero).toBe(false);
    expect(r.deferredToEvaluators).toEqual(['FUGA_TEMA']);
  });
});

describe('Avaliadores A/B/C e agregação (seções 14, 16, 18)', () => {
  const thresholds = { total: 100, perCompetency: 80 };

  it('normaliza pontuações para os níveis válidos', () => {
    expect(normalizeScore(150)).toBe(160);
    expect(normalizeScore(999)).toBe(200);
    expect(normalizeScore(-3)).toBe(0);
  });

  it('sem divergência: média por competência', () => {
    const r = aggregate([ev('A', [160, 160, 120, 160, 120]), ev('B', [160, 120, 120, 160, 160])]);
    expect(r.total).toBe(720);
    expect(r.competencyScores[2]).toBe(140);
  });

  it('aciona o terceiro avaliador acima do limite configurado', () => {
    expect(needsThirdEvaluator(ev('A', [200, 200, 200, 200, 200]), ev('B', [120, 120, 120, 120, 120]), thresholds)).toBe(true);
    expect(needsThirdEvaluator(ev('A', [160, 160, 160, 160, 160]), ev('B', [160, 120, 160, 160, 160]), thresholds)).toBe(false);
    expect(needsThirdEvaluator(ev('A', [160, 160, 160, 160, 160]), ev('B', [0, 0, 0, 0, 0], true), thresholds)).toBe(true);
  });

  it('com três avaliadores usa os dois mais próximos por competência', () => {
    const r = aggregate([ev('A', [200, 200, 200, 200, 200]), ev('B', [120, 120, 120, 120, 120]), ev('C', [120, 160, 120, 120, 120])]);
    expect(r.competencyScores[1]).toBe(120);
    expect(r.competencyScores[2]).toBe(140);
  });

  it('zero só prevalece com maioria', () => {
    expect(aggregate([ev('A', [0, 0, 0, 0, 0], true), ev('B', [160, 160, 160, 160, 160]), ev('C', [160, 160, 160, 160, 160])]).zero).toBe(false);
    expect(aggregate([ev('A', [0, 0, 0, 0, 0], true), ev('B', [0, 0, 0, 0, 0], true), ev('C', [160, 160, 160, 160, 160])]).zero).toBe(true);
  });

  it('auditor de consistência sinaliza incoerências', () => {
    const a = ev('A', [200, 0, 160, 200, 200]);
    a.competencies[0].problematicExcerpts = ['erro literal'];
    const flags = auditConsistency([a, ev('B', [40, 40, 40, 40, 40])], 150);
    expect(flags).toContain('A:C1:NOTA_MAXIMA_COM_PROBLEMAS');
    expect(flags).toContain('A:C2_ZERO_C3_ALTA');
    expect(flags).toContain('DIVERGENCIA_EXTREMA_AB');
  });
});
