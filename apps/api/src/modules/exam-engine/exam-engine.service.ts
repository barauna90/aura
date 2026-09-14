import { BadRequestException, ForbiddenException, Injectable, Logger, NotFoundException } from '@nestjs/common';
import { ExamSession, Prisma } from '@prisma/client';
import { DISCLAIMERS } from '@sip-enem/shared';
import { PrismaService } from '../../prisma/prisma.service';
import { AuditService } from '../audit/audit.service';
import { AccessService } from '../subscriptions/access.service';
import { OFFICIAL_VISIBLE_WHERE } from '../content/exams.service';
import { CreateSessionDto, SaveAnswersDto, NoteDto } from './exam-engine.dto';
import { computeExpectedEnd, canPause, isExpired, remainingSeconds, resume as resumeTimer, timeUsedSeconds, TimerState } from './timer.rules';
import { applicableQuestions, grade } from './grading.rules';
import { ErrorNotebookService } from '../study/error-notebook.service';

/**
 * EXAM ENGINE AGENT + ANSWER SHEET AGENT (seções 6–12, 24, 60).
 */
@Injectable()
export class ExamEngineService {
  private readonly logger = new Logger('ExamEngine');

  constructor(
    private prisma: PrismaService,
    private audit: AuditService,
    private access: AccessService,
    private errorNotebook: ErrorNotebookService,
  ) {}

  // ------------------------------------------------------------------
  //  Criação e início
  // ------------------------------------------------------------------

  async create(userId: string, dto: CreateSessionDto) {
    const exam = await this.prisma.exam.findFirst({
      where: { id: dto.examId, ...OFFICIAL_VISIBLE_WHERE },
      include: { booklets: { where: { id: dto.bookletId, reviewStatus: 'VERIFIED' }, include: { questions: { where: { reviewStatus: 'VERIFIED' } } } } },
    });
    if (!exam) throw new NotFoundException('Prova oficial não disponível');
    const booklet = exam.booklets[0];
    if (!booklet) throw new NotFoundException('Caderno não disponível');

    if (exam.hasForeignLanguage && !dto.language) {
      throw new BadRequestException({ code: 'LANGUAGE_REQUIRED', message: 'Escolha a língua estrangeira: INGLÊS ou ESPANHOL.' });
    }
    if (dto.mode === 'PROVA_REAL' && dto.selectedAreas?.length) {
      throw new BadRequestException('O Modo Prova Real não permite selecionar áreas específicas.');
    }
    if (dto.mode === 'PROVA_REAL') await this.access.assertCanStartFullExam(userId, exam);

    const questions = applicableQuestions(booklet.questions, dto.language ?? null, dto.selectedAreas ?? []);
    if (!questions.length) throw new BadRequestException('Nenhuma questão aplicável para a seleção feita.');

    const session = await this.prisma.examSession.create({
      data: {
        userId,
        examId: exam.id,
        bookletId: booklet.id,
        mode: dto.mode,
        language: dto.language ?? null,
        selectedAreas: dto.selectedAreas ?? [],
        device: dto.device,
        answerSheet: {
          create: {
            answers: {
              create: questions.map((q) => ({ questionId: q.id, questionNumber: q.originalNumber })),
            },
          },
        },
      },
    });
    await this.audit.log({ actorId: userId, action: 'session.created', entityType: 'ExamSession', entityId: session.id, metadata: { mode: dto.mode, examId: exam.id } });
    return this.state(userId, session.id);
  }

  async start(userId: string, sessionId: string) {
    const session = await this.own(userId, sessionId);
    if (session.status !== 'CREATED') return this.state(userId, sessionId);
    const now = new Date();
    await this.prisma.examSession.update({
      where: { id: sessionId },
      data: { status: 'IN_PROGRESS', startedAt: now, expectedEndAt: computeExpectedEnd(now, session.exam.durationMinutes) },
    });
    await this.audit.log({ actorId: userId, action: 'session.started', entityType: 'ExamSession', entityId: sessionId });
    return this.state(userId, sessionId);
  }

  // ------------------------------------------------------------------
  //  Estado (cronômetro do servidor) — chamado pelo cliente periodicamente
  // ------------------------------------------------------------------

  async state(userId: string, sessionId: string) {
    let session = await this.own(userId, sessionId);
    if (isExpired(this.timer(session))) {
      session = await this.finalize(session, 'EXPIRED');
    }
    const sheet = await this.prisma.answerSheet.findUnique({
      where: { sessionId },
      include: { answers: { orderBy: { questionNumber: 'asc' }, select: { questionId: true, questionNumber: true, option: true, changeCount: true } } },
    });
    const answered = sheet?.answers.filter((a) => a.option !== null).length ?? 0;
    const total = sheet?.answers.length ?? 0;
    const finished = session.status === 'FINISHED' || session.status === 'EXPIRED';

    return {
      id: session.id,
      mode: session.mode,
      status: session.status,
      language: session.language,
      selectedAreas: session.selectedAreas,
      exam: {
        id: session.exam.id,
        title: session.exam.title,
        year: session.exam.edition.year,
        durationMinutes: session.exam.durationMinutes,
        hasEssay: session.exam.hasEssay,
        areas: session.exam.areas,
      },
      booklet: { id: session.bookletId },
      startedAt: session.startedAt,
      expectedEndAt: session.expectedEndAt,
      finishedAt: session.finishedAt,
      remainingSeconds: remainingSeconds(this.timer(session)),
      serverTime: new Date(),
      // Antes do encerramento NUNCA se informa acerto/erro — só o cartão-resposta.
      answerSheet: { answered, blank: total - answered, total, answers: sheet?.answers ?? [] },
      hasResult: finished,
      notices: {
        answerSheetOnly: DISCLAIMERS.ANSWER_SHEET_ONLY,
        studyMode: session.mode === 'ESTUDO' ? DISCLAIMERS.STUDY_MODE_NOTICE : null,
      },
    };
  }

  // ------------------------------------------------------------------
  //  Cartão-resposta (autosave)
  // ------------------------------------------------------------------

  async saveAnswers(userId: string, sessionId: string, dto: SaveAnswersDto) {
    const session = await this.own(userId, sessionId);
    if (session.status === 'CREATED') throw new BadRequestException('Inicie a prova antes de marcar o cartão-resposta.');
    if (session.status === 'PAUSED') throw new BadRequestException('Sessão pausada.');
    if (session.status !== 'IN_PROGRESS') throw new ForbiddenException('Cartão-resposta bloqueado: a prova já foi encerrada.');
    if (isExpired(this.timer(session))) {
      await this.finalize(session, 'EXPIRED');
      throw new ForbiddenException('Tempo esgotado. A prova foi encerrada automaticamente.');
    }
    const sheet = await this.prisma.answerSheet.findUniqueOrThrow({ where: { sessionId }, include: { answers: true } });
    const byQuestion = new Map(sheet.answers.map((a) => [a.questionId, a]));

    await this.prisma.$transaction(
      dto.answers
        .filter((entry) => byQuestion.has(entry.questionId))
        .map((entry) => {
          const current = byQuestion.get(entry.questionId)!;
          const changed = current.option !== entry.option;
          return this.prisma.answer.update({
            where: { id: current.id },
            data: {
              option: entry.option,
              changeCount: changed ? { increment: 1 } : undefined,
              answeredAt: entry.option ? new Date() : null,
              timeSpentSec: entry.timeSpentSec ?? current.timeSpentSec,
            },
          });
        }),
    );
    return { saved: dto.answers.length, serverTime: new Date(), remainingSeconds: remainingSeconds(this.timer(session)) };
  }

  // ------------------------------------------------------------------
  //  Pausa (somente MODO ESTUDO)
  // ------------------------------------------------------------------

  async pause(userId: string, sessionId: string) {
    const session = await this.own(userId, sessionId);
    const check = canPause(this.timer(session));
    if (!check.ok) throw new ForbiddenException(check.reason);
    await this.prisma.examSession.update({ where: { id: sessionId }, data: { status: 'PAUSED', pausedAt: new Date() } });
    return this.state(userId, sessionId);
  }

  async resume(userId: string, sessionId: string) {
    const session = await this.own(userId, sessionId);
    if (session.status !== 'PAUSED') throw new BadRequestException('Sessão não está pausada');
    const r = resumeTimer(this.timer(session));
    await this.prisma.examSession.update({
      where: { id: sessionId },
      data: { status: 'IN_PROGRESS', pausedAt: null, expectedEndAt: r.expectedEndAt, pausedSeconds: r.pausedSeconds },
    });
    return this.state(userId, sessionId);
  }

  // ------------------------------------------------------------------
  //  Encerramento e correção
  // ------------------------------------------------------------------

  async finish(userId: string, sessionId: string) {
    const session = await this.own(userId, sessionId);
    if (session.status === 'FINISHED' || session.status === 'EXPIRED') return this.result(userId, sessionId);
    if (session.status === 'CREATED') throw new BadRequestException('A prova não foi iniciada');
    await this.finalize(session, 'FINISHED');
    return this.result(userId, sessionId);
  }

  private async finalize(session: SessionWithExam, status: 'FINISHED' | 'EXPIRED') {
    const now = new Date();
    const timer = this.timer(session);
    // Se expirou, o encerramento efetivo é o horário previsto — não "agora".
    const finishedAt = status === 'EXPIRED' && session.expectedEndAt ? session.expectedEndAt : now;
    const used = timeUsedSeconds(timer, finishedAt);

    const sheet = await this.prisma.answerSheet.findUniqueOrThrow({
      where: { sessionId: session.id },
      include: { answers: true },
    });
    const questions = await this.prisma.question.findMany({
      where: { id: { in: sheet.answers.map((a) => a.questionId) } },
      include: { officialAnswer: true, classification: { where: { reviewStatus: 'VERIFIED' } } },
    });

    const result = grade(
      questions.map((q) => ({
        questionId: q.id,
        originalNumber: q.originalNumber,
        area: q.area,
        foreignLanguage: q.foreignLanguage,
        official: q.officialAnswer && q.officialAnswer.reviewStatus === 'VERIFIED'
          ? { correct: q.officialAnswer.correct, annulled: q.officialAnswer.annulled }
          : null,
        discipline: q.classification?.discipline ?? null,
      })),
      sheet.answers.map((a) => ({ questionId: a.questionId, option: a.option, changeCount: a.changeCount })),
      session.language,
      session.selectedAreas,
    );

    const updated = await this.prisma.$transaction(async (tx) => {
      const s = await tx.examSession.update({
        where: { id: session.id },
        data: { status, finishedAt, timeUsedSeconds: used },
        include: { exam: { include: { edition: true } } },
      });
      await tx.answerSheet.update({ where: { id: sheet.id }, data: { lockedAt: now } });
      for (const g of result.questions) {
        await tx.answer.updateMany({
          where: { answerSheetId: sheet.id, questionId: g.questionId },
          data: { isCorrect: g.status === 'CORRECT' ? true : g.status === 'WRONG' ? false : null },
        });
      }
      await tx.sessionResult.create({
        data: {
          sessionId: session.id,
          totalQuestions: result.totalQuestions,
          correct: result.correct,
          wrong: result.wrong,
          blank: result.blank,
          percent: result.percent,
          timeUsedSeconds: used,
          avgSecondsPerQuestion: result.totalQuestions ? Math.round((used / result.totalQuestions) * 10) / 10 : 0,
          changedAnswers: result.changedAnswers,
          byArea: result.byArea as unknown as Prisma.InputJsonValue,
          byDiscipline: result.byDiscipline as unknown as Prisma.InputJsonValue,
        },
      });
      await tx.auditLog.create({ data: { actorId: session.userId, action: `session.${status.toLowerCase()}`, entityType: 'ExamSession', entityId: session.id } });
      return s;
    });

    // Caderno de erros (seção 21) — fora da transação, não bloqueia o encerramento.
    const wrong = result.questions.filter((g) => g.status === 'WRONG').map((g) => g.questionId);
    this.errorNotebook.addMany(session.userId, wrong).catch((e) => this.logger.error(e));

    return updated;
  }

  // ------------------------------------------------------------------
  //  Resultado e relatório de questões (seções 10, 12)
  // ------------------------------------------------------------------

  async result(userId: string, sessionId: string) {
    const session = await this.own(userId, sessionId);
    if (session.status !== 'FINISHED' && session.status !== 'EXPIRED') {
      throw new ForbiddenException('O resultado só é exibido após o encerramento da prova.');
    }
    const [result, sheet, essay] = await Promise.all([
      this.prisma.sessionResult.findUniqueOrThrow({ where: { sessionId } }),
      this.prisma.answerSheet.findUniqueOrThrow({
        where: { sessionId },
        include: {
          answers: {
            orderBy: { questionNumber: 'asc' },
            include: {
              question: {
                include: {
                  officialAnswer: true,
                  classification: { include: { topic: true } },
                  resolution: true,
                },
              },
            },
          },
        },
      }),
      this.prisma.essay.findUnique({ where: { sessionId }, include: { finalResult: true } }),
    ]);

    const questions = sheet.answers.map((a) => {
      const q = a.question;
      const official = q.officialAnswer?.reviewStatus === 'VERIFIED' ? q.officialAnswer : null;
      const annulled = official?.annulled ?? false;
      const status = annulled ? 'ANNULLED' : a.option === null ? 'BLANK' : a.isCorrect ? 'CORRECT' : 'WRONG';
      const classification = q.classification?.reviewStatus === 'VERIFIED' ? q.classification : null;
      return {
        questionId: q.id,
        number: q.originalNumber,
        area: q.area,
        page: q.pageNumber,
        marked: a.option,
        official: official?.correct ?? null,
        annulled,
        status,
        changeCount: a.changeCount,
        timeSpentSec: a.timeSpentSec,
        topic: classification?.topic?.name ?? null,
        discipline: classification?.discipline ?? null,
        skill: classification?.skill ?? null,
        competency: classification?.competency ?? null,
        resolution:
          q.resolution?.reviewStatus === 'VERIFIED' ? { body: q.resolution.body, sourceType: q.resolution.sourceType } : null,
        resolutionNotice: q.resolution?.reviewStatus === 'VERIFIED' ? null : DISCLAIMERS.NO_RESOLUTION,
      };
    });

    return {
      session: {
        id: session.id,
        mode: session.mode,
        status: session.status,
        language: session.language,
        startedAt: session.startedAt,
        finishedAt: session.finishedAt,
        device: session.device,
        exam: { id: session.exam.id, title: session.exam.title, year: session.exam.edition.year, durationMinutes: session.exam.durationMinutes, hasEssay: session.exam.hasEssay },
      },
      objective: {
        ...result,
        // Acertos pelo gabarito oficial. NÃO é nota ENEM.
        label: 'Acertos oficiais pelo gabarito',
        scoreEstimateNotice: DISCLAIMERS.SCORE_ESTIMATE,
      },
      essay: essay
        ? { id: essay.id, status: essay.status, simulatedScore: essay.finalResult?.total ?? null, label: DISCLAIMERS.ESSAY_SCORE_LABEL }
        : null,
      questions,
      notices: { independence: DISCLAIMERS.INDEPENDENCE },
    };
  }

  async history(userId: string) {
    const sessions = await this.prisma.examSession.findMany({
      where: { userId },
      include: { exam: { include: { edition: true } }, result: true, essay: { include: { finalResult: true } } },
      orderBy: { createdAt: 'desc' },
    });
    return sessions.map((s) => ({
      id: s.id,
      exam: { id: s.exam.id, title: s.exam.title, year: s.exam.edition.year },
      mode: s.mode,
      status: s.status,
      language: s.language,
      device: s.device,
      startedAt: s.startedAt,
      finishedAt: s.finishedAt,
      timeUsedSeconds: s.timeUsedSeconds,
      result: s.result ? { correct: s.result.correct, wrong: s.result.wrong, blank: s.result.blank, total: s.result.totalQuestions, percent: s.result.percent, byArea: s.result.byArea } : null,
      essay: s.essay ? { status: s.essay.status, simulatedScore: s.essay.finalResult?.total ?? null } : null,
    }));
  }

  /** Comparação entre duas realizações (seção 56). */
  async compare(userId: string, aId: string, bId: string) {
    const [a, b] = await Promise.all([this.result(userId, aId), this.result(userId, bId)]);
    const rowsA = a.objective.byArea as unknown as AreaRow[];
    const rowsB = b.objective.byArea as unknown as AreaRow[];
    const areas = new Set([...rowsA, ...rowsB].map((x) => x.area));
    const rows = [...areas].map((area) => {
      const pa = rowsA.find((x) => x.area === area);
      const pb = rowsB.find((x) => x.area === area);
      return { area, before: pa?.correct ?? null, after: pb?.correct ?? null, delta: pa && pb ? pb.correct - pa.correct : null };
    });
    rows.push({
      area: 'REDACAO' as never,
      before: a.essay?.simulatedScore ?? null,
      after: b.essay?.simulatedScore ?? null,
      delta: a.essay?.simulatedScore != null && b.essay?.simulatedScore != null ? b.essay.simulatedScore - a.essay.simulatedScore : null,
    });
    return { before: a.session, after: b.session, rows };
  }

  async addNote(userId: string, sessionId: string, dto: NoteDto) {
    const session = await this.own(userId, sessionId);
    if (session.mode === 'PROVA_REAL' && session.status === 'IN_PROGRESS') {
      throw new ForbiddenException('Anotações e marcações não estão disponíveis no Modo Prova Real.');
    }
    return this.prisma.sessionNote.create({ data: { sessionId, questionId: dto.questionId, body: dto.body, flagged: dto.flagged ?? false } });
  }

  /** Cron: encerra sessões expiradas mesmo sem o cliente chamar /state. */
  async expireStale() {
    const stale = await this.prisma.examSession.findMany({
      where: { status: 'IN_PROGRESS', expectedEndAt: { lt: new Date() } },
      include: { exam: { include: { edition: true } } },
    });
    for (const s of stale) await this.finalize(s, 'EXPIRED');
    return stale.length;
  }

  // ------------------------------------------------------------------

  private async own(userId: string, sessionId: string): Promise<SessionWithExam> {
    const s = await this.prisma.examSession.findUnique({ where: { id: sessionId }, include: { exam: { include: { edition: true } } } });
    if (!s || s.userId !== userId) throw new NotFoundException('Sessão não encontrada');
    return s;
  }

  private timer(s: SessionWithExam): TimerState {
    return {
      mode: s.mode,
      status: s.status,
      durationMinutes: s.exam.durationMinutes,
      startedAt: s.startedAt,
      expectedEndAt: s.expectedEndAt,
      pausedAt: s.pausedAt,
      pausedSeconds: s.pausedSeconds,
    };
  }
}

type SessionWithExam = ExamSession & { exam: { id: string; title: string; durationMinutes: number; hasEssay: boolean; areas: string[]; edition: { year: number } } };
interface AreaRow {
  area: string;
  correct: number;
}
