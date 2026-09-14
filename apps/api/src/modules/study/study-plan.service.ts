import { BadRequestException, Injectable, NotFoundException } from '@nestjs/common';
import { ExamArea, Prisma } from '@prisma/client';
import { PrismaService } from '../../prisma/prisma.service';
import { AccessService } from '../subscriptions/access.service';
import { AnalyticsService } from '../analytics/analytics.service';
import { buildPlan, PlannerInput } from './study-plan.rules';

@Injectable()
export class StudyPlanService {
  constructor(
    private prisma: PrismaService,
    private access: AccessService,
    private analytics: AnalyticsService,
  ) {}

  async current(userId: string) {
    const plan = await this.prisma.studyPlan.findFirst({
      where: { userId, active: true },
      include: { tasks: { orderBy: { scheduledOn: 'asc' }, include: { topic: true } } },
      orderBy: { createdAt: 'desc' },
    });
    return plan;
  }

  async today(userId: string) {
    const plan = await this.current(userId);
    if (!plan) return [];
    const start = new Date();
    start.setHours(0, 0, 0, 0);
    const end = new Date(start.getTime() + 86_400_000);
    return plan.tasks.filter((t) => t.scheduledOn >= start && t.scheduledOn < end);
  }

  /** Gera (ou regenera) o plano com base no desempenho real. */
  async generate(userId: string, opts: { kind: 'REGULAR' | 'INTENSIVO'; weeklyHours?: number; daysLeft?: number }) {
    await this.access.assertFeature(userId, 'studyPlan');
    const profile = await this.prisma.profile.findUnique({ where: { userId } });
    const weeklyHours = opts.weeklyHours ?? profile?.weeklyHours ?? 10;
    if (weeklyHours < 1 || weeklyHours > 80) throw new BadRequestException('Horas semanais inválidas');

    const targetDate =
      opts.daysLeft != null ? new Date(Date.now() + opts.daysLeft * 86_400_000) : (profile?.targetExamDate ?? null);

    const perf = await this.analytics.overview(userId, 'ALL');
    const due = await this.prisma.errorNotebookEntry.count({ where: { userId, nextReviewAt: { lte: new Date() } } });
    const weakTopics = await this.analytics.weakTopics(userId, 6);

    const input: PlannerInput = {
      kind: opts.kind,
      startDate: new Date(),
      targetDate,
      weeklyHours,
      areas: perf.byArea.map((a) => ({ area: a.area as ExamArea, percent: a.percent, blankRate: a.blankRate, avgSecondsPerQuestion: a.avgSecondsPerQuestion })),
      essayAverage: perf.essay.average,
      essayWeakCompetencies: perf.essay.weakCompetencies,
      errorNotebookDue: due,
      weakTopics,
    };
    const tasks = buildPlan(input);

    await this.prisma.studyPlan.updateMany({ where: { userId, active: true }, data: { active: false } });
    const topics = await this.prisma.studyTopic.findMany({ where: { slug: { in: tasks.map((t) => t.topicSlug).filter(Boolean) as string[] } } });
    const topicId = (slug?: string) => topics.find((t) => t.slug === slug)?.id;

    return this.prisma.studyPlan.create({
      data: {
        userId,
        kind: opts.kind,
        targetDate,
        weeklyHours,
        daysLeft: opts.daysLeft ?? (targetDate ? Math.ceil((targetDate.getTime() - Date.now()) / 86_400_000) : null),
        rationale: {
          prioritizedAreas: input.areas,
          weakTopics,
          essayWeakCompetencies: input.essayWeakCompetencies,
          errorNotebookDue: due,
        } as unknown as Prisma.InputJsonValue,
        tasks: {
          create: tasks.map((t) => ({
            scheduledOn: t.scheduledOn,
            kind: t.kind,
            title: t.title,
            minutes: t.minutes,
            topicId: topicId(t.topicSlug),
            payload: (t.payload ?? {}) as Prisma.InputJsonValue,
          })),
        },
      },
      include: { tasks: { orderBy: { scheduledOn: 'asc' } } },
    });
  }

  async updateTask(userId: string, taskId: string, data: { status?: 'PENDING' | 'DONE' | 'SKIPPED'; scheduledOn?: Date }) {
    const task = await this.prisma.studyTask.findUnique({ where: { id: taskId }, include: { plan: true } });
    if (!task || task.plan.userId !== userId) throw new NotFoundException('Tarefa não encontrada');
    return this.prisma.studyTask.update({ where: { id: taskId }, data });
  }

  /** Calendário (seção 54): tarefas + provas + redações num intervalo. */
  async calendar(userId: string, from: Date, to: Date) {
    const [tasks, sessions, essays] = await Promise.all([
      this.prisma.studyTask.findMany({ where: { plan: { userId, active: true }, scheduledOn: { gte: from, lte: to } }, orderBy: { scheduledOn: 'asc' } }),
      this.prisma.examSession.findMany({ where: { userId, createdAt: { gte: from, lte: to } }, include: { exam: true, result: true } }),
      this.prisma.essay.findMany({ where: { userId, submittedAt: { gte: from, lte: to } }, include: { finalResult: true } }),
    ]);
    return { tasks, sessions, essays };
  }
}
