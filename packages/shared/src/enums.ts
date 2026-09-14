/**
 * Enumerações compartilhadas entre API e Web.
 * Devem espelhar os enums do schema Prisma (apps/api/prisma/schema.prisma).
 */

export const SOURCE_TYPES = ['OFFICIAL_INEP', 'EDITORIAL', 'AI_GENERATED_EDUCATIONAL'] as const;
export type SourceType = (typeof SOURCE_TYPES)[number];

export const REVIEW_STATUSES = ['PENDING', 'VALIDATING', 'VERIFIED', 'REJECTED'] as const;
export type ReviewStatus = (typeof REVIEW_STATUSES)[number];

/** Fluxo de auditoria de conteúdo (seção 41). */
export const PIPELINE_STAGES = [
  'IMPORTED',
  'AUTO_VALIDATED',
  'HUMAN_REVIEW_1',
  'HUMAN_REVIEW_2',
  'PUBLISHED',
] as const;
export type PipelineStage = (typeof PIPELINE_STAGES)[number];

export const EXAM_AREAS = ['LINGUAGENS', 'HUMANAS', 'NATUREZA', 'MATEMATICA', 'REDACAO'] as const;
export type ExamArea = (typeof EXAM_AREAS)[number];

export const EXAM_AREA_LABEL: Record<ExamArea, string> = {
  LINGUAGENS: 'Linguagens, Códigos e suas Tecnologias',
  HUMANAS: 'Ciências Humanas e suas Tecnologias',
  NATUREZA: 'Ciências da Natureza e suas Tecnologias',
  MATEMATICA: 'Matemática e suas Tecnologias',
  REDACAO: 'Redação',
};

export const EXAM_AREA_SHORT: Record<ExamArea, string> = {
  LINGUAGENS: 'Linguagens',
  HUMANAS: 'Humanas',
  NATUREZA: 'Natureza',
  MATEMATICA: 'Matemática',
  REDACAO: 'Redação',
};

export const EXAM_APPLICATIONS = ['REGULAR', 'REAPLICACAO', 'PPL', 'DIGITAL'] as const;
export type ExamApplication = (typeof EXAM_APPLICATIONS)[number];

export const EXAM_APPLICATION_LABEL: Record<ExamApplication, string> = {
  REGULAR: 'Aplicação regular',
  REAPLICACAO: 'Reaplicação',
  PPL: 'PPL',
  DIGITAL: 'ENEM Digital',
};

export const FOREIGN_LANGUAGES = ['INGLES', 'ESPANHOL'] as const;
export type ForeignLanguage = (typeof FOREIGN_LANGUAGES)[number];

export const SESSION_MODES = ['PROVA_REAL', 'ESTUDO'] as const;
export type SessionMode = (typeof SESSION_MODES)[number];

export const SESSION_STATUSES = ['CREATED', 'IN_PROGRESS', 'PAUSED', 'FINISHED', 'EXPIRED'] as const;
export type SessionStatus = (typeof SESSION_STATUSES)[number];

export const ANSWER_OPTIONS = ['A', 'B', 'C', 'D', 'E'] as const;
export type AnswerOption = (typeof ANSWER_OPTIONS)[number];

export const SUBSCRIPTION_STATUSES = [
  'TRIALING',
  'ACTIVE',
  'PAST_DUE',
  'CANCELED',
  'EXPIRED',
  'REFUNDED',
  'SUSPENDED',
] as const;
export type SubscriptionStatus = (typeof SUBSCRIPTION_STATUSES)[number];

/** Somente estes status concedem acesso premium (seção 28). */
export const PREMIUM_GRANTING_STATUSES: readonly SubscriptionStatus[] = ['TRIALING', 'ACTIVE'];

export const COMMISSION_STATUSES = [
  'PENDING',
  'APPROVED',
  'AVAILABLE',
  'REQUESTED',
  'PAID',
  'CANCELED',
  'REVERSED',
] as const;
export type CommissionStatus = (typeof COMMISSION_STATUSES)[number];

export const ESSAY_COMPETENCIES = [1, 2, 3, 4, 5] as const;
export type EssayCompetency = (typeof ESSAY_COMPETENCIES)[number];

export const ESSAY_COMPETENCY_LABEL: Record<EssayCompetency, string> = {
  1: 'Domínio da modalidade escrita formal da língua portuguesa.',
  2: 'Compreensão da proposta e desenvolvimento do tema dentro da estrutura do texto dissertativo-argumentativo.',
  3: 'Seleção, relação, organização e interpretação de informações, fatos, opiniões e argumentos em defesa de um ponto de vista.',
  4: 'Conhecimento dos mecanismos linguísticos necessários à construção da argumentação.',
  5: 'Elaboração de proposta de intervenção para o problema abordado, respeitando os direitos humanos.',
};

/** Níveis de pontuação por competência utilizados pela correção simulada. */
export const ESSAY_COMPETENCY_LEVELS = [0, 40, 80, 120, 160, 200] as const;

export const USER_ROLES = ['STUDENT', 'REVIEWER', 'ADMIN', 'SUPER_ADMIN'] as const;
export type UserRole = (typeof USER_ROLES)[number];
