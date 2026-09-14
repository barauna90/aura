import { answerKeyChecksum, canAdvance, ExamSnapshot, isPubliclyOfficial, nextStage, sha256, validateExamForPublication } from './guardian.rules';

const validExam = (): ExamSnapshot => ({
  durationMinutes: 330,
  source: { sourceType: 'OFFICIAL_INEP', sourceUrl: 'https://download.inep.gov.br/enem/provas/2023/dia1.pdf', checksum: sha256('pdf'), reviewStatus: 'VERIFIED' },
  booklets: [
    {
      id: 'b1',
      reviewStatus: 'VERIFIED',
      pdfChecksum: sha256('pdf'),
      pageCount: 32,
      questions: [
        { originalNumber: 1, reviewStatus: 'VERIFIED', sourceType: 'OFFICIAL_INEP', officialAnswer: { correct: 'A', annulled: false, reviewStatus: 'VERIFIED' } },
        { originalNumber: 2, reviewStatus: 'VERIFIED', sourceType: 'OFFICIAL_INEP', officialAnswer: { correct: null, annulled: true, reviewStatus: 'VERIFIED' } },
      ],
      answerSets: [{ reviewStatus: 'VERIFIED', checksum: 'x' }],
    },
  ],
});

describe('ENEM OFFICIAL CONTENT GUARDIAN (seções 1, 3, 41, 65)', () => {
  it('prova completa e verificada é publicável', () => {
    expect(validateExamForPublication(validExam())).toEqual([]);
  });

  it('bloqueia fonte que não seja OFFICIAL_INEP', () => {
    const e = validExam();
    e.source.sourceType = 'AI_GENERATED_EDUCATIONAL';
    expect(validateExamForPublication(e).map((v) => v.code)).toContain('SOURCE_NOT_OFFICIAL');
  });

  it('bloqueia questão gerada por IA dentro de prova oficial', () => {
    const e = validExam();
    e.booklets[0].questions[0].sourceType = 'AI_GENERATED_EDUCATIONAL';
    expect(validateExamForPublication(e).map((v) => v.code)).toContain('QUESTION_NOT_OFFICIAL');
  });

  it('bloqueia gabarito ausente ou vazio', () => {
    const e = validExam();
    e.booklets[0].questions[0].officialAnswer = null;
    expect(validateExamForPublication(e).map((v) => v.code)).toContain('ANSWER_MISSING');
    const e2 = validExam();
    e2.booklets[0].questions[0].officialAnswer = { correct: null, annulled: false, reviewStatus: 'VERIFIED' };
    expect(validateExamForPublication(e2).map((v) => v.code)).toContain('ANSWER_EMPTY');
  });

  it('bloqueia duração não cadastrada (não existe duração universal)', () => {
    const e = validExam();
    e.durationMinutes = 0;
    expect(validateExamForPublication(e).map((v) => v.code)).toContain('DURATION_INVALID');
  });

  it('checksum do gabarito muda se qualquer alternativa for alterada — nada muda silenciosamente', () => {
    const original = answerKeyChecksum([{ number: 1, correct: 'A' }, { number: 2, correct: 'B' }]);
    const tampered = answerKeyChecksum([{ number: 1, correct: 'A' }, { number: 2, correct: 'C' }]);
    const reordered = answerKeyChecksum([{ number: 2, correct: 'B' }, { number: 1, correct: 'A' }]);
    expect(original).not.toBe(tampered);
    expect(original).toBe(reordered);
  });

  it('fluxo de auditoria não pula etapas e exige revisores distintos', () => {
    expect(nextStage('IMPORTED')).toBe('AUTO_VALIDATED');
    expect(nextStage('PUBLISHED')).toBeNull();
    expect(canAdvance('IMPORTED', 'PUBLISHED', {}, 'u1')?.code).toBe('INVALID_TRANSITION');
    expect(canAdvance('HUMAN_REVIEW_1', 'HUMAN_REVIEW_2', { review1: 'u1' }, 'u1')?.code).toBe('SAME_REVIEWER');
    expect(canAdvance('HUMAN_REVIEW_1', 'HUMAN_REVIEW_2', { review1: 'u1' }, 'u2')).toBeNull();
  });

  it('somente VERIFIED + OFFICIAL_INEP + PUBLISHED aparece como oficial', () => {
    expect(isPubliclyOfficial({ reviewStatus: 'VERIFIED', sourceType: 'OFFICIAL_INEP', pipelineStage: 'PUBLISHED' })).toBe(true);
    expect(isPubliclyOfficial({ reviewStatus: 'PENDING', sourceType: 'OFFICIAL_INEP', pipelineStage: 'PUBLISHED' })).toBe(false);
    expect(isPubliclyOfficial({ reviewStatus: 'VERIFIED', sourceType: 'EDITORIAL' })).toBe(false);
    expect(isPubliclyOfficial({ reviewStatus: 'VERIFIED', sourceType: 'OFFICIAL_INEP', pipelineStage: 'HUMAN_REVIEW_2' })).toBe(false);
  });
});
