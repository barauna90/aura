import { Body, Controller, Get, Module, Param, Post, Put, Req } from '@nestjs/common';
import { IsOptional, IsString, MaxLength } from 'class-validator';
import type { Request } from 'express';
import { Cron, CronExpression } from '@nestjs/schedule';
import { ReferralService } from './referral.service';
import { Public } from '../../common/decorators/public.decorator';
import { CurrentUser, AuthUser } from '../../common/decorators/current-user.decorator';
import { Roles } from '../../common/decorators/roles.decorator';

class WithdrawalDto {
  @IsString() @MaxLength(20)
  method: string;

  @IsOptional() @IsString() @MaxLength(120)
  pixKey?: string;
}

class BlockDto {
  @IsString() @MaxLength(300)
  reason: string;
}

@Controller('referrals')
export class ReferralsController {
  constructor(private referrals: ReferralService) {}

  /** plataforma.com.br/r/CODIGO → o frontend chama este endpoint e guarda o cookie. */
  @Public()
  @Post('click/:code')
  click(@Param('code') code: string, @Req() req: Request) {
    return this.referrals.trackClick(code, req.ip, req.headers['user-agent']);
  }

  @Get('dashboard')
  dashboard(@CurrentUser() user: AuthUser) {
    return this.referrals.dashboard(user.id);
  }

  @Post('withdrawals')
  withdraw(@CurrentUser() user: AuthUser, @Body() dto: WithdrawalDto) {
    return this.referrals.requestWithdrawal(user.id, dto.method, dto.pixKey);
  }

  // ---------- admin ----------
  @Roles('ADMIN')
  @Get('admin/settings')
  settings() {
    return this.referrals.settings();
  }

  @Roles('ADMIN')
  @Put('admin/settings')
  updateSettings(@Body() body: Record<string, unknown>, @CurrentUser() user: AuthUser) {
    return this.referrals.adminUpdateSettings(body, user.id);
  }

  @Roles('ADMIN')
  @Post('admin/commissions/:id/block')
  block(@Param('id') id: string, @Body() dto: BlockDto, @CurrentUser() user: AuthUser) {
    return this.referrals.adminBlockCommission(id, dto.reason, user.id);
  }

  @Roles('ADMIN')
  @Post('admin/withdrawals/:id/pay')
  pay(@Param('id') id: string, @CurrentUser() user: AuthUser) {
    return this.referrals.adminPayWithdrawal(id, user.id);
  }

  @Cron(CronExpression.EVERY_DAY_AT_3AM)
  releaseMatured() {
    return this.referrals.releaseMatured();
  }
}

@Module({
  controllers: [ReferralsController],
  providers: [ReferralService],
  exports: [ReferralService],
})
export class ReferralsModule {}
