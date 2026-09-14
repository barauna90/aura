import type { AnswerOption, ExamArea, SessionMode, SessionStatus, ForeignLanguage } from './enums';

export interface AreaResult {
  area: ExamArea;
  total: number;
  correct: number;
  wrong: number;
  blank: number;
  percent: number;
}

export interface ObjectiveResult {
  totalQuestions: number;
  answered: number;
  correct: number;
  wrong: number;
  blank: number;
  percent: number;
  timeUsedSeconds: number;
  avgSecondsPerQuestion: number;
  changedAnswers: number;
  byArea: AreaResult[];
}

export interface AnswerSheetEntry {
  questionNumber: number;
  option: AnswerOption | null;
  changeCount: number;
}

export interface SessionSummary {
  id: string;
  mode: SessionMode;
  status: SessionStatus;
  language: ForeignLanguage | null;
  startedAt: string | null;
  expectedEndAt: string | null;
  finishedAt: string | null;
  remainingSeconds: number | null;
}
