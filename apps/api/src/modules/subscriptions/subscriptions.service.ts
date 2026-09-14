import { BadRequestException, Inject, Injectable, NotFoundException } from '@nestjs/common';
import { PaymentMethod, Prisma } from '@prisma/client';
import { PrismaService } from '../../prisma/prisma.service';
import { AuditService } from '../audit/audit.service';
import { PAYMENT_PROVIDER, PaymentProvider } from '../payments/payment-provider.interface';
import { PromotionsService } from '../promotions/promotions.module';
import { AccessService } from './access.service';

/**
 * SUBSCRIPTION ORCHESTRATOR (seção 27/28).
 * Preços, limites e trial vêm do banco (Plan) — nunca do código.
 */
@Injectable()
export class SubscriptionsService {
  constructor(
    private prisma: PrismaService,
    private audit: AuditService,
    private promotions: PromotionsService,
    private access: AccessService,
    @Inject(PAYMENT_PROVIDER) private provider: PaymentProvider,
  ) {}

  plans() {
    return this.prisma.plan.findMany({ where: { isActive: true }, orderBy: { sortOrder: 'asc' } });
  }

  async mine(userId: string) {
    const [access, subscription, payments] = await Promise.all([
      this.access.resolve(userId),
      this.prisma.subscription.findFirst({ where: { userId }, include: { plan: true, coupon: true }, orderBy: { createdAt: 'desc' } }),
      this.prisma.payment.findMany({ where: { userId }, orderBy: { createdAt: 'desc' }, take: 24 }),
    ]);
    return { access, subscription, payments };
  }

  async checkout(userId: string, planCode: string, method: PaymentMethod, couponCode?: string) {
    const plan = await this.prisma.plan.findUnique({ where: { code: planCode } });
    if (!plan || !plan.isActive) throw new NotFoundException('Plano não encontrado');
    if (plan.priceCents === 0) throw new BadRequestException('O plano gratuito não requer assinatura');

    const user = await this.prisma.user.findUniqueOrThrow({ where: { id: userId } });
    const active = await this.prisma.subscription.findFirst({ where: { userId, status: { in: ['ACTIVE', 'TRIALING'] } } });
    if (active) throw new BadRequestException('Você já possui uma assinatura ativa');

    let discountCents = 0;
    let trialDays = plan.trialDays;
    let couponId: string | undefined;
    if (couponCode) {
      const outcome = await this.promotions.evaluate(couponCode, userId, plan.code, plan.priceCents);
      if (!outcome.valid) throw new BadRequestException(outcome.reason);
      discountCents = outcome.discountCents;
      trialDays = Math.max(trialDays, outcome.trialDays);
      couponId = outcome.couponId;
    }

    const now = new Date();
    const periodEnd = new Date(now);
    periodEnd.setDate(periodEnd.getDate() + (trialDays > 0 ? trialDays : 0));

    const amountCents = plan.priceCents - discountCents;
    const subscription = await this.prisma.subscription.create({
      data: {
        userId,
        planId: plan.id,
        // TRIALING só se houver trial; caso contrário aguarda confirmação (PAST_DUE = aguardando pagamento).
        status: trialDays > 0 ? 'TRIALING' : 'PAST_DUE',
        provider: this.provider.name,
        currentPeriodStart: now,
        currentPeriodEnd: periodEnd,
        couponId,
      },
    });

    const charge = await this.provider.createCharge({
      userId,
      email: user.email,
      subscriptionId: subscription.id,
      amountCents,
      description: `${plan.name} — assinatura mensal`,
      method,
      recurring: true,
      intervalMonths: plan.intervalMonths,
    });

    const payment = await this.prisma.payment.create({
      data: {
        userId,
        subscriptionId: subscription.id,
        provider: this.provider.name,
        providerRef: charge.providerRef,
        method,
        amountCents: plan.priceCents,
        discountCents,
      },
    });
    await this.prisma.subscription.update({ where: { id: subscription.id }, data: { providerRef: charge.providerRef } });
    if (couponId) await this.promotions.consume(couponId, userId);
    await this.audit.log({ actorId: userId, action: 'subscription.checkout', entityType: 'Subscription', entityId: subscription.id, metadata: { planCode, method, discountCents } });

    return { subscriptionId: subscription.id, paymentId: payment.id, amountCents, discountCents, trialDays, checkout: charge.checkout };
  }

  async cancel(userId: string) {
    const sub = await this.prisma.subscription.findFirst({ where: { userId, status: { in: ['ACTIVE', 'TRIALING', 'PAST_DUE'] } } });
    if (!sub) throw new NotFoundException('Nenhuma assinatura ativa');
    if (sub.providerRef) await this.provider.cancelRecurring(sub.providerRef);
    // Cancela ao fim do período — sem renovação enganosa, sem perda imediata do que foi pago.
    await this.prisma.subscription.update({ where: { id: sub.id }, data: { cancelAtPeriodEnd: true, canceledAt: new Date() } });
    await this.audit.log({ actorId: userId, action: 'subscription.cancel_requested', entityType: 'Subscription', entityId: sub.id });
    return { ok: true, accessUntil: sub.currentPeriodEnd };
  }

  /** Job diário: expira períodos encerrados. */
  async expireEnded(now = new Date()) {
    const ended = await this.prisma.subscription.findMany({
      where: { status: { in: ['ACTIVE', 'TRIALING'] }, currentPeriodEnd: { lt: now } },
    });
    for (const s of ended) {
      const status = s.cancelAtPeriodEnd ? 'CANCELED' : s.status === 'TRIALING' ? 'EXPIRED' : 'PAST_DUE';
      await this.prisma.subscription.update({ where: { id: s.id }, data: { status } });
      await this.audit.log({ action: `subscription.${status.toLowerCase()}`, entityType: 'Subscription', entityId: s.id });
    }
    return ended.length;
  }

  // ---------- admin ----------
  adminPlans() {
    return this.prisma.plan.findMany({ orderBy: { sortOrder: 'asc' } });
  }

  async adminUpsertPlan(data: Prisma.PlanUncheckedCreateInput, actorId: string) {
    const plan = await this.prisma.plan.upsert({ where: { code: data.code }, update: data, create: data });
    await this.audit.log({ actorId, action: 'plan.upserted', entityType: 'Plan', entityId: plan.id, metadata: data as Prisma.InputJsonValue });
    return plan;
  }
}
