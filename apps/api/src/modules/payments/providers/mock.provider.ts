import { createHmac, randomUUID, timingSafeEqual } from 'crypto';
import { UnauthorizedException } from '@nestjs/common';
import { env } from '../../../config/env';
import {
  CreateChargeInput,
  CreateChargeResult,
  NormalizedWebhookEvent,
  PaymentProvider,
} from '../payment-provider.interface';

/**
 * Provedor de pagamentos simulado para desenvolvimento e testes.
 * Webhook: POST /api/payments/webhook/mock com header `x-signature` = HMAC-SHA256(body, PAYMENT_WEBHOOK_SECRET)
 * Body: { eventId, type: "PAYMENT_CONFIRMED" | ..., providerRef, amountCents?, instrumentHash? }
 */
export class MockPaymentProvider implements PaymentProvider {
  readonly name = 'mock';

  async createCharge(input: CreateChargeInput): Promise<CreateChargeResult> {
    const providerRef = `mock_${randomUUID()}`;
    return {
      providerRef,
      checkout: {
        kind: input.method === 'PIX' ? 'PIX' : input.method === 'BOLETO' ? 'BOLETO' : 'REDIRECT',
        payload:
          input.method === 'PIX'
            ? `00020126MOCKPIX${providerRef}5204000053039865802BR`
            : `${env.APP_URL}/assinatura/mock-checkout?ref=${providerRef}`,
        expiresAt: new Date(Date.now() + 30 * 60_000).toISOString(),
      },
    };
  }

  async cancelRecurring(): Promise<void> {
    /* nada a fazer no mock */
  }

  verifyAndParseWebhook(rawBody: Buffer, headers: Record<string, string | string[] | undefined>): NormalizedWebhookEvent {
    const signature = String(headers['x-signature'] ?? '');
    const expected = createHmac('sha256', env.PAYMENT_WEBHOOK_SECRET).update(rawBody).digest('hex');
    const a = Buffer.from(signature);
    const b = Buffer.from(expected);
    if (a.length !== b.length || !timingSafeEqual(a, b)) {
      throw new UnauthorizedException('Assinatura de webhook inválida');
    }
    const body = JSON.parse(rawBody.toString('utf8'));
    return {
      eventId: String(body.eventId),
      type: body.type ?? 'UNKNOWN',
      providerRef: String(body.providerRef),
      amountCents: body.amountCents,
      instrumentHash: body.instrumentHash,
      raw: body,
    };
  }
}
