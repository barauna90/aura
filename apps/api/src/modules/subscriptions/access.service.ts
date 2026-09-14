import { ForbiddenException, Injectable } from '@nestjs/common';
import { PREMIUM_GRANTING_STATUSES } from '@sip-enem/shared';
import { PrismaService } from '../../prisma/prisma.service';

export interface PlanLimits {
  /** -1 = ilimitado */
  fullExamsPerMonth: number;
  essaysPerMonth: number;
  studyPlan: boolean;
  tutor: boolean;
  errorNotebook: boolean;
}

export interface AccessInfo {
  tier: 'FREE' | 'PREMIUM';
  source: 'FREE_PLAN' | 'SUBSCRIPTION' | 'SCHOLARSHIP';
  planCode: string;
  planName: string;
  limits: PlanLimits;
  validUntil: Date | null;
}

const FREE_FALLBACK: PlanLimits = { fullExamsPerMonth: 1, essaysPerMonth: 1, studyPlan: false, tutor: false, errorNotebook: true };

/**
 * Direitos de acesso (seção 2/27/34). Ordem de resolução:
 *  1. Bolsa ativa → PREMIUM (limites do plano INTENSIVO)
 *  2. Assinatura com status TRIALING/ACTIVE (confirmado por webhook) → PREMIUM
 *  3. Plano gratuito (limites configuráveis pelo admin)
 */
@Injectable()
export class AccessService {
  constructor(private prisma: PrismaService) {}

  async resolve(userId: string): Promise<AccessInfo> {
    const now = new Date();
    const scholarship = await this.prisma.scholarship.findFirst({
      where: { userId, active: true, OR: [{ endsAt: null }, { endsAt: { gt: now } }] },
    });
    if (scholarship) {
      const plan = await this.prisma.plan.findFirst({ where: { code: 'INTENSIVO' } });
      return {
        tier: 'PREMIUM',
        source: 'SCHOLARSHIP',
        planCode: plan?.code ?? 'BOLSA',
        planName: 'Bolsa de estudos',
        limits: this.limitsOf(plan?.limits, { fullExamsPerMonth: -1, essaysPerMonth: 8, studyPlan: true, tutor: true, errorNotebook: true }),
        validUntil: scholarship.endsAt,
      };
    }

    const sub = await this.prisma.subscription.findFirst({
      where: { userId, status: { in: [...PREMIUM_GRANTING_STATUSES] }, currentPeriodEnd: { gt: now } },
      include: { plan: true },
      orderBy: { currentPeriodEnd: 'desc' },
    });
    if (sub) {
      return {
        tier: 'PREMIUM',
        source: 'SUBSCRIPTION',
        planCode: sub.plan.code,
        planName: sub.plan.name,
        limits: this.limitsOf(sub.plan.limits, FREE_FALLBACK),
        validUntil: sub.currentPeriodEnd,
      };
    }

    const free = await this.prisma.plan.findFirst({ where: { code: 'FREE' } });
    return {
      tier: 'FREE',
      source: 'FREE_PLAN',
      planCode: 'FREE',
      planName: free?.name ?? 'Plano gratuito',
      limits: this.limitsOf(free?.limits, FREE_FALLBACK),
      validUntil: null,
    };
  }

  async assertCanStartFullExam(userId: string, exam: { isFreeSample: boolean }) {
    if (exam.isFreeSample) return;
    const access = await this.resolve(userId);
    if (access.limits.fullExamsPerMonth === -1) return;
    const used = await this.prisma.examSession.count({
      where: { userId, mode: 'PROVA_REAL', createdAt: { gte: this.monthStart() }, exam: { isFreeSample: false } },
    });
    if (used >= access.limits.fullExamsPerMonth) {
      throw new ForbiddenException({
        code: 'LIMIT_FULL_EXAMS',
        message: `Seu plano permite ${access.limits.fullExamsPerMonth} prova(s) completa(s) por mês. Assine para liberar acesso ilimitado.`,
      });
    }
  }

  async assertCanSubmitEssay(userId: string) {
    const access = await this.resolve(userId);
    if (access.limits.essaysPerMonth === -1) return;
    const used = await this.prisma.essay.count({
      where: { userId, submittedAt: { gte: this.monthStart() } },
    });
    if (used >= access.limits.essaysPerMonth) {
      throw new ForbiddenException({
        code: 'LIMIT_ESSAYS',
        message: `Seu plano permite ${access.limits.essaysPerMonth} correção(ões) de redação por mês.`,
      });
    }
  }

  async assertFeature(userId: string, feature: 'studyPlan' | 'tutor') {
    const access = await this.resolve(userId);
    if (!access.limits[feature]) {
      throw new ForbiddenException({ code: 'FEATURE_LOCKED', message: 'Este recurso está disponível nos planos pagos e para bolsistas.' });
    }
  }

  private limitsOf(raw: unknown, fallback: PlanLimits): PlanLimits {
    const r = (raw ?? {}) as Partial<PlanLimits>;
    return { ...fallback, ...r };
  }

  private monthStart() {
    const d = new Date();
    return new Date(d.getFullYear(), d.getMonth(), 1);
  }
}
