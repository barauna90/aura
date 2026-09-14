import { applicableQuestions, GradableQuestion, grade } from './grading.rules';

const q = (n: number, area: GradableQuestion['area'], correct: 'A' | 'B' | 'C' | 'D' | 'E' | null, extra: Partial<GradableQuestion> = {}): GradableQuestion => ({
  questionId: `q${n}`,
  originalNumber: n,
  area,
  foreignLanguage: null,
  official: { correct, annulled: false },
  ...extra,
});

const questions: GradableQuestion[] = [
  q(1, 'LINGUAGENS', 'A', { foreignLanguage: 'INGLES' }),
  q(2, 'LINGUAGENS', 'B', { foreignLanguage: 'INGLES' }),
  q(1, 'LINGUAGENS', 'C', { foreignLanguage: 'ESPANHOL', questionId: 'q1es' }),
  q(2, 'LINGUAGENS', 'D', { foreignLanguage: 'ESPANHOL', questionId: 'q2es' }),
  q(6, 'LINGUAGENS', 'E'),
  q(46, 'HUMANAS', 'A'),
  q(47, 'HUMANAS', 'B', { official: { correct: null, annulled: true } }),
  q(91, 'NATUREZA', 'C', { discipline: 'Física' }),
  q(136, 'MATEMATICA', 'D'),
];

describe('Correção objetiva (seções 9–11)', () => {
  it('considera apenas as questões do idioma escolhido', () => {
    const en = applicableQuestions(questions, 'INGLES');
    expect(en.map((x) => x.questionId)).toEqual(['q1', 'q2', 'q6', 'q46', 'q47', 'q91', 'q136']);
    const es = applicableQuestions(questions, 'ESPANHOL');
    expect(es.map((x) => x.questionId)).toContain('q1es');
    expect(es.map((x) => x.questionId)).not.toContain('q1');
  });

  it('só corrige o que está no cartão-resposta; ausência = em branco', () => {
    const r = grade(questions, [{ questionId: 'q1', option: 'A', changeCount: 1 }], 'INGLES');
    expect(r.correct).toBe(1);
    expect(r.blank).toBe(5); // q2, q6, q46, q91, q136 (q47 anulada não conta)
    expect(r.wrong).toBe(0);
  });

  it('questão anulada oficialmente não conta como erro nem como acerto', () => {
    const r = grade(questions, [{ questionId: 'q47', option: 'E', changeCount: 1 }], 'INGLES');
    expect(r.annulled).toBe(1);
    expect(r.totalQuestions).toBe(6);
    expect(r.questions.find((x) => x.questionId === 'q47')?.status).toBe('ANNULLED');
  });

  it('agrupa por área e por disciplina classificada', () => {
    const r = grade(
      questions,
      [
        { questionId: 'q1', option: 'A', changeCount: 1 },
        { questionId: 'q2', option: 'C', changeCount: 3 },
        { questionId: 'q91', option: 'C', changeCount: 1 },
        { questionId: 'q136', option: 'A', changeCount: 1 },
      ],
      'INGLES',
    );
    const ling = r.byArea.find((a) => a.area === 'LINGUAGENS')!;
    expect(ling).toMatchObject({ total: 3, correct: 1, wrong: 1, blank: 1 });
    expect(r.byDiscipline).toEqual([{ discipline: 'Física', total: 1, correct: 1, percent: 100 }]);
    expect(r.changedAnswers).toBe(1); // q2 mudou de resposta (changeCount > 1)
    expect(r.percent).toBe(33.3);
  });

  it('modo estudo pode restringir áreas', () => {
    const r = grade(questions, [], 'INGLES', ['MATEMATICA']);
    expect(r.totalQuestions).toBe(1);
  });

  it('NUNCA produz uma "nota ENEM" — apenas contagens e percentuais', () => {
    const r = grade(questions, [], 'INGLES');
    expect(Object.keys(r)).not.toContain('score');
    expect(Object.keys(r)).not.toContain('notaEnem');
    expect(Object.keys(r)).not.toContain('triScore');
  });
});
