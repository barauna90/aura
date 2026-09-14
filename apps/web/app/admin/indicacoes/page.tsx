'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useApi } from '@/lib/hooks';
import { Button, Card, ErrorBox, Field, Input, Notice, PageHeader, Select, Spinner } from '@/components/ui';

interface Settings {
  model: 'PERCENT' | 'FIXED';
  value: number;
  recurring: boolean;
  firstPaymentOnly: boolean;
  validationDays: number;
  minWithdrawalCents: number;
  payoutMethods: string[];
}

export default function ReferralsAdmin() {
  const { data, error, loading, reload } = useApi<Settings>('/referrals/admin/settings');
  const [form, setForm] = useState<Settings | null>(null);
  const [msg, setMsg] = useState<string | null>(null);
  const [err, setErr] = useState<{ message: string } | null>(null);
  const [commissionId, setCommissionId] = useState('');
  const [reason, setReason] = useState('');
  const [withdrawalId, setWithdrawalId] = useState('');

  useEffect(() => {
    if (data) setForm(data);
  }, [data]);

  const save = async () => {
    if (!form) return;
    setErr(null);
    try {
      await api('/referrals/admin/settings', { method: 'PUT', json: form });
      setMsg('Configurações salvas.');
      reload();
    } catch (e) {
      setErr(e as { message: string });
    }
  };
  const block = async () => {
    setErr(null);
    try {
      await api(`/referrals/admin/commissions/${commissionId}/block`, { method: 'POST', json: { reason } });
      setMsg('Comissão bloqueada.');
    } catch (e) {
      setErr(e as { message: string });
    }
  };
  const pay = async () => {
    setErr(null);
    try {
      await api(`/referrals/admin/withdrawals/${withdrawalId}/pay`, { method: 'POST' });
      setMsg('Saque marcado como pago.');
    } catch (e) {
      setErr(e as { message: string });
    }
  };

  if (loading || !form) return <Spinner />;
  return (
    <div className="space-y-6">
      <PageHeader title="Indicações e comissões" subtitle="Fluxo: indicação → cadastro → assinatura → pagamento confirmado → validação → liberada → saque → pagamento." />
      {msg && <Notice tone="success">{msg}</Notice>}
      <ErrorBox error={err ?? error} />
      <Card className="space-y-3">
        <h2 className="font-semibold">Regras de comissionamento</h2>
        <div className="grid gap-3 sm:grid-cols-3">
          <Field label="Modelo" id="model">
            <Select id="model" value={form.model} onChange={(e) => setForm({ ...form, model: e.target.value as Settings['model'] })}>
              <option value="PERCENT">Percentual</option>
              <option value="FIXED">Valor fixo (centavos)</option>
            </Select>
          </Field>
          <Field label="Valor" id="value"><Input id="value" type="number" value={form.value} onChange={(e) => setForm({ ...form, value: Number(e.target.value) })} /></Field>
          <Field label="Período de validação (dias)" id="vd"><Input id="vd" type="number" value={form.validationDays} onChange={(e) => setForm({ ...form, validationDays: Number(e.target.value) })} /></Field>
          <Field label="Valor mínimo para saque (centavos)" id="min"><Input id="min" type="number" value={form.minWithdrawalCents} onChange={(e) => setForm({ ...form, minWithdrawalCents: Number(e.target.value) })} /></Field>
          <Field label="Formas de pagamento (vírgula)" id="pm"><Input id="pm" value={form.payoutMethods.join(',')} onChange={(e) => setForm({ ...form, payoutMethods: e.target.value.split(',').map((s) => s.trim()) })} /></Field>
        </div>
        <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.firstPaymentOnly} onChange={(e) => setForm({ ...form, firstPaymentOnly: e.target.checked, recurring: e.target.checked ? false : form.recurring })} /> Comissão somente na primeira mensalidade</label>
        <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.recurring} onChange={(e) => setForm({ ...form, recurring: e.target.checked, firstPaymentOnly: e.target.checked ? false : form.firstPaymentOnly })} /> Comissão recorrente</label>
        <Button onClick={save}>Salvar</Button>
      </Card>
      <div className="grid gap-4 md:grid-cols-2">
        <Card className="space-y-2">
          <h2 className="font-semibold">Bloquear comissão suspeita</h2>
          <Field label="ID da comissão" id="cid"><Input id="cid" value={commissionId} onChange={(e) => setCommissionId(e.target.value)} /></Field>
          <Field label="Motivo" id="reason"><Input id="reason" value={reason} onChange={(e) => setReason(e.target.value)} /></Field>
          <Button variant="danger" onClick={block} disabled={!commissionId || !reason}>Bloquear</Button>
        </Card>
        <Card className="space-y-2">
          <h2 className="font-semibold">Marcar saque como pago</h2>
          <Field label="ID do saque" id="wid"><Input id="wid" value={withdrawalId} onChange={(e) => setWithdrawalId(e.target.value)} /></Field>
          <Button onClick={pay} disabled={!withdrawalId}>Confirmar pagamento</Button>
        </Card>
      </div>
      <Notice tone="neutral">Antifraude automático: autoindicação, mesmo CPF, mesmo meio de pagamento e chargeback bloqueiam a comissão; volume anormal e cancelamentos repetidos geram alerta.</Notice>
    </div>
  );
}
