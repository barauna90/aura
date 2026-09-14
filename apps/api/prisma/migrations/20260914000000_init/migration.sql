-- CreateSchema
CREATE SCHEMA IF NOT EXISTS "public";

-- CreateEnum
CREATE TYPE "UserRole" AS ENUM ('STUDENT', 'REVIEWER', 'ADMIN', 'SUPER_ADMIN');

-- CreateEnum
CREATE TYPE "SourceType" AS ENUM ('OFFICIAL_INEP', 'EDITORIAL', 'AI_GENERATED_EDUCATIONAL');

-- CreateEnum
CREATE TYPE "ReviewStatus" AS ENUM ('PENDING', 'VALIDATING', 'VERIFIED', 'REJECTED');

-- CreateEnum
CREATE TYPE "PipelineStage" AS ENUM ('IMPORTED', 'AUTO_VALIDATED', 'HUMAN_REVIEW_1', 'HUMAN_REVIEW_2', 'PUBLISHED');

-- CreateEnum
CREATE TYPE "ExamArea" AS ENUM ('LINGUAGENS', 'HUMANAS', 'NATUREZA', 'MATEMATICA', 'REDACAO');

-- CreateEnum
CREATE TYPE "ExamApplication" AS ENUM ('REGULAR', 'REAPLICACAO', 'PPL', 'DIGITAL');

-- CreateEnum
CREATE TYPE "BookletColor" AS ENUM ('AZUL', 'AMARELO', 'BRANCO', 'ROSA', 'CINZA', 'VERDE', 'LARANJA', 'NAO_APLICAVEL');

-- CreateEnum
CREATE TYPE "ForeignLanguage" AS ENUM ('INGLES', 'ESPANHOL');

-- CreateEnum
CREATE TYPE "AnswerOption" AS ENUM ('A', 'B', 'C', 'D', 'E');

-- CreateEnum
CREATE TYPE "SessionMode" AS ENUM ('PROVA_REAL', 'ESTUDO');

-- CreateEnum
CREATE TYPE "SessionStatus" AS ENUM ('CREATED', 'IN_PROGRESS', 'PAUSED', 'FINISHED', 'EXPIRED');

-- CreateEnum
CREATE TYPE "EssayStatus" AS ENUM ('DRAFT', 'SUBMITTED', 'EVALUATING', 'EVALUATED', 'FAILED');

-- CreateEnum
CREATE TYPE "EvaluatorRole" AS ENUM ('A', 'B', 'C');

-- CreateEnum
CREATE TYPE "SubscriptionStatus" AS ENUM ('TRIALING', 'ACTIVE', 'PAST_DUE', 'CANCELED', 'EXPIRED', 'REFUNDED', 'SUSPENDED');

-- CreateEnum
CREATE TYPE "PaymentStatus" AS ENUM ('PENDING', 'CONFIRMED', 'FAILED', 'REFUNDED', 'CHARGEBACK');

-- CreateEnum
CREATE TYPE "PaymentMethod" AS ENUM ('PIX', 'CREDIT_CARD', 'BOLETO');

-- CreateEnum
CREATE TYPE "CouponType" AS ENUM ('PERCENT', 'FIXED', 'FIRST_MONTH', 'N_MONTHS', 'FREE_TRIAL');

-- CreateEnum
CREATE TYPE "CommissionStatus" AS ENUM ('PENDING', 'APPROVED', 'AVAILABLE', 'REQUESTED', 'PAID', 'CANCELED', 'REVERSED');

-- CreateEnum
CREATE TYPE "CommissionModel" AS ENUM ('PERCENT', 'FIXED');

-- CreateEnum
CREATE TYPE "WithdrawalStatus" AS ENUM ('REQUESTED', 'APPROVED', 'PAID', 'REJECTED');

-- CreateEnum
CREATE TYPE "StudyTaskStatus" AS ENUM ('PENDING', 'DONE', 'SKIPPED');

-- CreateEnum
CREATE TYPE "NotificationChannel" AS ENUM ('EMAIL', 'WEB_PUSH', 'IN_APP');

-- CreateEnum
CREATE TYPE "TicketStatus" AS ENUM ('OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED');

-- CreateTable
CREATE TABLE "User" (
    "id" TEXT NOT NULL,
    "email" TEXT NOT NULL,
    "passwordHash" TEXT NOT NULL,
    "role" "UserRole" NOT NULL DEFAULT 'STUDENT',
    "isActive" BOOLEAN NOT NULL DEFAULT true,
    "mfaEnabled" BOOLEAN NOT NULL DEFAULT false,
    "mfaSecret" TEXT,
    "referralCode" TEXT NOT NULL,
    "referredById" TEXT,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,
    "deletedAt" TIMESTAMP(3),

    CONSTRAINT "User_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Profile" (
    "id" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "fullName" TEXT NOT NULL,
    "cpfHash" TEXT,
    "goal" TEXT,
    "targetExamDate" TIMESTAMP(3),
    "weeklyHours" INTEGER,
    "mainDifficulty" TEXT,
    "onboardingDone" BOOLEAN NOT NULL DEFAULT false,
    "fontScale" INTEGER NOT NULL DEFAULT 100,
    "theme" TEXT NOT NULL DEFAULT 'system',
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "Profile_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "RefreshToken" (
    "id" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "tokenHash" TEXT NOT NULL,
    "expiresAt" TIMESTAMP(3) NOT NULL,
    "revokedAt" TIMESTAMP(3),
    "userAgent" TEXT,
    "ip" TEXT,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "RefreshToken_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Consent" (
    "id" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "type" TEXT NOT NULL,
    "version" TEXT NOT NULL,
    "granted" BOOLEAN NOT NULL,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Consent_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "ContentSource" (
    "id" TEXT NOT NULL,
    "sourceType" "SourceType" NOT NULL,
    "sourceUrl" TEXT NOT NULL,
    "sourceYear" INTEGER NOT NULL,
    "documentVersion" TEXT NOT NULL,
    "importDate" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "lastValidation" TIMESTAMP(3),
    "checksum" TEXT NOT NULL,
    "reviewStatus" "ReviewStatus" NOT NULL DEFAULT 'PENDING',
    "description" TEXT,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "ContentSource_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Exam" (
    "id" TEXT NOT NULL,
    "editionId" TEXT NOT NULL,
    "application" "ExamApplication" NOT NULL,
    "day" INTEGER NOT NULL,
    "title" TEXT NOT NULL,
    "durationMinutes" INTEGER NOT NULL,
    "hasEssay" BOOLEAN NOT NULL DEFAULT false,
    "hasForeignLanguage" BOOLEAN NOT NULL DEFAULT false,
    "areas" "ExamArea"[],
    "structureNote" TEXT,
    "sourceId" TEXT NOT NULL,
    "reviewStatus" "ReviewStatus" NOT NULL DEFAULT 'PENDING',
    "pipelineStage" "PipelineStage" NOT NULL DEFAULT 'IMPORTED',
    "version" INTEGER NOT NULL DEFAULT 1,
    "isFreeSample" BOOLEAN NOT NULL DEFAULT false,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "Exam_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "ExamEdition" (
    "id" TEXT NOT NULL,
    "year" INTEGER NOT NULL,
    "name" TEXT NOT NULL,
    "rulesVersion" TEXT,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "ExamEdition_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "ExamBooklet" (
    "id" TEXT NOT NULL,
    "examId" TEXT NOT NULL,
    "color" "BookletColor" NOT NULL DEFAULT 'NAO_APLICAVEL',
    "label" TEXT NOT NULL,
    "pdfKey" TEXT NOT NULL,
    "pdfChecksum" TEXT NOT NULL,
    "pageCount" INTEGER NOT NULL,
    "sourceId" TEXT NOT NULL,
    "reviewStatus" "ReviewStatus" NOT NULL DEFAULT 'PENDING',
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "ExamBooklet_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "ExamPage" (
    "id" TEXT NOT NULL,
    "bookletId" TEXT NOT NULL,
    "pageNumber" INTEGER NOT NULL,
    "imageKey" TEXT,
    "checksum" TEXT,

    CONSTRAINT "ExamPage_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Question" (
    "id" TEXT NOT NULL,
    "bookletId" TEXT NOT NULL,
    "originalNumber" INTEGER NOT NULL,
    "area" "ExamArea" NOT NULL,
    "foreignLanguage" "ForeignLanguage",
    "pageNumber" INTEGER,
    "statementText" TEXT,
    "sourceType" "SourceType" NOT NULL DEFAULT 'OFFICIAL_INEP',
    "reviewStatus" "ReviewStatus" NOT NULL DEFAULT 'PENDING',
    "pipelineStage" "PipelineStage" NOT NULL DEFAULT 'IMPORTED',
    "version" INTEGER NOT NULL DEFAULT 1,
    "checksum" TEXT,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "Question_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "QuestionOption" (
    "id" TEXT NOT NULL,
    "questionId" TEXT NOT NULL,
    "letter" "AnswerOption" NOT NULL,
    "text" TEXT,

    CONSTRAINT "QuestionOption_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "OfficialAnswerSet" (
    "id" TEXT NOT NULL,
    "bookletId" TEXT NOT NULL,
    "sourceId" TEXT NOT NULL,
    "pdfKey" TEXT,
    "checksum" TEXT NOT NULL,
    "reviewStatus" "ReviewStatus" NOT NULL DEFAULT 'PENDING',
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "OfficialAnswerSet_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "OfficialAnswer" (
    "id" TEXT NOT NULL,
    "answerSetId" TEXT NOT NULL,
    "questionId" TEXT NOT NULL,
    "correct" "AnswerOption",
    "annulled" BOOLEAN NOT NULL DEFAULT false,
    "reviewStatus" "ReviewStatus" NOT NULL DEFAULT 'PENDING',

    CONSTRAINT "OfficialAnswer_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "QuestionClassification" (
    "id" TEXT NOT NULL,
    "questionId" TEXT NOT NULL,
    "discipline" TEXT,
    "topicId" TEXT,
    "competency" TEXT,
    "skill" TEXT,
    "reviewStatus" "ReviewStatus" NOT NULL DEFAULT 'PENDING',

    CONSTRAINT "QuestionClassification_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "QuestionResolution" (
    "id" TEXT NOT NULL,
    "questionId" TEXT NOT NULL,
    "body" TEXT NOT NULL,
    "sourceType" "SourceType" NOT NULL DEFAULT 'EDITORIAL',
    "authorId" TEXT,
    "reviewStatus" "ReviewStatus" NOT NULL DEFAULT 'PENDING',
    "version" INTEGER NOT NULL DEFAULT 1,

    CONSTRAINT "QuestionResolution_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "EssayPrompt" (
    "id" TEXT NOT NULL,
    "examId" TEXT NOT NULL,
    "theme" TEXT NOT NULL,
    "motivatingTexts" JSONB NOT NULL,
    "pdfKey" TEXT,
    "maxLines" INTEGER NOT NULL DEFAULT 30,
    "sourceId" TEXT NOT NULL,
    "reviewStatus" "ReviewStatus" NOT NULL DEFAULT 'PENDING',
    "version" INTEGER NOT NULL DEFAULT 1,

    CONSTRAINT "EssayPrompt_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "EssayZeroRule" (
    "id" TEXT NOT NULL,
    "editionId" TEXT NOT NULL,
    "code" TEXT NOT NULL,
    "description" TEXT NOT NULL,
    "sourceUrl" TEXT NOT NULL,
    "reviewStatus" "ReviewStatus" NOT NULL DEFAULT 'PENDING',

    CONSTRAINT "EssayZeroRule_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "OfficialDocument" (
    "id" TEXT NOT NULL,
    "sourceId" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "year" INTEGER NOT NULL,
    "kind" TEXT NOT NULL,
    "storageKey" TEXT NOT NULL,
    "version" INTEGER NOT NULL DEFAULT 1,
    "reviewStatus" "ReviewStatus" NOT NULL DEFAULT 'PENDING',

    CONSTRAINT "OfficialDocument_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "OfficialDocumentChunk" (
    "id" TEXT NOT NULL,
    "documentId" TEXT NOT NULL,
    "ordinal" INTEGER NOT NULL,
    "content" TEXT NOT NULL,
    "page" INTEGER,

    CONSTRAINT "OfficialDocumentChunk_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "ContentVersion" (
    "id" TEXT NOT NULL,
    "entityType" TEXT NOT NULL,
    "entityId" TEXT NOT NULL,
    "version" INTEGER NOT NULL,
    "previousData" JSONB,
    "newData" JSONB NOT NULL,
    "reason" TEXT NOT NULL,
    "authorId" TEXT NOT NULL,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "ContentVersion_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "ExamSession" (
    "id" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "examId" TEXT NOT NULL,
    "bookletId" TEXT NOT NULL,
    "mode" "SessionMode" NOT NULL,
    "status" "SessionStatus" NOT NULL DEFAULT 'CREATED',
    "language" "ForeignLanguage",
    "selectedAreas" "ExamArea"[],
    "startedAt" TIMESTAMP(3),
    "expectedEndAt" TIMESTAMP(3),
    "pausedAt" TIMESTAMP(3),
    "pausedSeconds" INTEGER NOT NULL DEFAULT 0,
    "finishedAt" TIMESTAMP(3),
    "timeUsedSeconds" INTEGER,
    "device" TEXT,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "ExamSession_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "AnswerSheet" (
    "id" TEXT NOT NULL,
    "sessionId" TEXT NOT NULL,
    "lockedAt" TIMESTAMP(3),
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "AnswerSheet_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Answer" (
    "id" TEXT NOT NULL,
    "answerSheetId" TEXT NOT NULL,
    "questionId" TEXT NOT NULL,
    "questionNumber" INTEGER NOT NULL,
    "option" "AnswerOption",
    "changeCount" INTEGER NOT NULL DEFAULT 0,
    "answeredAt" TIMESTAMP(3),
    "timeSpentSec" INTEGER,
    "isCorrect" BOOLEAN,

    CONSTRAINT "Answer_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "SessionNote" (
    "id" TEXT NOT NULL,
    "sessionId" TEXT NOT NULL,
    "questionId" TEXT,
    "body" TEXT NOT NULL,
    "flagged" BOOLEAN NOT NULL DEFAULT false,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "SessionNote_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "SessionResult" (
    "id" TEXT NOT NULL,
    "sessionId" TEXT NOT NULL,
    "totalQuestions" INTEGER NOT NULL,
    "correct" INTEGER NOT NULL,
    "wrong" INTEGER NOT NULL,
    "blank" INTEGER NOT NULL,
    "percent" DOUBLE PRECISION NOT NULL,
    "timeUsedSeconds" INTEGER NOT NULL,
    "avgSecondsPerQuestion" DOUBLE PRECISION NOT NULL,
    "changedAnswers" INTEGER NOT NULL,
    "byArea" JSONB NOT NULL,
    "byDiscipline" JSONB,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "SessionResult_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Essay" (
    "id" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "sessionId" TEXT,
    "promptId" TEXT NOT NULL,
    "draftText" TEXT,
    "finalText" TEXT,
    "lineCount" INTEGER,
    "status" "EssayStatus" NOT NULL DEFAULT 'DRAFT',
    "submittedAt" TIMESTAMP(3),
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "Essay_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "EssayEvaluation" (
    "id" TEXT NOT NULL,
    "essayId" TEXT NOT NULL,
    "evaluator" "EvaluatorRole" NOT NULL,
    "provider" TEXT NOT NULL,
    "model" TEXT NOT NULL,
    "zeroScore" BOOLEAN NOT NULL DEFAULT false,
    "zeroReason" TEXT,
    "total" INTEGER NOT NULL,
    "positives" JSONB NOT NULL,
    "improvements" JSONB NOT NULL,
    "rawResponse" JSONB,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "EssayEvaluation_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "EssayCompetencyScore" (
    "id" TEXT NOT NULL,
    "evaluationId" TEXT NOT NULL,
    "competency" INTEGER NOT NULL,
    "score" INTEGER NOT NULL,
    "justification" TEXT NOT NULL,
    "problematicExcerpts" JSONB NOT NULL,

    CONSTRAINT "EssayCompetencyScore_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "EssayFinalResult" (
    "id" TEXT NOT NULL,
    "essayId" TEXT NOT NULL,
    "total" INTEGER NOT NULL,
    "competencyScores" JSONB NOT NULL,
    "usedThirdEvaluator" BOOLEAN NOT NULL DEFAULT false,
    "divergence" INTEGER NOT NULL,
    "consistencyFlags" JSONB NOT NULL,
    "checklist" JSONB NOT NULL,
    "studyRecommendation" JSONB NOT NULL,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "EssayFinalResult_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "StudyTopic" (
    "id" TEXT NOT NULL,
    "area" "ExamArea" NOT NULL,
    "discipline" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "slug" TEXT NOT NULL,
    "description" TEXT,
    "recurrence" INTEGER NOT NULL DEFAULT 0,
    "matrixSkill" TEXT,
    "sourceType" "SourceType" NOT NULL DEFAULT 'EDITORIAL',
    "reviewStatus" "ReviewStatus" NOT NULL DEFAULT 'PENDING',

    CONSTRAINT "StudyTopic_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "StudyMaterial" (
    "id" TEXT NOT NULL,
    "topicId" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "body" TEXT NOT NULL,
    "sourceType" "SourceType" NOT NULL DEFAULT 'EDITORIAL',
    "reviewStatus" "ReviewStatus" NOT NULL DEFAULT 'PENDING',
    "version" INTEGER NOT NULL DEFAULT 1,

    CONSTRAINT "StudyMaterial_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "StudyPlan" (
    "id" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "kind" TEXT NOT NULL,
    "targetDate" TIMESTAMP(3),
    "weeklyHours" INTEGER NOT NULL,
    "daysLeft" INTEGER,
    "rationale" JSONB NOT NULL,
    "active" BOOLEAN NOT NULL DEFAULT true,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "StudyPlan_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "StudyTask" (
    "id" TEXT NOT NULL,
    "planId" TEXT NOT NULL,
    "topicId" TEXT,
    "scheduledOn" TIMESTAMP(3) NOT NULL,
    "kind" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "minutes" INTEGER NOT NULL,
    "status" "StudyTaskStatus" NOT NULL DEFAULT 'PENDING',
    "payload" JSONB,

    CONSTRAINT "StudyTask_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "ErrorNotebookEntry" (
    "id" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "questionId" TEXT NOT NULL,
    "note" TEXT,
    "reviewed" BOOLEAN NOT NULL DEFAULT false,
    "nextReviewAt" TIMESTAMP(3),
    "interval" INTEGER NOT NULL DEFAULT 1,
    "ease" DOUBLE PRECISION NOT NULL DEFAULT 2.5,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "ErrorNotebookEntry_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Favorite" (
    "id" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "questionId" TEXT NOT NULL,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Favorite_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Goal" (
    "id" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "kind" TEXT NOT NULL,
    "target" INTEGER NOT NULL,
    "active" BOOLEAN NOT NULL DEFAULT true,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Goal_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Achievement" (
    "id" TEXT NOT NULL,
    "code" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "description" TEXT NOT NULL,

    CONSTRAINT "Achievement_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "UserAchievement" (
    "id" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "achievementId" TEXT NOT NULL,
    "earnedAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "UserAchievement_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Plan" (
    "id" TEXT NOT NULL,
    "code" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "description" TEXT,
    "priceCents" INTEGER NOT NULL,
    "currency" TEXT NOT NULL DEFAULT 'BRL',
    "intervalMonths" INTEGER NOT NULL DEFAULT 1,
    "trialDays" INTEGER NOT NULL DEFAULT 0,
    "limits" JSONB NOT NULL,
    "benefits" JSONB NOT NULL,
    "isActive" BOOLEAN NOT NULL DEFAULT true,
    "sortOrder" INTEGER NOT NULL DEFAULT 0,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "Plan_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Subscription" (
    "id" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "planId" TEXT NOT NULL,
    "status" "SubscriptionStatus" NOT NULL DEFAULT 'TRIALING',
    "provider" TEXT NOT NULL,
    "providerRef" TEXT,
    "currentPeriodStart" TIMESTAMP(3) NOT NULL,
    "currentPeriodEnd" TIMESTAMP(3) NOT NULL,
    "cancelAtPeriodEnd" BOOLEAN NOT NULL DEFAULT false,
    "canceledAt" TIMESTAMP(3),
    "couponId" TEXT,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "Subscription_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Payment" (
    "id" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "subscriptionId" TEXT,
    "provider" TEXT NOT NULL,
    "providerRef" TEXT NOT NULL,
    "method" "PaymentMethod" NOT NULL,
    "amountCents" INTEGER NOT NULL,
    "discountCents" INTEGER NOT NULL DEFAULT 0,
    "status" "PaymentStatus" NOT NULL DEFAULT 'PENDING',
    "instrumentHash" TEXT,
    "confirmedAt" TIMESTAMP(3),
    "refundedAt" TIMESTAMP(3),
    "rawWebhook" JSONB,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "Payment_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "WebhookEvent" (
    "id" TEXT NOT NULL,
    "provider" TEXT NOT NULL,
    "eventId" TEXT NOT NULL,
    "type" TEXT NOT NULL,
    "payload" JSONB NOT NULL,
    "processedAt" TIMESTAMP(3),
    "error" TEXT,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "WebhookEvent_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Coupon" (
    "id" TEXT NOT NULL,
    "code" TEXT NOT NULL,
    "type" "CouponType" NOT NULL,
    "value" INTEGER NOT NULL,
    "months" INTEGER,
    "startsAt" TIMESTAMP(3) NOT NULL,
    "endsAt" TIMESTAMP(3),
    "maxUses" INTEGER,
    "maxUsesPerUser" INTEGER NOT NULL DEFAULT 1,
    "minAmountCents" INTEGER,
    "isActive" BOOLEAN NOT NULL DEFAULT true,
    "affiliateId" TEXT,
    "promotionId" TEXT,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Coupon_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "CouponUsage" (
    "id" TEXT NOT NULL,
    "couponId" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "CouponUsage_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Promotion" (
    "id" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "description" TEXT,
    "kind" TEXT NOT NULL,
    "startsAt" TIMESTAMP(3) NOT NULL,
    "endsAt" TIMESTAMP(3),
    "isActive" BOOLEAN NOT NULL DEFAULT true,
    "config" JSONB NOT NULL,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Promotion_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "ReferralSettings" (
    "id" TEXT NOT NULL DEFAULT 'default',
    "model" "CommissionModel" NOT NULL DEFAULT 'PERCENT',
    "value" INTEGER NOT NULL DEFAULT 20,
    "recurring" BOOLEAN NOT NULL DEFAULT false,
    "firstPaymentOnly" BOOLEAN NOT NULL DEFAULT true,
    "validationDays" INTEGER NOT NULL DEFAULT 30,
    "minWithdrawalCents" INTEGER NOT NULL DEFAULT 5000,
    "payoutMethods" JSONB NOT NULL DEFAULT '["PIX"]',
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "ReferralSettings_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "ReferralClick" (
    "id" TEXT NOT NULL,
    "referrerId" TEXT NOT NULL,
    "ipHash" TEXT,
    "userAgent" TEXT,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "ReferralClick_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Commission" (
    "id" TEXT NOT NULL,
    "affiliateId" TEXT NOT NULL,
    "referredUserId" TEXT NOT NULL,
    "subscriptionId" TEXT NOT NULL,
    "paymentId" TEXT NOT NULL,
    "amountCents" INTEGER NOT NULL,
    "status" "CommissionStatus" NOT NULL DEFAULT 'PENDING',
    "availableAt" TIMESTAMP(3),
    "fraudFlags" JSONB,
    "blockedReason" TEXT,
    "withdrawalId" TEXT,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "Commission_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Withdrawal" (
    "id" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "amountCents" INTEGER NOT NULL,
    "method" TEXT NOT NULL,
    "pixKeyHash" TEXT,
    "status" "WithdrawalStatus" NOT NULL DEFAULT 'REQUESTED',
    "paidAt" TIMESTAMP(3),
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Withdrawal_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Sponsor" (
    "id" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "seats" INTEGER NOT NULL,
    "usedSeats" INTEGER NOT NULL DEFAULT 0,
    "active" BOOLEAN NOT NULL DEFAULT true,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Sponsor_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Scholarship" (
    "id" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "sponsorId" TEXT,
    "days" INTEGER,
    "startsAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "endsAt" TIMESTAMP(3),
    "grantedBy" TEXT NOT NULL,
    "reason" TEXT,
    "active" BOOLEAN NOT NULL DEFAULT true,

    CONSTRAINT "Scholarship_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "Notification" (
    "id" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "channel" "NotificationChannel" NOT NULL,
    "kind" TEXT NOT NULL,
    "title" TEXT NOT NULL,
    "body" TEXT NOT NULL,
    "readAt" TIMESTAMP(3),
    "sentAt" TIMESTAMP(3),
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Notification_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "SupportTicket" (
    "id" TEXT NOT NULL,
    "userId" TEXT NOT NULL,
    "subject" TEXT NOT NULL,
    "body" TEXT NOT NULL,
    "status" "TicketStatus" NOT NULL DEFAULT 'OPEN',
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "SupportTicket_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "AuditLog" (
    "id" TEXT NOT NULL,
    "actorId" TEXT,
    "action" TEXT NOT NULL,
    "entityType" TEXT,
    "entityId" TEXT,
    "metadata" JSONB,
    "ip" TEXT,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "AuditLog_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "AiUsage" (
    "id" TEXT NOT NULL,
    "provider" TEXT NOT NULL,
    "model" TEXT NOT NULL,
    "purpose" TEXT NOT NULL,
    "inputTokens" INTEGER NOT NULL,
    "outputTokens" INTEGER NOT NULL,
    "costCents" INTEGER NOT NULL DEFAULT 0,
    "latencyMs" INTEGER NOT NULL,
    "success" BOOLEAN NOT NULL,
    "error" TEXT,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "AiUsage_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "SystemAlert" (
    "id" TEXT NOT NULL,
    "level" TEXT NOT NULL,
    "source" TEXT NOT NULL,
    "message" TEXT NOT NULL,
    "metadata" JSONB,
    "resolvedAt" TIMESTAMP(3),
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "SystemAlert_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "_CouponPlans" (
    "A" TEXT NOT NULL,
    "B" TEXT NOT NULL,

    CONSTRAINT "_CouponPlans_AB_pkey" PRIMARY KEY ("A","B")
);

-- CreateIndex
CREATE UNIQUE INDEX "User_email_key" ON "User"("email");

-- CreateIndex
CREATE UNIQUE INDEX "User_referralCode_key" ON "User"("referralCode");

-- CreateIndex
CREATE UNIQUE INDEX "Profile_userId_key" ON "Profile"("userId");

-- CreateIndex
CREATE UNIQUE INDEX "Profile_cpfHash_key" ON "Profile"("cpfHash");

-- CreateIndex
CREATE UNIQUE INDEX "RefreshToken_tokenHash_key" ON "RefreshToken"("tokenHash");

-- CreateIndex
CREATE INDEX "Consent_userId_type_idx" ON "Consent"("userId", "type");

-- CreateIndex
CREATE UNIQUE INDEX "Exam_editionId_application_day_key" ON "Exam"("editionId", "application", "day");

-- CreateIndex
CREATE UNIQUE INDEX "ExamEdition_year_key" ON "ExamEdition"("year");

-- CreateIndex
CREATE UNIQUE INDEX "ExamBooklet_examId_color_key" ON "ExamBooklet"("examId", "color");

-- CreateIndex
CREATE UNIQUE INDEX "ExamPage_bookletId_pageNumber_key" ON "ExamPage"("bookletId", "pageNumber");

-- CreateIndex
CREATE INDEX "Question_area_idx" ON "Question"("area");

-- CreateIndex
CREATE UNIQUE INDEX "Question_bookletId_originalNumber_key" ON "Question"("bookletId", "originalNumber");

-- CreateIndex
CREATE UNIQUE INDEX "QuestionOption_questionId_letter_key" ON "QuestionOption"("questionId", "letter");

-- CreateIndex
CREATE UNIQUE INDEX "OfficialAnswer_questionId_key" ON "OfficialAnswer"("questionId");

-- CreateIndex
CREATE UNIQUE INDEX "QuestionClassification_questionId_key" ON "QuestionClassification"("questionId");

-- CreateIndex
CREATE UNIQUE INDEX "QuestionResolution_questionId_key" ON "QuestionResolution"("questionId");

-- CreateIndex
CREATE UNIQUE INDEX "EssayPrompt_examId_key" ON "EssayPrompt"("examId");

-- CreateIndex
CREATE UNIQUE INDEX "EssayZeroRule_editionId_code_key" ON "EssayZeroRule"("editionId", "code");

-- CreateIndex
CREATE INDEX "OfficialDocumentChunk_documentId_ordinal_idx" ON "OfficialDocumentChunk"("documentId", "ordinal");

-- CreateIndex
CREATE INDEX "ContentVersion_entityType_entityId_idx" ON "ContentVersion"("entityType", "entityId");

-- CreateIndex
CREATE INDEX "ExamSession_userId_createdAt_idx" ON "ExamSession"("userId", "createdAt");

-- CreateIndex
CREATE UNIQUE INDEX "AnswerSheet_sessionId_key" ON "AnswerSheet"("sessionId");

-- CreateIndex
CREATE UNIQUE INDEX "Answer_answerSheetId_questionId_key" ON "Answer"("answerSheetId", "questionId");

-- CreateIndex
CREATE UNIQUE INDEX "SessionResult_sessionId_key" ON "SessionResult"("sessionId");

-- CreateIndex
CREATE UNIQUE INDEX "Essay_sessionId_key" ON "Essay"("sessionId");

-- CreateIndex
CREATE INDEX "EssayEvaluation_essayId_idx" ON "EssayEvaluation"("essayId");

-- CreateIndex
CREATE UNIQUE INDEX "EssayCompetencyScore_evaluationId_competency_key" ON "EssayCompetencyScore"("evaluationId", "competency");

-- CreateIndex
CREATE UNIQUE INDEX "EssayFinalResult_essayId_key" ON "EssayFinalResult"("essayId");

-- CreateIndex
CREATE UNIQUE INDEX "StudyTopic_slug_key" ON "StudyTopic"("slug");

-- CreateIndex
CREATE INDEX "StudyTask_planId_scheduledOn_idx" ON "StudyTask"("planId", "scheduledOn");

-- CreateIndex
CREATE UNIQUE INDEX "ErrorNotebookEntry_userId_questionId_key" ON "ErrorNotebookEntry"("userId", "questionId");

-- CreateIndex
CREATE UNIQUE INDEX "Favorite_userId_questionId_key" ON "Favorite"("userId", "questionId");

-- CreateIndex
CREATE UNIQUE INDEX "Achievement_code_key" ON "Achievement"("code");

-- CreateIndex
CREATE UNIQUE INDEX "UserAchievement_userId_achievementId_key" ON "UserAchievement"("userId", "achievementId");

-- CreateIndex
CREATE UNIQUE INDEX "Plan_code_key" ON "Plan"("code");

-- CreateIndex
CREATE INDEX "Subscription_userId_status_idx" ON "Subscription"("userId", "status");

-- CreateIndex
CREATE UNIQUE INDEX "Payment_providerRef_key" ON "Payment"("providerRef");

-- CreateIndex
CREATE UNIQUE INDEX "WebhookEvent_provider_eventId_key" ON "WebhookEvent"("provider", "eventId");

-- CreateIndex
CREATE UNIQUE INDEX "Coupon_code_key" ON "Coupon"("code");

-- CreateIndex
CREATE INDEX "CouponUsage_couponId_userId_idx" ON "CouponUsage"("couponId", "userId");

-- CreateIndex
CREATE INDEX "ReferralClick_referrerId_createdAt_idx" ON "ReferralClick"("referrerId", "createdAt");

-- CreateIndex
CREATE INDEX "Commission_affiliateId_status_idx" ON "Commission"("affiliateId", "status");

-- CreateIndex
CREATE UNIQUE INDEX "Commission_paymentId_affiliateId_key" ON "Commission"("paymentId", "affiliateId");

-- CreateIndex
CREATE INDEX "Scholarship_userId_active_idx" ON "Scholarship"("userId", "active");

-- CreateIndex
CREATE INDEX "Notification_userId_readAt_idx" ON "Notification"("userId", "readAt");

-- CreateIndex
CREATE INDEX "AuditLog_entityType_entityId_idx" ON "AuditLog"("entityType", "entityId");

-- CreateIndex
CREATE INDEX "AuditLog_actorId_createdAt_idx" ON "AuditLog"("actorId", "createdAt");

-- CreateIndex
CREATE INDEX "AiUsage_purpose_createdAt_idx" ON "AiUsage"("purpose", "createdAt");

-- CreateIndex
CREATE INDEX "_CouponPlans_B_index" ON "_CouponPlans"("B");

-- AddForeignKey
ALTER TABLE "User" ADD CONSTRAINT "User_referredById_fkey" FOREIGN KEY ("referredById") REFERENCES "User"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Profile" ADD CONSTRAINT "Profile_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "RefreshToken" ADD CONSTRAINT "RefreshToken_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Consent" ADD CONSTRAINT "Consent_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Exam" ADD CONSTRAINT "Exam_editionId_fkey" FOREIGN KEY ("editionId") REFERENCES "ExamEdition"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Exam" ADD CONSTRAINT "Exam_sourceId_fkey" FOREIGN KEY ("sourceId") REFERENCES "ContentSource"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "ExamBooklet" ADD CONSTRAINT "ExamBooklet_examId_fkey" FOREIGN KEY ("examId") REFERENCES "Exam"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "ExamBooklet" ADD CONSTRAINT "ExamBooklet_sourceId_fkey" FOREIGN KEY ("sourceId") REFERENCES "ContentSource"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "ExamPage" ADD CONSTRAINT "ExamPage_bookletId_fkey" FOREIGN KEY ("bookletId") REFERENCES "ExamBooklet"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Question" ADD CONSTRAINT "Question_bookletId_fkey" FOREIGN KEY ("bookletId") REFERENCES "ExamBooklet"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "QuestionOption" ADD CONSTRAINT "QuestionOption_questionId_fkey" FOREIGN KEY ("questionId") REFERENCES "Question"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "OfficialAnswerSet" ADD CONSTRAINT "OfficialAnswerSet_bookletId_fkey" FOREIGN KEY ("bookletId") REFERENCES "ExamBooklet"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "OfficialAnswerSet" ADD CONSTRAINT "OfficialAnswerSet_sourceId_fkey" FOREIGN KEY ("sourceId") REFERENCES "ContentSource"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "OfficialAnswer" ADD CONSTRAINT "OfficialAnswer_answerSetId_fkey" FOREIGN KEY ("answerSetId") REFERENCES "OfficialAnswerSet"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "OfficialAnswer" ADD CONSTRAINT "OfficialAnswer_questionId_fkey" FOREIGN KEY ("questionId") REFERENCES "Question"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "QuestionClassification" ADD CONSTRAINT "QuestionClassification_questionId_fkey" FOREIGN KEY ("questionId") REFERENCES "Question"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "QuestionClassification" ADD CONSTRAINT "QuestionClassification_topicId_fkey" FOREIGN KEY ("topicId") REFERENCES "StudyTopic"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "QuestionResolution" ADD CONSTRAINT "QuestionResolution_questionId_fkey" FOREIGN KEY ("questionId") REFERENCES "Question"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "EssayPrompt" ADD CONSTRAINT "EssayPrompt_examId_fkey" FOREIGN KEY ("examId") REFERENCES "Exam"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "EssayPrompt" ADD CONSTRAINT "EssayPrompt_sourceId_fkey" FOREIGN KEY ("sourceId") REFERENCES "ContentSource"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "EssayZeroRule" ADD CONSTRAINT "EssayZeroRule_editionId_fkey" FOREIGN KEY ("editionId") REFERENCES "ExamEdition"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "OfficialDocument" ADD CONSTRAINT "OfficialDocument_sourceId_fkey" FOREIGN KEY ("sourceId") REFERENCES "ContentSource"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "OfficialDocumentChunk" ADD CONSTRAINT "OfficialDocumentChunk_documentId_fkey" FOREIGN KEY ("documentId") REFERENCES "OfficialDocument"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "ExamSession" ADD CONSTRAINT "ExamSession_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "ExamSession" ADD CONSTRAINT "ExamSession_examId_fkey" FOREIGN KEY ("examId") REFERENCES "Exam"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "AnswerSheet" ADD CONSTRAINT "AnswerSheet_sessionId_fkey" FOREIGN KEY ("sessionId") REFERENCES "ExamSession"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Answer" ADD CONSTRAINT "Answer_answerSheetId_fkey" FOREIGN KEY ("answerSheetId") REFERENCES "AnswerSheet"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Answer" ADD CONSTRAINT "Answer_questionId_fkey" FOREIGN KEY ("questionId") REFERENCES "Question"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "SessionNote" ADD CONSTRAINT "SessionNote_sessionId_fkey" FOREIGN KEY ("sessionId") REFERENCES "ExamSession"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "SessionResult" ADD CONSTRAINT "SessionResult_sessionId_fkey" FOREIGN KEY ("sessionId") REFERENCES "ExamSession"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Essay" ADD CONSTRAINT "Essay_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Essay" ADD CONSTRAINT "Essay_sessionId_fkey" FOREIGN KEY ("sessionId") REFERENCES "ExamSession"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Essay" ADD CONSTRAINT "Essay_promptId_fkey" FOREIGN KEY ("promptId") REFERENCES "EssayPrompt"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "EssayEvaluation" ADD CONSTRAINT "EssayEvaluation_essayId_fkey" FOREIGN KEY ("essayId") REFERENCES "Essay"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "EssayCompetencyScore" ADD CONSTRAINT "EssayCompetencyScore_evaluationId_fkey" FOREIGN KEY ("evaluationId") REFERENCES "EssayEvaluation"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "EssayFinalResult" ADD CONSTRAINT "EssayFinalResult_essayId_fkey" FOREIGN KEY ("essayId") REFERENCES "Essay"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "StudyMaterial" ADD CONSTRAINT "StudyMaterial_topicId_fkey" FOREIGN KEY ("topicId") REFERENCES "StudyTopic"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "StudyPlan" ADD CONSTRAINT "StudyPlan_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "StudyTask" ADD CONSTRAINT "StudyTask_planId_fkey" FOREIGN KEY ("planId") REFERENCES "StudyPlan"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "StudyTask" ADD CONSTRAINT "StudyTask_topicId_fkey" FOREIGN KEY ("topicId") REFERENCES "StudyTopic"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "ErrorNotebookEntry" ADD CONSTRAINT "ErrorNotebookEntry_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "ErrorNotebookEntry" ADD CONSTRAINT "ErrorNotebookEntry_questionId_fkey" FOREIGN KEY ("questionId") REFERENCES "Question"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Favorite" ADD CONSTRAINT "Favorite_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Favorite" ADD CONSTRAINT "Favorite_questionId_fkey" FOREIGN KEY ("questionId") REFERENCES "Question"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Goal" ADD CONSTRAINT "Goal_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "UserAchievement" ADD CONSTRAINT "UserAchievement_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "UserAchievement" ADD CONSTRAINT "UserAchievement_achievementId_fkey" FOREIGN KEY ("achievementId") REFERENCES "Achievement"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Subscription" ADD CONSTRAINT "Subscription_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Subscription" ADD CONSTRAINT "Subscription_planId_fkey" FOREIGN KEY ("planId") REFERENCES "Plan"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Subscription" ADD CONSTRAINT "Subscription_couponId_fkey" FOREIGN KEY ("couponId") REFERENCES "Coupon"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Payment" ADD CONSTRAINT "Payment_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Payment" ADD CONSTRAINT "Payment_subscriptionId_fkey" FOREIGN KEY ("subscriptionId") REFERENCES "Subscription"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Coupon" ADD CONSTRAINT "Coupon_promotionId_fkey" FOREIGN KEY ("promotionId") REFERENCES "Promotion"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "CouponUsage" ADD CONSTRAINT "CouponUsage_couponId_fkey" FOREIGN KEY ("couponId") REFERENCES "Coupon"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "CouponUsage" ADD CONSTRAINT "CouponUsage_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "ReferralClick" ADD CONSTRAINT "ReferralClick_referrerId_fkey" FOREIGN KEY ("referrerId") REFERENCES "User"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Commission" ADD CONSTRAINT "Commission_affiliateId_fkey" FOREIGN KEY ("affiliateId") REFERENCES "User"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Commission" ADD CONSTRAINT "Commission_subscriptionId_fkey" FOREIGN KEY ("subscriptionId") REFERENCES "Subscription"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Commission" ADD CONSTRAINT "Commission_paymentId_fkey" FOREIGN KEY ("paymentId") REFERENCES "Payment"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Commission" ADD CONSTRAINT "Commission_withdrawalId_fkey" FOREIGN KEY ("withdrawalId") REFERENCES "Withdrawal"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Withdrawal" ADD CONSTRAINT "Withdrawal_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Scholarship" ADD CONSTRAINT "Scholarship_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Scholarship" ADD CONSTRAINT "Scholarship_sponsorId_fkey" FOREIGN KEY ("sponsorId") REFERENCES "Sponsor"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "Notification" ADD CONSTRAINT "Notification_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "SupportTicket" ADD CONSTRAINT "SupportTicket_userId_fkey" FOREIGN KEY ("userId") REFERENCES "User"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "AuditLog" ADD CONSTRAINT "AuditLog_actorId_fkey" FOREIGN KEY ("actorId") REFERENCES "User"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "_CouponPlans" ADD CONSTRAINT "_CouponPlans_A_fkey" FOREIGN KEY ("A") REFERENCES "Coupon"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "_CouponPlans" ADD CONSTRAINT "_CouponPlans_B_fkey" FOREIGN KEY ("B") REFERENCES "Plan"("id") ON DELETE CASCADE ON UPDATE CASCADE;

