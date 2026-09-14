import { createHmac } from 'crypto';
import { MockPaymentProvider } from './mock.provider';
import { env } from '../../../config/env';

describe('Webhook assinado (seção 28/43)', () => {
  const provider = new MockPaymentProvider();
  const body = Buffer.from(JSON.stringify({ eventId: 'evt_1', type: 'PAYMENT_CONFIRMED', providerRef: 'mock_abc', amountCents: 1990 }));

  it('aceita assinatura válida e normaliza o evento', () => {
    const sig = createHmac('sha256', env.PAYMENT_WEBHOOK_SECRET).update(body).digest('hex');
    const evt = provider.verifyAndParseWebhook(body, { 'x-signature': sig });
    expect(evt).toMatchObject({ eventId: 'evt_1', type: 'PAYMENT_CONFIRMED', providerRef: 'mock_abc', amountCents: 1990 });
  });

  it('rejeita assinatura inválida — o navegador nunca libera acesso', () => {
    expect(() => provider.verifyAndParseWebhook(body, { 'x-signature': 'deadbeef' })).toThrow();
    expect(() => provider.verifyAndParseWebhook(body, {})).toThrow();
  });
});
