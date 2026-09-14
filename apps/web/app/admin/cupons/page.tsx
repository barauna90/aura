'use client';

import { useState, type FormEvent } from 'react';
import { api } from '@/lib/api';
import { useApi } from '@/lib/hooks';
import { dateBR } from '@/lib/format';
import { Badge, Button, Card, ErrorBox, Field, Input, Notice, PageHeader, Select, Spinner } from '@/components/ui';

interface Coupon {
  id: string;
  code: string;
  type: string;
  value: number;
  months: number | null;
  startsAt: string;
  endsAt: string | null;
  maxUses: number | null;
  maxUsesPerUser: number;
  minAmountCents: number | null;
  isActive: boolean;
  plans: Array<{ code: string }>;
  _count: { usages: number };
}

export default function CouponsAdmin() {
  const { data, error, loading, reload } = useApi<Coupon[]>('/admin/promotions/coupons');
  const [msg, setMsg] = useState<string | null>(null);
  const [err, setErr] = useState<{ message: string } | null>(null);

  const submit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const get = (k: string) => String(fd.get(k) ?? '').trim();
    setErr(null);
    try {
      await api('/admin/promotions/coupons', {
        method: 'POST',
        json: {
          code: get('code'),
          type: get('type'),
          value: Number(get('value')),
          months: get('months') ? Number(get('months')) : null,
          startsAt: new Date(get('startsAt') || Date.now()).toISOString(),
          endsAt: get('endsAt') ? new Date(get('endsAt')).toISOString() : null,
          maxUses: get('maxUses') ? Number(get('maxUses')) : null,
          maxUsesPerUser: Number(get('maxUsesPerUser') || 1),
          minAmountCents: get('minAmountCents') ? Number(get('minAmountCents')) : null,
          isActive: true,
          planCodes: get('planCodes') ? get('planCodes').split(',').map((s) => s.trim().toUpperCase()) : undefined,
        },
      });
      setMsg('Cupom salvo.');
      reload();
      e.currentTarget.reset();
    } catch (e2) {
      setErr(e2 as { message: string });
    }
  };

  return (
    <div className="space-y-6">
      <PageHeader title="Cupons e promoções" subtitle="Percentual, valor fixo, primeiro mês, X meses com desconto, teste gratuito, cupom individual ou por afiliado." />
      {msg && <Notice tone="success">{msg}</Notice>}
      <ErrorBox error={err ?? error} />
      <div className="grid gap-6 lg:grid-cols-[360px_1fr]">
        <Card>
          <h2 className="font-semibold">Novo cupom</h2>
          <form onSubmit={submit} className="mt-3 space-y-3">
            <Field label="Código" id="code"><Input id="code" name="code" required /></Field>
            <Field label="Tipo" id="type">
              <Select id="type" name="type">
                <option value="PERCENT">Percentual</option>
                <option value="FIXED">Valor fixo (centavos)</option>
                <option value="FIRST_MONTH">Primeiro mês (%)</option>
                <option value="N_MONTHS">X meses com desconto (%)</option>
                <option value="FREE_TRIAL">Teste gratuito (dias)</option>
              </Select>
            </Field>
            <div className="grid grid-cols-2 gap-2">
              <Field label="Valor" id="value"><Input id="value" name="value" type="number" required /></Field>
              <Field label="Meses (N_MONTHS)" id="months"><Input id="months" name="months" type="number" /></Field>
              <Field label="Início" id="startsAt"><Input id="startsAt" name="startsAt" type="date" /></Field>
              <Field label="Fim" id="endsAt"><Input id="endsAt" name="endsAt" type="date" /></Field>
              <Field label="Limite total" id="maxUses"><Input id="maxUses" name="maxUses" type="number" /></Field>
              <Field label="Limite por usuário" id="maxUsesPerUser"><Input id="maxUsesPerUser" name="maxUsesPerUser" type="number" defaultValue={1} /></Field>
            </div>
            <Field label="Valor mínimo (centavos)" id="min"><Input id="min" name="minAmountCents" type="number" /></Field>
            <Field label="Planos permitidos (códigos separados por vírgula; vazio = todos)" id="plans"><Input id="plans" name="planCodes" placeholder="ESTUDANTE,INTENSIVO" /></Field>
            <Button type="submit">Salvar cupom</Button>
          </form>
        </Card>
        <Card>
          <h2 className="font-semibold">Cupons</h2>
          {loading && <Spinner />}
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="text-left text-xs uppercase text-muted"><tr><th className="py-2 pr-3">Código</th><th className="py-2 pr-3">Tipo</th><th className="py-2 pr-3">Valor</th><th className="py-2 pr-3">Vigência</th><th className="py-2 pr-3">Usos</th><th className="py-2 pr-3">Planos</th><th>Status</th></tr></thead>
              <tbody>
                {(data ?? []).map((c) => (
                  <tr key={c.id} className="border-t border-border">
                    <td className="py-2 pr-3 font-mono">{c.code}</td>
                    <td className="py-2 pr-3">{c.type}</td>
                    <td className="py-2 pr-3">{c.value}{c.months ? ` × ${c.months}m` : ''}</td>
                    <td className="py-2 pr-3">{dateBR(c.startsAt)} – {c.endsAt ? dateBR(c.endsAt) : '∞'}</td>
                    <td className="py-2 pr-3">{c._count.usages}{c.maxUses ? `/${c.maxUses}` : ''}</td>
                    <td className="py-2 pr-3">{c.plans.map((p) => p.code).join(', ') || 'todos'}</td>
                    <td className="py-2"><Badge tone={c.isActive ? 'success' : 'neutral'}>{c.isActive ? 'ativo' : 'inativo'}</Badge></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      </div>
    </div>
  );
}
