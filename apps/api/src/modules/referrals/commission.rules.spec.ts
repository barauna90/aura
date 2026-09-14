import { assessFraud, availableAt, canRequestWithdrawal, canTransition, computeCommissionCents, isCommissionable } from './commission.rules';
import { applyCoupon } from '../promotions/coupon.rules';

const settings = { model: 'PERCENT' as const, value: 20, recurring: false, firstPaymentOnly: true, validationDays: 30, minWithdrawalCents: 5000 };

describe('Comissionamento (seções 30–31)', () => {
  it('calcula percentual e valor fixo', () => {
    expect(computeCommissionCents(1990, settings)).toBe(398);
    expect(computeCommissionCents(1990, { ...settings, model: 'FIXED', value: 500 })).toBe(500);
    expect(computeCommissionCents(300, { ...settings, model: 'FIXED', value: 500 })).toBe(300);
  });
  it('respeita "somente primeira mensalidade" vs recorrente', () => {
    expect(isCommissionable(1, settings)).toBe(true);
    expect(isCommissionable(2, settings)).toBe(false);
    expect(isCommissionable(2, { ...settings, recurring: true, firstPaymentOnly: false })).toBe(true);
  });
  it('libera após o período de validação', () => {
    expect(availableAt(new Date('2026-01-01T00:00:00Z'), settings).toISOString()).toBe('2026-01-31T00:00:00.000Z');
  });
  it('segue o ciclo PENDING → APPROVED → AVAILABLE → REQUESTED → PAID', () => {
    expect(canTransition('PENDING', 'APPROVED')).toBe(true);
    expect(canTransition('AVAILABLE', 'PAID')).toBe(false);
    expect(canTransition('PAID', 'REVERSED')).toBe(true);
    expect(canTransition('CANCELED', 'PENDING')).toBe(false);
  });
  it('bloqueia autoindicação, mesmo CPF, mesmo meio de pagamento e chargeback', () => {
    const none = { sameUser: false, sameCpfHash: false, sameInstrumentHash: false, sameIpHashRecently: false, referrerSignupsLast24h: 0, referredCancellations: 0, chargebackHistory: 0 };
    expect(assessFraud(none).block).toBe(false);
    expect(assessFraud({ ...none, sameUser: true }).block).toBe(true);
    expect(assessFraud({ ...none, sameCpfHash: true }).flags).toContain('MESMO_CPF');
    expect(assessFraud({ ...none, sameInstrumentHash: true }).block).toBe(true);
    expect(assessFraud({ ...none, chargebackHistory: 1 }).block).toBe(true);
    expect(assessFraud({ ...none, sameIpHashRecently: true }).block).toBe(false);
    expect(assessFraud({ ...none, sameIpHashRecently: true, referrerSignupsLast24h: 12 }).block).toBe(true);
  });
  it('saque exige valor mínimo', () => {
    expect(canRequestWithdrawal(4999, settings).ok).toBe(false);
    expect(canRequestWithdrawal(5000, settings).ok).toBe(true);
  });
});

describe('Cupons (seção 33)', () => {
  const coupon = { type: 'PERCENT' as const, value: 50, months: null, startsAt: new Date('2026-01-01'), endsAt: new Date('2026-12-31'), maxUses: 100, maxUsesPerUser: 1, minAmountCents: null, isActive: true, allowedPlanCodes: ['ESTUDANTE'] };
  const ctx = { now: new Date('2026-06-01'), planCode: 'ESTUDANTE', amountCents: 1990, totalUses: 0, userUses: 0 };

  it('aplica desconto percentual', () => {
    expect(applyCoupon(coupon, ctx)).toMatchObject({ valid: true, discountCents: 995 });
  });
  it('recusa fora da vigência, esgotado, reutilizado ou plano não permitido', () => {
    expect(applyCoupon(coupon, { ...ctx, now: new Date('2027-01-01') }).reason).toBe('Cupom expirado');
    expect(applyCoupon(coupon, { ...ctx, totalUses: 100 }).reason).toBe('Cupom esgotado');
    expect(applyCoupon(coupon, { ...ctx, userUses: 1 }).reason).toBe('Você já utilizou este cupom');
    expect(applyCoupon(coupon, { ...ctx, planCode: 'INTENSIVO' }).reason).toBe('Cupom não válido para este plano');
  });
  it('teste gratuito gera dias de trial sem desconto', () => {
    expect(applyCoupon({ ...coupon, type: 'FREE_TRIAL', value: 14 }, ctx)).toMatchObject({ valid: true, trialDays: 14, discountCents: 0 });
  });
});
