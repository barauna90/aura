import { Body, Controller, Get, Module, Post } from '@nestjs/common';
import { Cron, CronExpression } from '@nestjs/schedule';
import { IsEnum, IsOptional, IsString, MaxLength } from 'class-validator';
import { PaymentMethod, Prisma } from '@prisma/client';
import { SubscriptionsService } from './subscriptions.service';
import { AccessService } from './access.service';
import { PaymentsModule } from '../payments/payments.module';
import { PromotionsModule, PromotionsService } from '../promotions/promotions.module';
import { Public } from '../../common/decorators/public.decorator';
import { Roles } from '../../common/decorators/roles.decorator';
import { CurrentUser, AuthUser } from '../../common/decorators/current-user.decorator';

class CheckoutDto {
  @IsString() @MaxLength(30)
  planCode: string;

  @IsEnum(PaymentMethod)
  method: PaymentMethod;

  @IsOptional() @IsString() @MaxLength(40)
  couponCode?: string;
}

class CouponCheckDto {
  @IsString() @MaxLength(40)
  couponCode: string;

  @IsString() @MaxLength(30)
  planCode: string;
}

@Controller('subscriptions')
export class SubscriptionsController {
  constructor(
    private subs: SubscriptionsService,
    private promotions: PromotionsService,
  ) {}

  @Public()
  @Get('plans')
  plans() {
    return this.subs.plans();
  }

  @Get('me')
  mine(@CurrentUser() user: AuthUser) {
    return this.subs.mine(user.id);
  }

  @Post('coupon/check')
  async checkCoupon(@CurrentUser() user: AuthUser, @Body() dto: CouponCheckDto) {
    const plan = (await this.subs.plans()).find((p) => p.code === dto.planCode);
    if (!plan) return { valid: false, reason: 'Plano não encontrado' };
    return this.promotions.evaluate(dto.couponCode, user.id, dto.planCode, plan.priceCents);
  }

  @Post('checkout')
  checkout(@CurrentUser() user: AuthUser, @Body() dto: CheckoutDto) {
    return this.subs.checkout(user.id, dto.planCode, dto.method, dto.couponCode);
  }

  @Post('cancel')
  cancel(@CurrentUser() user: AuthUser) {
    return this.subs.cancel(user.id);
  }

  @Roles('ADMIN')
  @Get('admin/plans')
  adminPlans() {
    return this.subs.adminPlans();
  }

  @Roles('ADMIN')
  @Post('admin/plans')
  adminUpsert(@Body() body: Prisma.PlanUncheckedCreateInput, @CurrentUser() user: AuthUser) {
    return this.subs.adminUpsertPlan(body, user.id);
  }

  @Cron(CronExpression.EVERY_HOUR)
  expire() {
    return this.subs.expireEnded();
  }
}

@Module({
  imports: [PaymentsModule, PromotionsModule],
  controllers: [SubscriptionsController],
  providers: [SubscriptionsService, AccessService],
  exports: [AccessService, SubscriptionsService],
})
export class SubscriptionsModule {}
