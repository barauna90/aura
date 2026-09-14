import { BadRequestException, Injectable, Logger, NotFoundException } from '@nestjs/common';
import { createHash } from 'crypto';
import { CommissionStatus, Prisma } from '@prisma/client';
import { PrismaService } from '../../prisma/prisma.service';
import { AuditService } from '../audit/audit.service';
import { env } from '../../config/env';
import {
  assessFraud,
  availableAt,
  canRequestWithdrawal,
  canTransition,
  computeCommissionCents,
  isCommissionable,
} from './commission.rules';

/**
 * REFERRAL ORCHESTRATOR (seções 29–32).
 * Fluxo: INDICAÇÃO → CADASTRO → ASSINATURA → PAGAMENTO CONFIRMADO → VALIDAÇÃO → LIBERADA → SAQUE → PAGA
 */
@Injectable()
export class ReferralService {
  private readonly logger = new Logger('Referrals');

  constructor(
    private prisma: PrismaService,
    private audit: AuditService,
  ) {}

  settings() {
    return this.prisma.referralSettings.upsert({ where: { id: 'default' }, update: {}, create: { id: 'default' } });
  }

  async trackClick(code: string, ip?: string, userAgent?: string) {
    const referrer = await this.prisma.user.findUnique({ where: { referralCode: code.toUpperCase() } });
    if (!referrer) return { valid: false };
    await this.prisma.referralClick.create({
      data: { referrerId: referrer.id, ipHash: ip ? this.hash(ip) : null, userAgent: userAgent?.slice(0, 255) },
    });
    return { valid: true, code: referrer.referralCode };
  }

  async registerSignup(referrerId: string, newUserId: string, meta: { ip?: string }) {
    await this.audit.log({ actorId: newUserId, action: 'referral.signup', entityType: 'User', entityId: referrerId, metadata: { ipHash: meta.ip ? this.hash(meta.ip) : null } });
  }

  /** Chamado pelo PaymentsService após webhook CONFIRMED. */
  async onPaymentConfirmed(paymentId: string) {
    const payment = await this.prisma.payment.findUnique({
      where: { id: paymentId },
      include: { user: { include: { profile: true, referredBy: { include: { profile: true } } } }, subscription: true },
    });
    if (!payment?.user.referredBy || !payment.subscription || !payment.confirmedAt) return;
    const s = await this.settings();
    const referrer = payment.user.referredBy;

    const ordinal = await this.prisma.payment.count({
      where: { subscriptionId: payment.subscriptionId, status: 'CONFIRMED', confirmedAt: { lte: payment.confirmedAt } },
    });
    if (!isCommissionable(ordinal, s)) return;

    const amount = computeCommissionCents(payment.amountCents - payment.discountCents, s);
    if (amount <= 0) return;

    // Antifraude
    const signals = await this.collectFraudSignals(referrer.id, payment.user.id, payment.instrumentHash, payment.user.profile?.cpfHash ?? null);
    const fraud = assessFraud(signals);

    const commission = await this.prisma.commission.upsert({
      where: { paymentId_affiliateId: { paymentId, affiliateId: referrer.id } },
      update: {},
      create: {
        affiliateId: referrer.id,
        referredUserId: payment.user.id,
        subscriptionId: payment.subscription.id,
        paymentId,
        amountCents: amount,
        status: fraud.block ? 'CANCELED' : 'PENDING',
        availableAt: availableAt(payment.confirmedAt, s),
        fraudFlags: fraud.flags,
        blockedReason: fraud.block ? `Bloqueada automaticamente: ${fraud.flags.join(', ')}` : null,
      },
    });
    if (fraud.flags.length) {
      await this.audit.alert('WARN', 'referrals.fraud', `Sinais de fraude na comissão ${commission.id}`, { flags: fraud.flags });
    }
    await this.audit.log({ action: 'commission.created', entityType: 'Commission', entityId: commission.id, metadata: { amount, flags: fraud.flags } });
  }

  async onPaymentReversed(paymentId: string, reason: 'REFUND' | 'CHARGEBACK') {
    const commissions = await this.prisma.commission.findMany({ where: { paymentId } });
    for (const c of commissions) {
      const target: CommissionStatus = c.status === 'PAID' ? 'REVERSED' : 'CANCELED';
      if (!canTransition(c.status, target)) continue;
      await this.prisma.commission.update({ where: { id: c.id }, data: { status: target, blockedReason: reason } });
      await this.audit.log({ action: `commission.${target.toLowerCase()}`, entityType: 'Commission', entityId: c.id, metadata: { reason } });
    }
  }

  /** Job diário: PENDING → APPROVED → AVAILABLE após o período de validação. */
  async releaseMatured(now = new Date()) {
    const matured = await this.prisma.commission.findMany({
      where: { status: { in: ['PENDING', 'APPROVED'] }, availableAt: { lte: now } },
      include: { subscription: true },
    });
    let released = 0;
    for (const c of matured) {
      // Não libera se a assinatura indicada foi cancelada/estornada durante a validação.
      if (['CANCELED', 'REFUNDED', 'SUSPENDED'].includes(c.subscription.status)) {
        await this.prisma.commission.update({ where: { id: c.id }, data: { status: 'CANCELED', blockedReason: 'Assinatura indicada não permaneceu ativa' } });
        continue;
      }
      await this.prisma.commission.update({ where: { id: c.id }, data: { status: 'AVAILABLE' } });
      released++;
    }
    return released;
  }

  async dashboard(userId: string) {
    const user = await this.prisma.user.findUniqueOrThrow({ where: { id: userId } });
    const [clicks, signups, subscriptions, commissions, s] = await Promise.all([
      this.prisma.referralClick.count({ where: { referrerId: userId } }),
      this.prisma.user.count({ where: { referredById: userId } }),
      this.prisma.subscription.count({ where: { user: { referredById: userId }, status: { in: ['ACTIVE', 'TRIALING'] } } }),
      this.prisma.commission.findMany({ where: { affiliateId: userId }, orderBy: { createdAt: 'desc' } }),
      this.settings(),
    ]);
    const sum = (st: CommissionStatus[]) => commissions.filter((c) => st.includes(c.status)).reduce((a, c) => a + c.amountCents, 0);
    return {
      code: user.referralCode,
      link: `${env.APP_URL}/r/${user.referralCode}`,
      clicks,
      signups,
      subscriptions,
      conversionRate: clicks ? Math.round((subscriptions / clicks) * 1000) / 10 : 0,
      pendingCents: sum(['PENDING', 'APPROVED']),
      availableCents: sum(['AVAILABLE']),
      requestedCents: sum(['REQUESTED']),
      paidCents: sum(['PAID']),
      minWithdrawalCents: s.minWithdrawalCents,
      payoutMethods: s.payoutMethods,
      history: commissions,
    };
  }

  async requestWithdrawal(userId: string, method: string, pixKey?: string) {
    const s = await this.settings();
    const available = await this.prisma.commission.findMany({ where: { affiliateId: userId, status: 'AVAILABLE' } });
    const total = available.reduce((a, c) => a + c.amountCents, 0);
    const check = canRequestWithdrawal(total, s);
    if (!check.ok) throw new BadRequestException(check.reason);

    return this.prisma.$transaction(async (tx) => {
      const withdrawal = await tx.withdrawal.create({
        data: { userId, amountCents: total, method, pixKeyHash: pixKey ? this.hash(pixKey) : null },
      });
      await tx.commission.updateMany({
        where: { id: { in: available.map((c) => c.id) } },
        data: { status: 'REQUESTED', withdrawalId: withdrawal.id },
      });
      await tx.auditLog.create({ data: { actorId: userId, action: 'withdrawal.requested', entityType: 'Withdrawal', entityId: withdrawal.id, metadata: { total } } });
      return withdrawal;
    });
  }

  // ---------------- Admin ----------------

  async adminUpdateSettings(data: Prisma.ReferralSettingsUpdateInput, actorId: string) {
    const s = await this.prisma.referralSettings.update({ where: { id: 'default' }, data });
    await this.audit.log({ actorId, action: 'referral.settings.updated', metadata: data as Prisma.InputJsonValue });
    return s;
  }

  async adminBlockCommission(id: string, reason: string, actorId: string) {
    const c = await this.prisma.commission.findUnique({ where: { id } });
    if (!c) throw new NotFoundException();
    if (!canTransition(c.status, 'CANCELED')) throw new BadRequestException(`Não é possível cancelar comissão em ${c.status}`);
    await this.prisma.commission.update({ where: { id }, data: { status: 'CANCELED', blockedReason: reason } });
    await this.audit.log({ actorId, action: 'commission.blocked', entityType: 'Commission', entityId: id, metadata: { reason } });
  }

  async adminPayWithdrawal(id: string, actorId: string) {
    const w = await this.prisma.withdrawal.findUnique({ where: { id } });
    if (!w) throw new NotFoundException();
    await this.prisma.$transaction([
      this.prisma.withdrawal.update({ where: { id }, data: { status: 'PAID', paidAt: new Date() } }),
      this.prisma.commission.updateMany({ where: { withdrawalId: id, status: 'REQUESTED' }, data: { status: 'PAID' } }),
      this.prisma.auditLog.create({ data: { actorId, action: 'withdrawal.paid', entityType: 'Withdrawal', entityId: id } }),
    ]);
  }

  // ---------------- internos ----------------

  private async collectFraudSignals(referrerId: string, referredId: string, instrumentHash: string | null, cpfHash: string | null) {
    const since = new Date(Date.now() - 86_400_000);
    const [referrer, signups24h, referredCancellations, chargebacks, sameInstrument, sameIp] = await Promise.all([
      this.prisma.user.findUnique({ where: { id: referrerId }, include: { profile: true } }),
      this.prisma.user.count({ where: { referredById: referrerId, createdAt: { gte: since } } }),
      this.prisma.subscription.count({ where: { user: { referredById: referrerId }, status: 'CANCELED' } }),
      this.prisma.payment.count({ where: { userId: referredId, status: 'CHARGEBACK' } }),
      instrumentHash
        ? this.prisma.payment.count({ where: { instrumentHash, userId: { not: referredId } } })
        : Promise.resolve(0),
      this.sameIpRecently(referrerId, referredId),
    ]);
    return {
      sameUser: referrerId === referredId,
      sameCpfHash: !!cpfHash && referrer?.profile?.cpfHash === cpfHash,
      sameInstrumentHash: sameInstrument > 0,
      sameIpHashRecently: sameIp,
      referrerSignupsLast24h: signups24h,
      referredCancellations,
      chargebackHistory: chargebacks,
    };
  }

  private async sameIpRecently(referrerId: string, referredId: string): Promise<boolean> {
    const since = new Date(Date.now() - 7 * 86_400_000);
    const [a, b] = await Promise.all([
      this.prisma.refreshToken.findMany({ where: { userId: referrerId, createdAt: { gte: since } }, select: { ip: true } }),
      this.prisma.refreshToken.findMany({ where: { userId: referredId, createdAt: { gte: since } }, select: { ip: true } }),
    ]);
    const set = new Set(a.map((x) => x.ip).filter(Boolean));
    return b.some((x) => x.ip && set.has(x.ip));
  }

  private hash(v: string) {
    return createHash('sha256').update(v).digest('hex');
  }
}
