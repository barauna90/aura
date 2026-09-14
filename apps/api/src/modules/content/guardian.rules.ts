import { createHash } from 'crypto';
import { PipelineStage, ReviewStatus, SourceType } from '@prisma/client';

/**
 * ENEM OFFICIAL CONTENT GUARDIAN — regras puras (sem banco) para serem
 * testáveis. Este arquivo define O QUE é aceitável como conteúdo oficial.
 */

export const PIPELINE_ORDER: PipelineStage[] = [
  'IMPORTED',
  'AUTO_VALIDATED',
  'HUMAN_REVIEW_1',
  'HUMAN_REVIEW_2',
  'PUBLISHED',
];

export interface GuardianViolation {
  code: string;
  message: string;
}

export interface ExamSnapshot {
  durationMinutes: number;
  source: { sourceType: SourceType; sourceUrl: string; checksum: string; reviewStatus: ReviewStatus };
  booklets: Array<{
    id: string;
    reviewStatus: ReviewStatus;
    pdfChecksum: string;
    pageCount: number;
    questions: Array<{
      originalNumber: number;
      reviewStatus: ReviewStatus;
      sourceType: SourceType;
      officialAnswer: { correct: string | null; annulled: boolean; reviewStatus: ReviewStatus } | null;
    }>;
    answerSets: Array<{ reviewStatus: ReviewStatus; checksum: string }>;
  }>;
}

export function sha256(buffer: Buffer | string): string {
  return createHash('sha256').update(buffer).digest('hex');
}

/** Checksum determinístico de um gabarito (número → letra) para detectar alterações. */
export function answerKeyChecksum(answers: Array<{ number: number; correct: string | null; annulled?: boolean }>): string {
  const canonical = [...answers]
    .sort((a, b) => a.number - b.number)
    .map((a) => `${a.number}:${a.annulled ? 'X' : (a.correct ?? '-')}`)
    .join('|');
  return sha256(canonical);
}

/**
 * Valida se uma prova pode ser publicada como "Prova oficial do ENEM".
 * Retorna a lista de violações; vazia = publicável.
 */
export function validateExamForPublication(exam: ExamSnapshot): GuardianViolation[] {
  const v: GuardianViolation[] = [];

  if (exam.source.sourceType !== 'OFFICIAL_INEP') {
    v.push({ code: 'SOURCE_NOT_OFFICIAL', message: 'A fonte da prova não é OFFICIAL_INEP.' });
  }
  if (!exam.source.sourceUrl || !/^https?:\/\//.test(exam.source.sourceUrl)) {
    v.push({ code: 'SOURCE_URL_MISSING', message: 'SOURCE_URL ausente ou inválida.' });
  }
  if (!exam.source.checksum) {
    v.push({ code: 'SOURCE_CHECKSUM_MISSING', message: 'CHECKSUM do documento oficial ausente.' });
  }
  if (exam.source.reviewStatus !== 'VERIFIED') {
    v.push({ code: 'SOURCE_NOT_VERIFIED', message: 'A fonte ainda não foi verificada.' });
  }
  if (!Number.isInteger(exam.durationMinutes) || exam.durationMinutes <= 0) {
    v.push({ code: 'DURATION_INVALID', message: 'Duração oficial da edição não cadastrada.' });
  }
  if (exam.booklets.length === 0) {
    v.push({ code: 'NO_BOOKLET', message: 'Nenhum caderno oficial cadastrado.' });
  }

  for (const booklet of exam.booklets) {
    if (booklet.reviewStatus !== 'VERIFIED') {
      v.push({ code: 'BOOKLET_NOT_VERIFIED', message: `Caderno ${booklet.id} não verificado.` });
    }
    if (!booklet.pdfChecksum) {
      v.push({ code: 'BOOKLET_CHECKSUM_MISSING', message: `Caderno ${booklet.id} sem checksum do PDF.` });
    }
    if (booklet.pageCount <= 0) {
      v.push({ code: 'BOOKLET_NO_PAGES', message: `Caderno ${booklet.id} sem páginas.` });
    }
    if (booklet.questions.length === 0) {
      v.push({ code: 'BOOKLET_NO_QUESTIONS', message: `Caderno ${booklet.id} sem questões.` });
    }
    if (!booklet.answerSets.some((s) => s.reviewStatus === 'VERIFIED')) {
      v.push({ code: 'ANSWER_SET_NOT_VERIFIED', message: `Caderno ${booklet.id} sem gabarito verificado.` });
    }
    for (const q of booklet.questions) {
      if (q.sourceType !== 'OFFICIAL_INEP') {
        v.push({ code: 'QUESTION_NOT_OFFICIAL', message: `Questão ${q.originalNumber} não é oficial.` });
      }
      if (q.reviewStatus !== 'VERIFIED') {
        v.push({ code: 'QUESTION_NOT_VERIFIED', message: `Questão ${q.originalNumber} não verificada.` });
      }
      if (!q.officialAnswer) {
        v.push({ code: 'ANSWER_MISSING', message: `Questão ${q.originalNumber} sem gabarito oficial.` });
      } else {
        if (q.officialAnswer.reviewStatus !== 'VERIFIED') {
          v.push({ code: 'ANSWER_NOT_VERIFIED', message: `Gabarito da questão ${q.originalNumber} não verificado.` });
        }
        if (!q.officialAnswer.annulled && !q.officialAnswer.correct) {
          v.push({ code: 'ANSWER_EMPTY', message: `Questão ${q.originalNumber} sem alternativa correta e não anulada.` });
        }
      }
    }
  }
  return v;
}

/** Transições permitidas no fluxo de auditoria. Nunca pular etapas. */
export function nextStage(current: PipelineStage): PipelineStage | null {
  const idx = PIPELINE_ORDER.indexOf(current);
  return idx >= 0 && idx < PIPELINE_ORDER.length - 1 ? PIPELINE_ORDER[idx + 1] : null;
}

export function canAdvance(
  current: PipelineStage,
  target: PipelineStage,
  reviewers: { review1?: string | null; review2?: string | null },
  actorId: string,
): GuardianViolation | null {
  if (nextStage(current) !== target) {
    return { code: 'INVALID_TRANSITION', message: `Transição ${current} → ${target} não permitida.` };
  }
  if (target === 'HUMAN_REVIEW_2' && reviewers.review1 === actorId) {
    return { code: 'SAME_REVIEWER', message: 'A segunda revisão deve ser feita por outra pessoa.' };
  }
  return null;
}

/** Só conteúdo VERIFIED aparece como oficial. */
export function isPubliclyOfficial(entity: { reviewStatus: ReviewStatus; sourceType?: SourceType; pipelineStage?: PipelineStage }): boolean {
  if (entity.reviewStatus !== 'VERIFIED') return false;
  if (entity.sourceType && entity.sourceType !== 'OFFICIAL_INEP') return false;
  if (entity.pipelineStage && entity.pipelineStage !== 'PUBLISHED') return false;
  return true;
}
