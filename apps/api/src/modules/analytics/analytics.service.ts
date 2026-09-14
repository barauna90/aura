import { Injectable } from '@nestjs/common';
import { ExamArea } from '@prisma/client';
import { DISCLAIMERS, EXAM_AREAS } from '@sip-enem/shared';
import { PrismaService } from '../../prisma/prisma.service';

export type Period = '7D' | '30D' | '90D' | 'ALL';

interface AreaRow {
  area: string;
  total: number;
  correct: number;
  wrong: number;
  blank: number;
}

/**
 * ANALYTICS AGENT (seções 22, 23, 25). Tudo aqui deriva de resultados reais;
 * nenhum número é "suavizado" para motivar artificialmente.
 */
@Injectable()
export class AnalyticsService {
  constructor(private prisma: PrismaService) {}

  private since(period: Period): Date | undefined {
    const days = { '7D': 7, '30D': 30, '90D': 90, ALL: 0 }[period];
    return days ? new Date(Date.now() - days * 86_400_000) : undefined;
  }

  async overview(userId: string, period: Period = '30D') {
    const since = this.since(period);
    const sessions = await this.prisma.examSession.findMany({
      where: { userId, status: { in: ['FINISHED', 'EXPIRED'] }, ...(since ? { finishedAt: { gte: since } } : {}) },
      include: { result: true, answerSheet: { include: { answers: { select: { timeSpentSec: true, isCorrect: true, option: true } } } } },
      orderBy: { finishedAt: 'asc' },
    });
    const essays = await this.prisma.essay.findMany({
      where: { userId, status: 'EVALUATED', ...(since ? { submittedAt: { gte: since } } : {}) },
      include: { finalResult: true },
      orderBy: { submittedAt: 'asc' },
    });

    // Por área (agregado)
    const agg = new Map<string, { total: number; correct: number; blank: number; seconds: number; count: number }>();
    for (const s of sessions) {
      const rows = (s.result?.byArea ?? []) as unknown as AreaRow[];
      for (const r of rows) {
        const e = agg.get(r.area) ?? { total: 0, correct: 0, blank: 0, seconds: 0, count: 0 };
        e.total += r.total;
        e.correct += r.correct;
        e.blank += r.blank;
        agg.set(r.area, e);
      }
    }
    const byArea = EXAM_AREAS.filter((a) => a !== 'REDACAO').map((area) => {
      const e = agg.get(area);
      return {
        area,
        total: e?.total ?? 0,
        correct: e?.correct ?? 0,
        percent: e && e.total ? Math.round((e.correct / e.total) * 1000) / 10 : null,
        blankRate: e && e.total ? e.blank / e.total : 0,
        avgSecondsPerQuestion: null as number | null,
      };
    });

    const totalQuestions = sessions.reduce((a, s) => a + (s.result?.totalQuestions ?? 0), 0);
    const totalCorrect = sessions.reduce((a, s) => a + (s.result?.correct ?? 0), 0);
    const totalSeconds = sessions.reduce((a, s) => a + (s.timeUsedSeconds ?? 0), 0);
    const allAnswers = sessions.flatMap((s) => s.answerSheet?.answers ?? []);
    const timed = allAnswers.filter((a) => a.timeSpentSec != null);

    // Evolução (série temporal por sessão)
    const series = sessions.map((s) => ({
      date: s.finishedAt,
      sessionId: s.id,
      percent: s.result?.percent ?? 0,
      byArea: ((s.result?.byArea ?? []) as unknown as AreaRow[]).map((r) => ({ area: r.area, percent: r.total ? Math.round((r.correct / r.total) * 1000) / 10 : 0 })),
    }));
    const essaySeries = essays.map((e) => ({ date: e.submittedAt, essayId: e.id, total: e.finalResult?.total ?? 0 }));

    // Competências mais fracas em redação
    const compSums = new Map<number, { sum: number; n: number }>();
    for (const e of essays) {
      const scores = (e.finalResult?.competencyScores ?? {}) as Record<string, number>;
      for (const [k, v] of Object.entries(scores)) {
        const c = compSums.get(Number(k)) ?? { sum: 0, n: 0 };
        c.sum += v;
        c.n++;
        compSums.set(Number(k), c);
      }
    }
    const weakCompetencies = [...compSums.entries()]
      .map(([c, v]) => ({ c, avg: v.sum / v.n }))
      .sort((a, b) => a.avg - b.avg)
      .map((x) => x.c);

    const strongest = [...byArea].filter((a) => a.percent !== null).sort((a, b) => b.percent! - a.percent!)[0]?.area ?? null;
    const weakest = [...byArea].filter((a) => a.percent !== null).sort((a, b) => a.percent! - b.percent!)[0]?.area ?? null;

    const studyDays = new Set(sessions.map((s) => s.finishedAt?.toISOString().slice(0, 10))).size;

    return {
      period,
      examsCompleted: sessions.length,
      fullExams: sessions.filter((s) => s.mode === 'PROVA_REAL').length,
      partialExams: sessions.filter((s) => s.mode === 'ESTUDO').length,
      questionsAnswered: totalQuestions,
      accuracy: totalQuestions ? Math.round((totalCorrect / totalQuestions) * 1000) / 10 : null,
      blankRate: totalQuestions ? Math.round((sessions.reduce((a, s) => a + (s.result?.blank ?? 0), 0) / totalQuestions) * 1000) / 10 : null,
      changedAnswers: sessions.reduce((a, s) => a + (s.result?.changedAnswers ?? 0), 0),
      hoursStudied: Math.round((totalSeconds / 3600) * 10) / 10,
      studyDays,
      avgSecondsPerQuestion: timed.length ? Math.round(timed.reduce((a, t) => a + (t.timeSpentSec ?? 0), 0) / timed.length) : null,
      byArea,
      strongestArea: strongest,
      weakestArea: weakest,
      series,
      movingAverage: movingAverage(series.map((s) => s.percent), 3),
      essay: {
        count: essays.length,
        average: essays.length ? Math.round(essays.reduce((a, e) => a + (e.finalResult?.total ?? 0), 0) / essays.length) : null,
        series: essaySeries,
        weakCompetencies,
        label: DISCLAIMERS.ESSAY_SCORE_LABEL,
        notice: DISCLAIMERS.ESSAY_EVALUATION,
      },
      scoreNotice: DISCLAIMERS.SCORE_ESTIMATE,
    };
  }

  /** Assuntos com mais erros (somente classificações VERIFIED). */
  async weakTopics(userId: string, limit = 5) {
    const rows = await this.prisma.answer.groupBy({
      by: ['questionId'],
      where: { answerSheet: { session: { userId } }, isCorrect: false },
      _count: { _all: true },
    });
    if (!rows.length) return [];
    const questions = await this.prisma.question.findMany({
      where: { id: { in: rows.map((r) => r.questionId) }, classification: { reviewStatus: 'VERIFIED', topicId: { not: null } } },
      include: { classification: { include: { topic: true } } },
    });
    const counts = new Map<string, { slug: string; name: string; area: ExamArea; errors: number }>();
    for (const q of questions) {
      const t = q.classification!.topic!;
      const c = counts.get(t.slug) ?? { slug: t.slug, name: t.name, area: t.area, errors: 0 };
      c.errors += rows.find((r) => r.questionId === q.id)?._count._all ?? 0;
      counts.set(t.slug, c);
    }
    return [...counts.values()].sort((a, b) => b.errors - a.errors).slice(0, limit);
  }

  /** Questões mais demoradas (seção 25). */
  async slowestQuestions(userId: string, limit = 10) {
    return this.prisma.answer.findMany({
      where: { answerSheet: { session: { userId } }, timeSpentSec: { not: null } },
      orderBy: { timeSpentSec: 'desc' },
      take: limit,
      include: { question: { select: { originalNumber: true, area: true, booklet: { select: { exam: { select: { title: true } } } } } } },
    });
  }

  async dashboard(userId: string) {
    const [overview, profile, last, goals, plan] = await Promise.all([
      this.overview(userId, 'ALL'),
      this.prisma.profile.findUnique({ where: { userId } }),
      this.prisma.examSession.findFirst({ where: { userId }, orderBy: { createdAt: 'desc' }, include: { exam: { include: { edition: true } }, result: true } }),
      this.prisma.goal.findMany({ where: { userId, active: true } }),
      this.prisma.studyPlan.findFirst({ where: { userId, active: true }, include: { tasks: { where: { scheduledOn: { gte: startOfToday(), lt: new Date(startOfToday().getTime() + 86_400_000) } } } } }),
    ]);
    return {
      greeting: `Olá, ${profile?.fullName?.split(' ')[0] ?? 'estudante'}.`,
      firstAccess: overview.examsCompleted === 0,
      onboardingDone: profile?.onboardingDone ?? false,
      overview,
      lastSession: last
        ? { id: last.id, title: last.exam.title, year: last.exam.edition.year, status: last.status, mode: last.mode, percent: last.result?.percent ?? null }
        : null,
      continueSession: last && ['CREATED', 'IN_PROGRESS', 'PAUSED'].includes(last.status) ? last.id : null,
      goals,
      todayTasks: plan?.tasks ?? [],
      startHere: [
        'Faça seu primeiro diagnóstico.',
        'Veja seus pontos fortes.',
        'Descubra o que precisa estudar.',
        'Receba seu plano.',
        'Faça provas completas.',
        'Treine sua redação.',
        'Acompanhe sua evolução.',
      ],
    };
  }
}

function movingAverage(values: number[], window: number): number[] {
  return values.map((_, i) => {
    const slice = values.slice(Math.max(0, i - window + 1), i + 1);
    return Math.round((slice.reduce((a, b) => a + b, 0) / slice.length) * 10) / 10;
  });
}

function startOfToday() {
  const d = new Date();
  d.setHours(0, 0, 0, 0);
  return d;
}
