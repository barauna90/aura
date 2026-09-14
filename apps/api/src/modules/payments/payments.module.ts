import { Controller, Headers, HttpCode, Module, Post, RawBodyRequest, Req } from '@nestjs/common';
import type { Request } from 'express';
import { env } from '../../config/env';
import { PAYMENT_PROVIDER, PaymentProvider } from './payment-provider.interface';
import { MockPaymentProvider } from './providers/mock.provider';
import { PaymentsService } from './payments.service';
import { Public } from '../../common/decorators/public.decorator';
import { ReferralsModule } from '../referrals/referrals.module';

export function buildPaymentProvider(): PaymentProvider {
  switch (env.PAYMENT_PROVIDER) {
    case 'mock':
      return new MockPaymentProvider();
    default:
      throw new Error(`PAYMENT_PROVIDER desconhecido: ${env.PAYMENT_PROVIDER}. Implemente-o em modules/payments/providers.`);
  }
}

@Controller('payments')
export class PaymentsController {
  constructor(private payments: PaymentsService) {}

  /** Endpoint de webhook — público, mas protegido pela assinatura do provedor. */
  @Public()
  @Post('webhook/:provider')
  @HttpCode(200)
  webhook(@Req() req: RawBodyRequest<Request>, @Headers() headers: Record<string, string>) {
    return this.payments.handleWebhook(req.rawBody ?? Buffer.from(JSON.stringify(req.body)), headers);
  }
}

@Module({
  imports: [ReferralsModule],
  controllers: [PaymentsController],
  providers: [{ provide: PAYMENT_PROVIDER, useFactory: buildPaymentProvider }, PaymentsService],
  exports: [PAYMENT_PROVIDER, PaymentsService],
})
export class PaymentsModule {}
