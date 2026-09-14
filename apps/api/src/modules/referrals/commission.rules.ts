import { CommissionModel, CommissionStatus } from '@prisma/client';

/**
 * REFERRAL AGENT — regras puras de comissionamento e antifraude (seções 30 e 31).
 */

export interface CommissionSettings {
  model: CommissionModel;
  value: number; // % (0-100) ou centavos
  recurring: boolean;
  firstPaymentOnly: boolean;
  validationDays: number;
  minWithdrawalCents: number;
}

export function computeCommissionCents(amountPaidCents: number, s: CommissionSettings): number {
  if (amountPaidCents <= 0) return 0;
  if (s.model === 'FIXED') return Math.min(s.value, amountPaidCents);
  return Math.floor((amountPaidCents * s.value) / 100);
}

/** Um pagamento gera comissão? (primeira mensalidade vs. recorrente) */
export function isCommissionable(paymentOrdinal: number, s: CommissionSettings): boolean {
  if (paymentOrdinal <= 1) return true;
  return s.recurring && !s.firstPaymentOnly;
}

export function availableAt(confirmedAt: Date, s: CommissionSettings): Date {
  return new Date(confirmedAt.getTime() + s.validationDays * 86_400_000);
}

/** Transições permitidas do ciclo de vida da comissão. */
const TRANSITIONS: Record<CommissionStatus, CommissionStatus[]> = {
  PENDING: ['APPROVED', 'CANCELED', 'REVERSED'],
  APPROVED: ['AVAILABLE', 'CANCELED', 'REVERSED'],
  AVAILABLE: ['REQUESTED', 'REVERSED', 'CANCELED'],
  REQUESTED: ['PAID', 'AVAILABLE', 'CANCELED'],
  PAID: ['REVERSED'],
  CANCELED: [],
  REVERSED: [],
};

export function canTransition(from: CommissionStatus, to: CommissionStatus): boolean {
  return TRANSITIONS[from].includes(to);
}

export interface FraudSignals {
  sameUser: boolean; // autoindicação
  sameCpfHash: boolean;
  sameInstrumentHash: boolean;
  sameIpHashRecently: boolean;
  referrerSignupsLast24h: number;
  referredCancellations: number;
  chargebackHistory: number;
}

export interface FraudAssessment {
  flags: string[];
  block: boolean;
}

export function assessFraud(s: FraudSignals): FraudAssessment {
  const flags: string[] = [];
  if (s.sameUser) flags.push('AUTOINDICACAO');
  if (s.sameCpfHash) flags.push('MESMO_CPF');
  if (s.sameInstrumentHash) flags.push('MESMO_MEIO_PAGAMENTO');
  if (s.sameIpHashRecently) flags.push('MESMO_IP_RECENTE');
  if (s.referrerSignupsLast24h >= 10) flags.push('VOLUME_ANORMAL');
  if (s.referredCancellations >= 3) flags.push('CANCELAMENTOS_REPETIDOS');
  if (s.chargebackHistory >= 1) flags.push('HISTORICO_CHARGEBACK');

  const hard = ['AUTOINDICACAO', 'MESMO_CPF', 'MESMO_MEIO_PAGAMENTO', 'HISTORICO_CHARGEBACK'];
  const block = flags.some((f) => hard.includes(f)) || flags.length >= 2;
  return { flags, block };
}

export function canRequestWithdrawal(availableCents: number, s: CommissionSettings): { ok: boolean; reason?: string } {
  if (availableCents < s.minWithdrawalCents) {
    return { ok: false, reason: `Valor mínimo para saque: R$ ${(s.minWithdrawalCents / 100).toFixed(2)}` };
  }
  return { ok: true };
}
