import { Body, Controller, Get, Injectable, Module, Param, Post, Put } from '@nestjs/common';
import { Prisma } from '@prisma/client';
import { PrismaService } from '../../prisma/prisma.service';
import { AuditService } from '../audit/audit.service';
import { Roles } from '../../common/decorators/roles.decorator';
import { CurrentUser, AuthUser } from '../../common/decorators/current-user.decorator';
import { applyCoupon, CouponOutcome } from './coupon.rules';

@Injectable()
export class PromotionsService {
  constructor(
    private prisma: PrismaService,
    private audit: AuditService,
  ) {}

  /** Valida um cupom para um usuário/plano/valor. Não consome o cupom. */
  async evaluate(code: string, userId: string, planCode: string, amountCents: number): Promise<CouponOutcome & { couponId?: string }> {
    const coupon = await this.prisma.coupon.findUnique({ where: { code: code.toUpperCase().trim() }, include: { plans: true } });
    if (!coupon) return { valid: false, reason: 'Cupom não encontrado', discountCents: 0, trialDays: 0, discountedMonths: 0 };
    const [totalUses, userUses] = await Promise.all([
      this.prisma.couponUsage.count({ where: { couponId: coupon.id } }),
      this.prisma.couponUsage.count({ where: { couponId: coupon.id, userId } }),
    ]);
    const outcome = applyCoupon(
      { ...coupon, allowedPlanCodes: coupon.plans.map((p) => p.code) },
      { now: new Date(), planCode, amountCents, totalUses, userUses },
    );
    return { ...outcome, couponId: coupon.id };
  }

  consume(couponId: string, userId: string) {
    return this.prisma.couponUsage.create({ data: { couponId, userId } });
  }

  listCoupons() {
    return this.prisma.coupon.findMany({ include: { plans: { select: { code: true } }, _count: { select: { usages: true } } }, orderBy: { createdAt: 'desc' } });
  }

  async upsertCoupon(data: Prisma.CouponUncheckedCreateInput & { planCodes?: string[] }, actorId: string) {
    const { planCodes, ...rest } = data;
    const coupon = await this.prisma.coupon.upsert({
      where: { code: rest.code.toUpperCase() },
      update: { ...rest, code: rest.code.toUpperCase(), plans: planCodes ? { set: planCodes.map((code) => ({ code })) } : undefined },
      create: { ...rest, code: rest.code.toUpperCase(), plans: planCodes ? { connect: planCodes.map((code) => ({ code })) } : undefined },
    });
    await this.audit.log({ actorId, action: 'coupon.upserted', entityType: 'Coupon', entityId: coupon.id });
    return coupon;
  }

  listPromotions() {
    return this.prisma.promotion.findMany({ include: { coupons: true }, orderBy: { createdAt: 'desc' } });
  }

  async upsertPromotion(id: string | undefined, data: Prisma.PromotionUncheckedCreateInput, actorId: string) {
    const promo = id
      ? await this.prisma.promotion.update({ where: { id }, data })
      : await this.prisma.promotion.create({ data });
    await this.audit.log({ actorId, action: 'promotion.upserted', entityType: 'Promotion', entityId: promo.id });
    return promo;
  }
}

@Controller('admin/promotions')
@Roles('ADMIN')
export class PromotionsAdminController {
  constructor(private promotions: PromotionsService) {}

  @Get('coupons')
  coupons() {
    return this.promotions.listCoupons();
  }

  @Post('coupons')
  upsertCoupon(@Body() body: Prisma.CouponUncheckedCreateInput & { planCodes?: string[] }, @CurrentUser() user: AuthUser) {
    return this.promotions.upsertCoupon(body, user.id);
  }

  @Get()
  list() {
    return this.promotions.listPromotions();
  }

  @Post()
  create(@Body() body: Prisma.PromotionUncheckedCreateInput, @CurrentUser() user: AuthUser) {
    return this.promotions.upsertPromotion(undefined, body, user.id);
  }

  @Put(':id')
  update(@Param('id') id: string, @Body() body: Prisma.PromotionUncheckedCreateInput, @CurrentUser() user: AuthUser) {
    return this.promotions.upsertPromotion(id, body, user.id);
  }
}

@Module({
  controllers: [PromotionsAdminController],
  providers: [PromotionsService],
  exports: [PromotionsService],
})
export class PromotionsModule {}
