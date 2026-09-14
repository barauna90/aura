import { CouponType } from '@prisma/client';

/** PROMOTION AGENT — regras puras de cupom (seção 33). */

export interface CouponLike {
  type: CouponType;
  value: number;
  months: number | null;
  startsAt: Date;
  endsAt: Date | null;
  maxUses: number | null;
  maxUsesPerUser: number;
  minAmountCents: number | null;
  isActive: boolean;
  allowedPlanCodes: string[]; // vazio = todos
}

export interface CouponContext {
  now: Date;
  planCode: string;
  amountCents: number;
  totalUses: number;
  userUses: number;
}

export interface CouponOutcome {
  valid: boolean;
  reason?: string;
  discountCents: number;
  trialDays: number;
  discountedMonths: number;
}

export function applyCoupon(c: CouponLike, ctx: CouponContext): CouponOutcome {
  const fail = (reason: string): CouponOutcome => ({ valid: false, reason, discountCents: 0, trialDays: 0, discountedMonths: 0 });
  if (!c.isActive) return fail('Cupom inativo');
  if (ctx.now < c.startsAt) return fail('Cupom ainda não está vigente');
  if (c.endsAt && ctx.now > c.endsAt) return fail('Cupom expirado');
  if (c.maxUses !== null && ctx.totalUses >= c.maxUses) return fail('Cupom esgotado');
  if (ctx.userUses >= c.maxUsesPerUser) return fail('Você já utilizou este cupom');
  if (c.allowedPlanCodes.length && !c.allowedPlanCodes.includes(ctx.planCode)) return fail('Cupom não válido para este plano');
  if (c.minAmountCents !== null && ctx.amountCents < c.minAmountCents) return fail('Valor mínimo não atingido');

  switch (c.type) {
    case 'PERCENT':
      return { valid: true, discountCents: Math.floor((ctx.amountCents * Math.min(100, c.value)) / 100), trialDays: 0, discountedMonths: 1 };
    case 'FIXED':
      return { valid: true, discountCents: Math.min(c.value, ctx.amountCents), trialDays: 0, discountedMonths: 1 };
    case 'FIRST_MONTH':
      return { valid: true, discountCents: Math.floor((ctx.amountCents * Math.min(100, c.value)) / 100), trialDays: 0, discountedMonths: 1 };
    case 'N_MONTHS':
      return { valid: true, discountCents: Math.floor((ctx.amountCents * Math.min(100, c.value)) / 100), trialDays: 0, discountedMonths: c.months ?? 1 };
    case 'FREE_TRIAL':
      return { valid: true, discountCents: 0, trialDays: c.value, discountedMonths: 0 };
  }
}
