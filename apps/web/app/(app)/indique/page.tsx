'use client';

import { useState } from 'react';
import { api } from '@/lib/api';
import { useApi } from '@/lib/hooks';
import { brl, dateBR } from '@/lib/format';
import { Badge, Button, Card, ErrorBox, Field, Input, Notice, PageHeader, Spinner, Stat } from '@/components/ui';

interface Dash {
  code: string;
  link: string;
  clicks: number;
  signups: number;
  subscriptions: number;
  conversionRate: number;
  pendingCents: number;
  availableCents: number;
  requestedCents: number;
  paidCents: number;
  minWithdrawalCents: number;
  history: Array<{ id: string; amountCents: number; status: string; createdAt: string; availableAt: string | null; blockedReason: string | null }>;
}

const TONE: Record<string, 'neutral' | 'success' | 'warning' | 'danger' | 'primary'> = { PENDING: 'neutral', APPROVED: 'primary', AVAILABLE: 'success', REQUESTED: 'warning', PAID: 'success', CANCELED: 'danger', REVERSED: 'danger' };

export default function ReferralPage() {
  const { data, error, loading, reload } = useApi<Dash>('/referrals/dashboard');
  const [pix, setPix] = useState('');
  const [msg, setMsg] = useState<string | null>(null);
  const [err, setErr] = useState<{ message: string } | null>(null);

  const withdraw = async () => {
    setErr(null);
    try {
      await api('/referrals/withdrawals', { method: 'POST', json: { method: 'PIX', pixKey: pix } });
      setMsg('Saque solicitado. Você será notificado quando for pago.');
      reload();
    } catch (e) {
      setErr(e as { message: string });
    }
  };

  if (loading) return <Spinner />;
  if (error || !data) return <ErrorBox error={error} />;

  return (
    <div className="space-y-6">
      <PageHeader title="Indique e ganhe" subtitle="Compartilhe seu link. Quando um indicado assina e o pagamento é confirmado, você recebe comissão após o período de validação." />
      <Card>
        <p className="text-sm text-muted">Seu link exclusivo</p>
        <div className="mt-1 flex flex-wrap items-center gap-2">
          <code className="rounded bg-surface-2 px-3 py-2 text-sm">{data.link}</code>
          <Button size="sm" variant="secondary" onClick={() => navigator.clipboard?.writeText(data.link)}>
            Copiar
          </Button>
        </div>
        <p className="mt-2 text-xs text-muted">Código: {data.code}</p>
      </Card>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Stat label="Cliques" value={data.clicks} />
        <Stat label="Cadastros" value={data.signups} />
        <Stat label="Assinaturas" value={data.subscriptions} hint={`Conversão ${data.conversionRate}%`} />
        <Stat label="Total recebido" value={brl(data.paidCents)} />
      </div>
      <div className="grid gap-3 sm:grid-cols-3">
        <Stat label="Comissão pendente" value={brl(data.pendingCents)} hint="Em validação" />
        <Stat label="Comissão disponível" value={brl(data.availableCents)} />
        <Stat label="Saque solicitado" value={brl(data.requestedCents)} />
      </div>
      <Card>
        <h2 className="font-semibold">Solicitar saque</h2>
        <p className="text-sm text-muted">Valor mínimo: {brl(data.minWithdrawalCents)} · via PIX</p>
        <div className="mt-3 flex flex-wrap items-end gap-2">
          <Field label="Chave PIX" id="pix">
            <Input id="pix" value={pix} onChange={(e) => setPix(e.target.value)} className="min-w-64" />
          </Field>
          <Button onClick={withdraw} disabled={data.availableCents < data.minWithdrawalCents || !pix}>
            Solicitar {brl(data.availableCents)}
          </Button>
        </div>
        {msg && <Notice tone="success">{msg}</Notice>}
        <ErrorBox error={err} />
      </Card>
      <Card>
        <h2 className="font-semibold">Histórico de comissões</h2>
        <ul className="mt-3 divide-y divide-border text-sm">
          {data.history.length === 0 && <li className="py-2 text-muted">Nenhuma comissão ainda.</li>}
          {data.history.map((c) => (
            <li key={c.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
              <span>
                {dateBR(c.createdAt)} · {brl(c.amountCents)}
                {c.availableAt && c.status === 'PENDING' && <span className="ml-2 text-xs text-muted">libera em {dateBR(c.availableAt)}</span>}
              </span>
              <span className="flex items-center gap-2">
                {c.blockedReason && <span className="text-xs text-muted">{c.blockedReason}</span>}
                <Badge tone={TONE[c.status]}>{c.status}</Badge>
              </span>
            </li>
          ))}
        </ul>
      </Card>
      <Notice tone="neutral">Indicações são verificadas contra autoindicação, contas duplicadas e estornos. Comissões suspeitas podem ser bloqueadas.</Notice>
    </div>
  );
}
