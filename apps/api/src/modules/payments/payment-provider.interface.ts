import { PaymentMethod } from '@prisma/client';

/**
 * PAYMENT AGENT — contrato abstrato de gateway (seção 28/61).
 * Regras de negócio NUNCA dependem de um provedor específico.
 */

export interface CreateChargeInput {
  userId: string;
  email: string;
  subscriptionId: string;
  amountCents: number;
  description: string;
  method: PaymentMethod;
  recurring: boolean;
  intervalMonths: number;
  metadata?: Record<string, string>;
}

export interface CreateChargeResult {
  providerRef: string;
  /** Dados para o cliente concluir (QR Code PIX, URL de checkout, linha do boleto). */
  checkout: {
    kind: 'PIX' | 'REDIRECT' | 'BOLETO' | 'CARD_TOKEN';
    payload: string;
    expiresAt?: string;
  };
}

export type NormalizedEventType =
  | 'PAYMENT_CONFIRMED'
  | 'PAYMENT_FAILED'
  | 'PAYMENT_REFUNDED'
  | 'CHARGEBACK'
  | 'SUBSCRIPTION_CANCELED'
  | 'UNKNOWN';

export interface NormalizedWebhookEvent {
  eventId: string;
  type: NormalizedEventType;
  providerRef: string;
  amountCents?: number;
  /** Fingerprint do instrumento (nunca número de cartão) — usado no antifraude. */
  instrumentHash?: string;
  raw: unknown;
}

export interface PaymentProvider {
  readonly name: string;
  createCharge(input: CreateChargeInput): Promise<CreateChargeResult>;
  cancelRecurring(providerRef: string): Promise<void>;
  /** Deve lançar erro se a assinatura do webhook for inválida. */
  verifyAndParseWebhook(rawBody: Buffer, headers: Record<string, string | string[] | undefined>): NormalizedWebhookEvent;
}

export const PAYMENT_PROVIDER = Symbol('PAYMENT_PROVIDER');
