import { BadRequestException, ForbiddenException, Injectable, Logger, NotFoundException } from '@nestjs/common';
import { PipelineStage, Prisma, ReviewStatus } from '@prisma/client';
import { PrismaService } from '../../prisma/prisma.service';
import { AuditService } from '../audit/audit.service';
import { StorageService } from './storage.service';
import {
  answerKeyChecksum,
  canAdvance,
  sha256,
  validateExamForPublication,
  GuardianViolation,
} from './guardian.rules';

/**
 * ENEM OFFICIAL CONTENT GUARDIAN
 *
 * Único caminho autorizado para:
 *  - alterar conteúdo oficial (sempre com versão + motivo + autor);
 *  - avançar o fluxo de auditoria IMPORTADO → ... → PUBLICADO;
 *  - verificar integridade entre o banco e o documento oficial.
 *
 * Nenhum outro serviço (muito menos a IA) escreve em Question/OfficialAnswer/Exam.
 */
@Injectable()
export class OfficialContentGuardianService {
  private readonly logger = new Logger('OfficialContentGuardian');

  constructor(
    private prisma: PrismaService,
    private audit: AuditService,
    private storage: StorageService,
  ) {}

  // ------------------------------------------------------------------
  //  Alteração versionada (seções 40 e 41)
  // ------------------------------------------------------------------

  async changeOfficialAnswer(
    questionId: string,
    data: { correct: 'A' | 'B' | 'C' | 'D' | 'E' | null; annulled: boolean },
    reason: string,
    actorId: string,
  ) {
    if (!reason?.trim()) throw new BadRequestException('Motivo obrigatório para alterar conteúdo oficial');
    const current = await this.prisma.officialAnswer.findUnique({ where: { questionId } });
    if (!current) throw new NotFoundException('Gabarito não encontrado');

    return this.prisma.$transaction(async (tx) => {
      const question = await tx.question.update({
        where: { id: questionId },
        data: { version: { increment: 1 }, reviewStatus: 'PENDING', pipelineStage: 'IMPORTED' },
      });
      const updated = await tx.officialAnswer.update({
        where: { questionId },
        data: { correct: data.correct, annulled: data.annulled, reviewStatus: 'PENDING' },
      });
      await tx.contentVersion.create({
        data: {
          entityType: 'OfficialAnswer',
          entityId: current.id,
          version: question.version,
          previousData: { correct: current.correct, annulled: current.annulled } as Prisma.InputJsonValue,
          newData: { correct: data.correct, annulled: data.annulled },
          reason,
          authorId: actorId,
        },
      });
      // A prova volta a PENDING — só reaparece como oficial após nova auditoria.
      await this.invalidateExamOfQuestion(tx, questionId);
      await tx.auditLog.create({
        data: { actorId, action: 'content.official_answer.changed', entityType: 'OfficialAnswer', entityId: current.id, metadata: { reason } },
      });
      return updated;
    });
  }

  async changeExamMetadata(
    examId: string,
    data: { durationMinutes?: number; title?: string; structureNote?: string },
    reason: string,
    actorId: string,
  ) {
    if (!reason?.trim()) throw new BadRequestException('Motivo obrigatório');
    const current = await this.prisma.exam.findUnique({ where: { id: examId } });
    if (!current) throw new NotFoundException('Prova não encontrada');
    return this.prisma.$transaction(async (tx) => {
      const updated = await tx.exam.update({
        where: { id: examId },
        data: { ...data, version: { increment: 1 }, reviewStatus: 'PENDING', pipelineStage: 'IMPORTED' },
      });
      await tx.contentVersion.create({
        data: {
          entityType: 'Exam',
          entityId: examId,
          version: updated.version,
          previousData: { durationMinutes: current.durationMinutes, title: current.title, structureNote: current.structureNote },
          newData: data as Prisma.InputJsonValue,
          reason,
          authorId: actorId,
        },
      });
      await tx.auditLog.create({
        data: { actorId, action: 'content.exam.changed', entityType: 'Exam', entityId: examId, metadata: { reason, data } as Prisma.InputJsonValue },
      });
      return updated;
    });
  }

  // ------------------------------------------------------------------
  //  Fluxo de auditoria
  // ------------------------------------------------------------------

  async advanceExam(examId: string, target: PipelineStage, actorId: string) {
    const exam = await this.prisma.exam.findUnique({ where: { id: examId } });
    if (!exam) throw new NotFoundException('Prova não encontrada');

    const reviewers = await this.reviewersOf('Exam', examId);
    const violation = canAdvance(exam.pipelineStage, target, reviewers, actorId);
    if (violation) throw new ForbiddenException(violation.message);

    if (target === 'AUTO_VALIDATED') {
      const problems = await this.autoValidate(examId);
      if (problems.length) {
        await this.audit.alert('WARN', 'guardian', `Validação automática falhou para prova ${examId}`, { problems } as unknown as Prisma.InputJsonValue);
        throw new BadRequestException({ message: 'Validação automática falhou', problems });
      }
    }

    if (target === 'PUBLISHED') {
      const problems = await this.autoValidate(examId);
      const integrity = await this.verifyIntegrity(examId);
      const all = [...problems, ...integrity];
      if (all.length) {
        throw new BadRequestException({ message: 'Publicação bloqueada pelo guardião', problems: all });
      }
    }

    const reviewStatus: ReviewStatus =
      target === 'PUBLISHED' ? 'VERIFIED' : target === 'IMPORTED' ? 'PENDING' : 'VALIDATING';

    await this.prisma.$transaction(async (tx) => {
      await tx.exam.update({ where: { id: examId }, data: { pipelineStage: target, reviewStatus } });
      if (target === 'PUBLISHED') {
        // Propaga VERIFIED para cadernos, questões, gabaritos e fontes desta prova.
        const booklets = await tx.examBooklet.findMany({ where: { examId }, select: { id: true, sourceId: true } });
        const bookletIds = booklets.map((b) => b.id);
        await tx.examBooklet.updateMany({ where: { id: { in: bookletIds } }, data: { reviewStatus: 'VERIFIED' } });
        await tx.question.updateMany({ where: { bookletId: { in: bookletIds } }, data: { reviewStatus: 'VERIFIED', pipelineStage: 'PUBLISHED' } });
        await tx.officialAnswerSet.updateMany({ where: { bookletId: { in: bookletIds } }, data: { reviewStatus: 'VERIFIED' } });
        await tx.officialAnswer.updateMany({ where: { question: { bookletId: { in: bookletIds } } }, data: { reviewStatus: 'VERIFIED' } });
        await tx.contentSource.updateMany({
          where: { id: { in: [exam.sourceId, ...booklets.map((b) => b.sourceId)] } },
          data: { reviewStatus: 'VERIFIED', lastValidation: new Date() },
        });
      }
      await tx.auditLog.create({
        data: { actorId, action: `content.exam.stage.${target}`, entityType: 'Exam', entityId: examId },
      });
    });
    return this.prisma.exam.findUnique({ where: { id: examId } });
  }

  async rejectExam(examId: string, reason: string, actorId: string) {
    if (!reason?.trim()) throw new BadRequestException('Motivo obrigatório');
    await this.prisma.exam.update({ where: { id: examId }, data: { reviewStatus: 'REJECTED' } });
    await this.audit.log({ actorId, action: 'content.exam.rejected', entityType: 'Exam', entityId: examId, metadata: { reason } });
  }

  // ------------------------------------------------------------------
  //  Validações
  // ------------------------------------------------------------------

  /** Validação automática estrutural (não depende de arquivos). */
  async autoValidate(examId: string): Promise<GuardianViolation[]> {
    const exam = await this.prisma.exam.findUnique({
      where: { id: examId },
      include: {
        source: true,
        booklets: {
          include: {
            questions: { include: { officialAnswer: true } },
            answerSets: true,
          },
        },
      },
    });
    if (!exam) return [{ code: 'NOT_FOUND', message: 'Prova não encontrada' }];

    // Durante a validação tratamos status PENDING/VALIDATING como aceitáveis;
    // o que importa aqui é estrutura + proveniência. Status final é dado na publicação.
    const snapshot = {
      durationMinutes: exam.durationMinutes,
      source: { ...exam.source, reviewStatus: 'VERIFIED' as ReviewStatus },
      booklets: exam.booklets.map((b) => ({
        id: b.id,
        reviewStatus: 'VERIFIED' as ReviewStatus,
        pdfChecksum: b.pdfChecksum,
        pageCount: b.pageCount,
        questions: b.questions.map((q) => ({
          originalNumber: q.originalNumber,
          reviewStatus: 'VERIFIED' as ReviewStatus,
          sourceType: q.sourceType,
          officialAnswer: q.officialAnswer
            ? { correct: q.officialAnswer.correct, annulled: q.officialAnswer.annulled, reviewStatus: 'VERIFIED' as ReviewStatus }
            : null,
        })),
        answerSets: b.answerSets.map((s) => ({ reviewStatus: 'VERIFIED' as ReviewStatus, checksum: s.checksum })),
      })),
    };
    return validateExamForPublication(snapshot);
  }

  /**
   * REGRA DE OURO DE QA (seção 65): compara o conteúdo cadastrado com o
   * documento oficial armazenado. Qualquer divergência bloqueia publicação.
   */
  async verifyIntegrity(examId: string): Promise<GuardianViolation[]> {
    const problems: GuardianViolation[] = [];
    const booklets = await this.prisma.examBooklet.findMany({
      where: { examId },
      include: { questions: { include: { officialAnswer: true } }, answerSets: true },
    });

    for (const booklet of booklets) {
      // 1) O PDF armazenado bate com o checksum registrado na importação?
      if (!(await this.storage.exists(booklet.pdfKey))) {
        problems.push({ code: 'PDF_MISSING', message: `PDF do caderno ${booklet.label} não encontrado no storage.` });
      } else {
        const buf = await this.storage.get(booklet.pdfKey);
        if (sha256(buf) !== booklet.pdfChecksum) {
          problems.push({ code: 'PDF_CHECKSUM_MISMATCH', message: `PDF do caderno ${booklet.label} foi alterado após a importação.` });
        }
      }
      // 2) O gabarito por questão bate com o checksum do gabarito importado?
      const computed = answerKeyChecksum(
        booklet.questions.map((q) => ({
          number: q.originalNumber,
          correct: q.officialAnswer?.correct ?? null,
          annulled: q.officialAnswer?.annulled ?? false,
        })),
      );
      const verifiedSet = booklet.answerSets.find((s) => s.checksum === computed);
      if (!verifiedSet) {
        problems.push({
          code: 'ANSWER_KEY_MISMATCH',
          message: `Gabarito do caderno ${booklet.label} diverge do gabarito oficial importado.`,
        });
      }
    }
    if (problems.length) {
      await this.audit.alert('ERROR', 'guardian.integrity', `Divergência de integridade na prova ${examId}`, { problems } as unknown as Prisma.InputJsonValue);
    }
    return problems;
  }

  // ------------------------------------------------------------------

  private async reviewersOf(entityType: string, entityId: string) {
    const logs = await this.prisma.auditLog.findMany({
      where: { entityType, entityId, action: { in: ['content.exam.stage.HUMAN_REVIEW_1', 'content.exam.stage.HUMAN_REVIEW_2'] } },
      orderBy: { createdAt: 'desc' },
    });
    return {
      review1: logs.find((l) => l.action.endsWith('HUMAN_REVIEW_1'))?.actorId ?? null,
      review2: logs.find((l) => l.action.endsWith('HUMAN_REVIEW_2'))?.actorId ?? null,
    };
  }

  private async invalidateExamOfQuestion(tx: Prisma.TransactionClient, questionId: string) {
    const q = await tx.question.findUniqueOrThrow({ where: { id: questionId }, include: { booklet: true } });
    await tx.exam.update({
      where: { id: q.booklet.examId },
      data: { reviewStatus: 'PENDING', pipelineStage: 'IMPORTED' },
    });
    this.logger.warn(`Prova ${q.booklet.examId} voltou para PENDING após alteração na questão ${q.originalNumber}`);
  }
}
