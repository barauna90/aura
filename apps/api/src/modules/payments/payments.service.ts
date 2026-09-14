import { Inject, Injectable, Logger } from '@nestjs/common';
import { Prisma, SubscriptionStatus } from '@prisma/client';
import { PrismaService } from '../../prisma/prisma.service';
import { AuditService } from '../audit/audit.service';
import { PAYMENT_PROVIDER, PaymentProvider, NormalizedWebhookEvent } from './payment-provider.interface';
import { ReferralService } from '../referrals/referral.service';

/**
 * Processamento de webhooks. O acesso premium depende EXCLUSIVAMENTE do que
 * chega por aqui (assinado + idempotente) — nunca do retorno do navegador.
 */
@Injectable()
export class PaymentsService {
  private readonly logger = new Logger('Payments');

  constructor(
    private prisma: PrismaService,
    private audit: AuditService,
    private referrals: ReferralService,
    @Inject(PAYMENT_PROVIDER) private provider: PaymentProvider,
  ) {}

  async handleWebhook(rawBody: Buffer, headers: Record<string, string | string[] | undefined>) {
    let event: NormalizedWebhookEvent;
    try {
      event = this.provider.verifyAndParseWebhook(rawBody, headers);
    } catch (e) {
      await this.audit.alert('WARN', 'payments.webhook', 'Webhook rejeitado (assinatura inválida)');
      throw e;
    }

    // Idempotência: cada eventId é processado uma única vez.
    const stored = await this.prisma.webhookEvent
      .create({ data: { provider: this.provider.name, eventId: event.eventId, type: event.type, payload: event.raw as Prisma.InputJsonValue } })
      .catch(() => null);
    if (!stored) {
      this.logger.log(`Webhook ${event.eventId} já processado`);
      return { duplicate: true };
    }

    try {
      await this.apply(event);
      await this.prisma.webhookEvent.update({ where: { id: stored.id }, data: { processedAt: new Date() } });
      return { ok: true };
    } catch (e) {
      await this.prisma.webhookEvent.update({ where: { id: stored.id }, data: { error: (e as Error).message } });
      await this.audit.alert('ERROR', 'payments.webhook', `Falha ao processar webhook ${event.eventId}: ${(e as Error).message}`);
      throw e;
    }
  }

  private async apply(event: NormalizedWebhookEvent) {
    const payment = await this.prisma.payment.findUnique({
      where: { providerRef: event.providerRef },
      include: { subscription: { include: { plan: true } } },
    });
    if (!payment) {
      await this.audit.alert('WARN', 'payments.webhook', `Pagamento ${event.providerRef} não encontrado`);
      return;
    }

    switch (event.type) {
      case 'PAYMENT_CONFIRMED': {
        if (payment.status === 'CONFIRMED') return;
        const now = new Date();
        await this.prisma.payment.update({
          where: { id: payment.id },
          data: { status: 'CONFIRMED', confirmedAt: now, instrumentHash: event.instrumentHash, rawWebhook: event.raw as Prisma.InputJsonValue },
        });
        if (payment.subscription) {
          const months = payment.subscription.plan.intervalMonths;
          const base = payment.subscription.currentPeriodEnd > now ? payment.subscription.currentPeriodEnd : now;
          const end = new Date(base);
          end.setMonth(end.getMonth() + months);
          await this.setStatus(payment.subscription.id, 'ACTIVE', { currentPeriodStart: now, currentPeriodEnd: end });
          await this.referrals.onPaymentConfirmed(payment.id);
        }
        break;
      }
      case 'PAYMENT_FAILED':
        await this.prisma.payment.update({ where: { id: payment.id }, data: { status: 'FAILED', rawWebhook: event.raw as Prisma.InputJsonValue } });
        if (payment.subscription && payment.subscription.status === 'ACTIVE') {
          await this.setStatus(payment.subscription.id, 'PAST_DUE');
        }
        break;
      case 'PAYMENT_REFUNDED':
        await this.prisma.payment.update({ where: { id: payment.id }, data: { status: 'REFUNDED', refundedAt: new Date() } });
        if (payment.subscription) await this.setStatus(payment.subscription.id, 'REFUNDED');
        await this.referrals.onPaymentReversed(payment.id, 'REFUND');
        break;
      case 'CHARGEBACK':
        await this.prisma.payment.update({ where: { id: payment.id }, data: { status: 'CHARGEBACK' } });
        if (payment.subscription) await this.setStatus(payment.subscription.id, 'SUSPENDED');
        await this.referrals.onPaymentReversed(payment.id, 'CHARGEBACK');
        break;
      case 'SUBSCRIPTION_CANCELED':
        if (payment.subscription) await this.setStatus(payment.subscription.id, 'CANCELED', { canceledAt: new Date() });
        break;
      default:
        this.logger.warn(`Evento desconhecido ${event.type}`);
    }
  }

  private async setStatus(subscriptionId: string, status: SubscriptionStatus, extra: Prisma.SubscriptionUpdateInput = {}) {
    await this.prisma.subscription.update({ where: { id: subscriptionId }, data: { status, ...extra } });
    await this.audit.log({ action: `subscription.${status.toLowerCase()}`, entityType: 'Subscription', entityId: subscriptionId });
  }
}
