import { Controller, Get, Injectable, Module, Query } from '@nestjs/common';
import { PrismaService } from '../../prisma/prisma.service';
import { Roles } from '../../common/decorators/roles.decorator';

/** ADMIN SERVICE — dashboard administrativo (seção 39) e observabilidade (45). */
@Injectable()
export class AdminService {
  constructor(private prisma: PrismaService) {}

  async dashboard() {
    const now = new Date();
    const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastMonthStart = new Date(now.getFullYear(), now.getMonth() - 1, 1);

    const [users, subscribers, trials, activeSubs, canceledThisMonth, activeAtMonthStart, paymentsThisMonth, coupons, affiliates, commissions, exams, questions, essays, sessions30d, alerts, aiUsage] =
      await Promise.all([
        this.prisma.user.count({ where: { deletedAt: null } }),
        this.prisma.subscription.count({ where: { status: 'ACTIVE' } }),
        this.prisma.subscription.count({ where: { status: 'TRIALING' } }),
        this.prisma.subscription.findMany({ where: { status: 'ACTIVE' }, include: { plan: true } }),
        this.prisma.subscription.count({ where: { status: 'CANCELED', canceledAt: { gte: monthStart } } }),
        this.prisma.subscription.count({ where: { createdAt: { lt: monthStart }, OR: [{ canceledAt: null }, { canceledAt: { gte: monthStart } }] } }),
        this.prisma.payment.aggregate({ where: { status: 'CONFIRMED', confirmedAt: { gte: monthStart } }, _sum: { amountCents: true, discountCents: true } }),
        this.prisma.coupon.count({ where: { isActive: true } }),
        this.prisma.user.count({ where: { referrals: { some: {} } } }),
        this.prisma.commission.groupBy({ by: ['status'], _sum: { amountCents: true }, _count: { _all: true } }),
        this.prisma.exam.groupBy({ by: ['reviewStatus'], _count: { _all: true } }),
        this.prisma.question.count(),
        this.prisma.essay.groupBy({ by: ['status'], _count: { _all: true } }),
        this.prisma.examSession.count({ where: { createdAt: { gte: new Date(Date.now() - 30 * 86_400_000) } } }),
        this.prisma.systemAlert.findMany({ where: { resolvedAt: null }, orderBy: { createdAt: 'desc' }, take: 20 }),
        this.prisma.aiUsage.aggregate({ where: { createdAt: { gte: monthStart } }, _sum: { costCents: true, inputTokens: true, outputTokens: true }, _count: { _all: true } }),
      ]);

    const mrrCents = activeSubs.reduce((a, s) => a + Math.round(s.plan.priceCents / s.plan.intervalMonths), 0);
    return {
      users,
      subscribers,
      trials,
      mrrCents,
      revenueMonthCents: (paymentsThisMonth._sum.amountCents ?? 0) - (paymentsThisMonth._sum.discountCents ?? 0),
      cancellationsMonth: canceledThisMonth,
      churnRate: activeAtMonthStart ? Math.round((canceledThisMonth / activeAtMonthStart) * 1000) / 10 : 0,
      coupons,
      affiliates,
      commissions,
      exams,
      questions,
      essays,
      sessions30d,
      ai: aiUsage,
      alerts,
      lastMonthStart,
    };
  }

  auditLogs(entityType?: string, entityId?: string) {
    return this.prisma.auditLog.findMany({
      where: { ...(entityType ? { entityType } : {}), ...(entityId ? { entityId } : {}) },
      include: { actor: { select: { email: true } } },
      orderBy: { createdAt: 'desc' },
      take: 200,
    });
  }

  users(q?: string) {
    return this.prisma.user.findMany({
      where: q ? { OR: [{ email: { contains: q, mode: 'insensitive' } }, { profile: { fullName: { contains: q, mode: 'insensitive' } } }] } : {},
      include: { profile: true, subscriptions: { where: { status: { in: ['ACTIVE', 'TRIALING'] } }, include: { plan: true } }, scholarships: { where: { active: true } } },
      orderBy: { createdAt: 'desc' },
      take: 100,
    });
  }
}

@Controller('admin')
@Roles('ADMIN')
export class AdminController {
  constructor(private admin: AdminService) {}

  @Get('dashboard')
  dashboard() {
    return this.admin.dashboard();
  }

  @Get('audit-logs')
  logs(@Query('entityType') entityType?: string, @Query('entityId') entityId?: string) {
    return this.admin.auditLogs(entityType, entityId);
  }

  @Get('users')
  users(@Query('q') q?: string) {
    return this.admin.users(q);
  }
}

@Module({ controllers: [AdminController], providers: [AdminService] })
export class AdminModule {}
