import { AnswerOption, ExamArea, ForeignLanguage } from '@prisma/client';

/**
 * OBJECTIVE GRADING AGENT — regras puras (seções 9, 10, 11).
 *
 * - Compara SOMENTE o cartão-resposta com o gabarito oficial.
 * - Filtra língua estrangeira pela opção escolhida.
 * - Questões anuladas oficialmente não contam como erro.
 * - NUNCA converte acertos em "nota ENEM" (TRI é exclusiva do Inep).
 */

export interface GradableQuestion {
  questionId: string;
  originalNumber: number;
  area: ExamArea;
  foreignLanguage: ForeignLanguage | null;
  official: { correct: AnswerOption | null; annulled: boolean } | null;
  discipline?: string | null;
}

export interface SheetAnswer {
  questionId: string;
  option: AnswerOption | null;
  changeCount: number;
}

export type QuestionStatus = 'CORRECT' | 'WRONG' | 'BLANK' | 'ANNULLED';

export interface GradedQuestion {
  questionId: string;
  originalNumber: number;
  area: ExamArea;
  marked: AnswerOption | null;
  official: AnswerOption | null;
  status: QuestionStatus;
  changeCount: number;
}

export interface AreaBreakdown {
  area: ExamArea;
  total: number;
  correct: number;
  wrong: number;
  blank: number;
  percent: number;
}

export interface GradingResult {
  totalQuestions: number;
  answered: number;
  correct: number;
  wrong: number;
  blank: number;
  annulled: number;
  percent: number;
  changedAnswers: number;
  byArea: AreaBreakdown[];
  byDiscipline: Array<{ discipline: string; total: number; correct: number; percent: number }>;
  questions: GradedQuestion[];
}

/**
 * Questões aplicáveis à sessão: exclui a língua estrangeira NÃO escolhida.
 * Se a prova tem língua estrangeira e o aluno não escolheu, nenhuma questão de idioma é considerada.
 */
export function applicableQuestions<T extends { foreignLanguage: ForeignLanguage | null; area: ExamArea }>(
  questions: T[],
  language: ForeignLanguage | null,
  selectedAreas: ExamArea[] = [],
): T[] {
  return questions.filter((q) => {
    if (selectedAreas.length && !selectedAreas.includes(q.area)) return false;
    if (q.foreignLanguage && q.foreignLanguage !== language) return false;
    return true;
  });
}

export function grade(
  questions: GradableQuestion[],
  sheet: SheetAnswer[],
  language: ForeignLanguage | null,
  selectedAreas: ExamArea[] = [],
): GradingResult {
  const applicable = applicableQuestions(questions, language, selectedAreas).sort(
    (a, b) => a.originalNumber - b.originalNumber,
  );
  const byId = new Map(sheet.map((s) => [s.questionId, s]));

  const graded: GradedQuestion[] = applicable.map((q) => {
    const marked = byId.get(q.questionId)?.option ?? null;
    const changeCount = byId.get(q.questionId)?.changeCount ?? 0;
    let status: QuestionStatus;
    if (q.official?.annulled) status = 'ANNULLED';
    else if (marked === null) status = 'BLANK';
    else if (q.official?.correct && marked === q.official.correct) status = 'CORRECT';
    else status = 'WRONG';
    return {
      questionId: q.questionId,
      originalNumber: q.originalNumber,
      area: q.area,
      marked,
      official: q.official?.correct ?? null,
      status,
      changeCount,
    };
  });

  const counted = graded.filter((g) => g.status !== 'ANNULLED');
  const correct = counted.filter((g) => g.status === 'CORRECT').length;
  const wrong = counted.filter((g) => g.status === 'WRONG').length;
  const blank = counted.filter((g) => g.status === 'BLANK').length;
  const annulled = graded.length - counted.length;

  const areas = [...new Set(counted.map((g) => g.area))];
  const byArea: AreaBreakdown[] = areas.map((area) => {
    const list = counted.filter((g) => g.area === area);
    const c = list.filter((g) => g.status === 'CORRECT').length;
    return {
      area,
      total: list.length,
      correct: c,
      wrong: list.filter((g) => g.status === 'WRONG').length,
      blank: list.filter((g) => g.status === 'BLANK').length,
      percent: list.length ? round((c / list.length) * 100) : 0,
    };
  });

  const disciplineMap = new Map<string, { total: number; correct: number }>();
  for (const q of applicable) {
    if (!q.discipline || q.official?.annulled) continue;
    const g = graded.find((x) => x.questionId === q.questionId)!;
    const entry = disciplineMap.get(q.discipline) ?? { total: 0, correct: 0 };
    entry.total++;
    if (g.status === 'CORRECT') entry.correct++;
    disciplineMap.set(q.discipline, entry);
  }

  return {
    totalQuestions: counted.length,
    answered: counted.filter((g) => g.marked !== null).length,
    correct,
    wrong,
    blank,
    annulled,
    percent: counted.length ? round((correct / counted.length) * 100) : 0,
    changedAnswers: graded.filter((g) => g.changeCount > 1).length,
    byArea,
    byDiscipline: [...disciplineMap.entries()].map(([discipline, v]) => ({
      discipline,
      total: v.total,
      correct: v.correct,
      percent: round((v.correct / v.total) * 100),
    })),
    questions: graded,
  };
}

function round(n: number) {
  return Math.round(n * 10) / 10;
}
