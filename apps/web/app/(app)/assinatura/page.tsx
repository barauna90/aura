'use client';

import { useState } from 'react';
import { api } from '@/lib/api';
import { useApi } from '@/lib/hooks';
import { brl, dateBR } from '@/lib/format';
import { Badge, Button, Card, ErrorBox, Field, Input, Notice, PageHeader, Select, Spinner } from '@/components/ui';

interface Plan {
  code: string;
  name: string;
  description: string | null;
  priceCents: number;
  trialDays: number;
  benefits: string[];
}
interface Mine {
  access: { tier: string; source: string; planName: string; validUntil: string | null; limits: Record<string, number | boolean> };
  subscription: { id: string; status: string; currentPeriodEnd: string; cancelAtPeriodEnd: boolean; plan: { name: string } } | null;
  payments: Array<{ id: string; amountCents: number; discountCents: number; status: string; method: string; createdAt: string }>;
}
interface Checkout {
  amountCents: number;
  discountCents: number;
  trialDays: number;
  checkout: { kind: string; payload: string; expiresAt?: string };
}

const STATUS_TONE: Record<string, 'success' | 'warning' | 'danger' | 'neutral' | 'primary'> = { ACTIVE: 'success', TRIALING: 'primary', PAST_DUE: 'warning', CANCELED: 'neutral', EXPIRED: 'neutral', REFUNDED: 'danger', SUSPENDED: 'danger' };

export default function SubscriptionPage() {
  const { data: plans } = useApi<Plan[]>('/subscriptions/plans');
  const { data: mine, error, loading, reload } = useApi<Mine>('/subscriptions/me');
  const [planCode, setPlanCode] = useState('');
  const [method, setMethod] = useState<'PIX' | 'CREDIT_CARD' | 'BOLETO'>('PIX');
  const [coupon, setCoupon] = useState('');
  const [couponInfo, setCouponInfo] = useState<{ valid: boolean; reason?: string; discountCents: number; trialDays: number } | null>(null);
  const [checkout, setCheckout] = useState<Checkout | null>(null);
  const [err, setErr] = useState<{ message: string } | null>(null);
  const [busy, setBusy] = useState(false);

  const checkCoupon = async () => {
    if (!coupon || !planCode) return;
    setCouponInfo(await api('/subscriptions/coupon/check', { method: 'POST', json: { couponCode: coupon, planCode } }));
  };
  const subscribe = async () => {
    setBusy(true);
    setErr(null);
    try {
      setCheckout(await api<Checkout>('/subscriptions/checkout', { method: 'POST', json: { planCode, method, couponCode: coupon || undefined } }));
      reload();
    } catch (e) {
      setErr(e as { message: string });
    } finally {
      setBusy(false);
    }
  };
  const cancel = async () => {
    if (!window.confirm('Cancelar a assinatura? Você mantém o acesso até o fim do período já pago.')) return;
    await api('/subscriptions/cancel', { method: 'POST' });
    reload();
  };

  if (loading) return <Spinner />;
  if (error || !mine) return <ErrorBox error={error} />;

  return (
    <div className="space-y-6">
      <PageHeader title="Minha assinatura" subtitle="Sem taxas escondidas. Cancele quando quiser; o acesso continua até o fim do período pago." />

      <Card>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="text-sm text-muted">Acesso atual</p>
            <p className="text-lg font-semibold">
              {mine.access.planName} <Badge tone={mine.access.tier === 'PREMIUM' ? 'success' : 'neutral'}>{mine.access.tier === 'PREMIUM' ? 'Premium' : 'Gratuito'}</Badge>
            </p>
            <p className="text-xs text-muted">
              {mine.access.source === 'SCHOLARSHIP' ? 'Bolsa de estudos' : mine.access.source === 'SUBSCRIPTION' ? 'Assinatura' : 'Plano gratuito'}
              {mine.access.validUntil && ` · válido até ${dateBR(mine.access.validUntil)}`}
            </p>
          </div>
          {mine.subscription && (
            <div className="text-right text-sm">
              <Badge tone={STATUS_TONE[mine.subscription.status]}>{mine.subscription.status}</Badge>
              <p className="mt-1 text-xs text-muted">Período atual até {dateBR(mine.subscription.currentPeriodEnd)}</p>
              {['ACTIVE', 'TRIALING', 'PAST_DUE'].includes(mine.subscription.status) && !mine.subscription.cancelAtPeriodEnd && (
                <Button size="sm" variant="secondary" className="mt-2" onClick={cancel}>
                  Cancelar assinatura
                </Button>
              )}
              {mine.subscription.cancelAtPeriodEnd && <p className="mt-1 text-xs text-warning">Cancelamento agendado para o fim do período.</p>}
            </div>
          )}
        </div>
        <p className="mt-3 text-xs text-muted">
          Limites: provas completas/mês {mine.access.limits.fullExamsPerMonth === -1 ? 'ilimitadas' : mine.access.limits.fullExamsPerMonth} · correções de redação/mês{' '}
          {mine.access.limits.essaysPerMonth === -1 ? 'ilimitadas' : mine.access.limits.essaysPerMonth}
        </p>
      </Card>

      {checkout ? (
        <Card className="space-y-3">
          <h2 className="font-semibold">Conclua o pagamento</h2>
          <p className="text-sm">
            Valor: <strong>{brl(checkout.amountCents)}</strong>
            {checkout.discountCents > 0 && <span className="text-success"> (desconto de {brl(checkout.discountCents)})</span>}
            {checkout.trialDays > 0 && <span className="text-muted"> · {checkout.trialDays} dias de teste antes da primeira cobrança</span>}
          </p>
          {checkout.checkout.kind === 'PIX' ? (
            <div>
              <p className="text-sm text-muted">Código PIX copia-e-cola:</p>
              <code className="mt-1 block break-all rounded bg-surface-2 p-3 text-xs">{checkout.checkout.payload}</code>
            </div>
          ) : (
            <a href={checkout.checkout.payload} className="text-primary underline">
              Abrir página de pagamento
            </a>
          )}
          <Notice tone="primary">O acesso premium é liberado assim que o provedor de pagamento confirmar a transação (normalmente em instantes para PIX).</Notice>
        </Card>
      ) : (
        (!mine.subscription || !['ACTIVE', 'TRIALING'].includes(mine.subscription.status)) && (
          <Card className="space-y-4">
            <h2 className="font-semibold">Assinar</h2>
            <div className="grid gap-3 md:grid-cols-3">
              {(plans ?? [])
                .filter((p) => p.priceCents > 0)
                .map((p) => (
                  <label key={p.code} className={`cursor-pointer rounded-lg border p-4 ${planCode === p.code ? 'border-primary bg-primary-soft' : 'border-border'}`}>
                    <input type="radio" name="plan" className="sr-only" checked={planCode === p.code} onChange={() => setPlanCode(p.code)} />
                    <p className="font-medium">{p.name}</p>
                    <p className="text-2xl font-semibold">
                      {brl(p.priceCents)}
                      <span className="text-xs font-normal text-muted">/mês</span>
                    </p>
                    {p.trialDays > 0 && <p className="text-xs text-success">{p.trialDays} dias grátis</p>}
                    <ul className="mt-2 text-xs text-muted">
                      {p.benefits.map((b) => (
                        <li key={b}>• {b}</li>
                      ))}
                    </ul>
                  </label>
                ))}
            </div>
            <div className="grid gap-3 sm:grid-cols-3">
              <Field label="Forma de pagamento" id="method">
                <Select id="method" value={method} onChange={(e) => setMethod(e.target.value as typeof method)}>
                  <option value="PIX">PIX</option>
                  <option value="CREDIT_CARD">Cartão de crédito (recorrente)</option>
                  <option value="BOLETO">Boleto</option>
                </Select>
              </Field>
              <Field label="Cupom (opcional)" id="coupon">
                <div className="flex gap-2">
                  <Input id="coupon" value={coupon} onChange={(e) => setCoupon(e.target.value.toUpperCase())} onBlur={checkCoupon} />
                  <Button variant="secondary" size="sm" onClick={checkCoupon}>
                    Aplicar
                  </Button>
                </div>
              </Field>
              <div className="flex items-end">
                <Button className="w-full" disabled={!planCode || busy} onClick={subscribe}>
                  {busy ? 'Processando…' : 'Assinar'}
                </Button>
              </div>
            </div>
            {couponInfo && (
              <Notice tone={couponInfo.valid ? 'success' : 'danger'}>
                {couponInfo.valid ? `Cupom válido: desconto de ${brl(couponInfo.discountCents)}${couponInfo.trialDays ? ` · ${couponInfo.trialDays} dias grátis` : ''}` : couponInfo.reason}
              </Notice>
            )}
            <ErrorBox error={err} />
          </Card>
        )
      )}

      <Card>
        <h2 className="font-semibold">Pagamentos</h2>
        <ul className="mt-2 divide-y divide-border text-sm">
          {mine.payments.length === 0 && <li className="py-2 text-muted">Nenhum pagamento.</li>}
          {mine.payments.map((p) => (
            <li key={p.id} className="flex justify-between py-2">
              <span>
                {dateBR(p.createdAt)} · {p.method}
              </span>
              <span>
                {brl(p.amountCents - p.discountCents)} <Badge tone={p.status === 'CONFIRMED' ? 'success' : p.status === 'PENDING' ? 'warning' : 'danger'}>{p.status}</Badge>
              </span>
            </li>
          ))}
        </ul>
      </Card>
    </div>
  );
}
