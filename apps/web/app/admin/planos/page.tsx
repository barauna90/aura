'use client';

import { useState } from 'react';
import { api } from '@/lib/api';
import { useApi } from '@/lib/hooks';
import { brl } from '@/lib/format';
import { Button, Card, ErrorBox, Field, Input, Notice, PageHeader, Spinner, Textarea } from '@/components/ui';

interface Plan {
  id: string;
  code: string;
  name: string;
  description: string | null;
  priceCents: number;
  trialDays: number;
  intervalMonths: number;
  limits: Record<string, number | boolean>;
  benefits: string[];
  isActive: boolean;
  sortOrder: number;
}

export default function PlansAdmin() {
  const { data, error, loading, reload } = useApi<Plan[]>('/subscriptions/admin/plans');
  const [editing, setEditing] = useState<Plan | null>(null);
  const [err, setErr] = useState<{ message: string } | null>(null);
  const [msg, setMsg] = useState<string | null>(null);

  const save = async () => {
    if (!editing) return;
    setErr(null);
    try {
      const { id: _id, ...rest } = editing;
      await api('/subscriptions/admin/plans', { method: 'POST', json: rest });
      setMsg(`Plano ${editing.code} salvo.`);
      setEditing(null);
      reload();
    } catch (e) {
      setErr(e as { message: string });
    }
  };

  return (
    <div className="space-y-6">
      <PageHeader title="Planos" subtitle="Preço, benefícios, limites e período de teste são configuráveis aqui — nunca no código." />
      {msg && <Notice tone="success">{msg}</Notice>}
      <ErrorBox error={err ?? error} />
      {loading && <Spinner />}
      <div className="grid gap-4 md:grid-cols-3">
        {(data ?? []).map((p) => (
          <Card key={p.id}>
            <div className="flex justify-between">
              <h2 className="font-semibold">{p.name}</h2>
              <span className="text-xs text-muted">{p.code}</span>
            </div>
            <p className="mt-1 text-2xl font-semibold">{brl(p.priceCents)}<span className="text-xs font-normal text-muted">/{p.intervalMonths} mês</span></p>
            <p className="text-xs text-muted">Trial: {p.trialDays} dias · {p.isActive ? 'ativo' : 'inativo'}</p>
            <pre className="mt-2 overflow-x-auto rounded bg-surface-2 p-2 text-xs">{JSON.stringify(p.limits, null, 1)}</pre>
            <Button size="sm" variant="secondary" className="mt-3" onClick={() => setEditing(p)}>Editar</Button>
          </Card>
        ))}
        <Card>
          <h2 className="font-semibold">Novo plano</h2>
          <Button size="sm" className="mt-3" onClick={() => setEditing({ id: '', code: '', name: '', description: '', priceCents: 0, trialDays: 0, intervalMonths: 1, limits: { fullExamsPerMonth: -1, essaysPerMonth: 4, studyPlan: true, tutor: true, errorNotebook: true }, benefits: [], isActive: true, sortOrder: 9 })}>Criar</Button>
        </Card>
      </div>

      {editing && (
        <Card className="space-y-3">
          <h2 className="font-semibold">{editing.id ? `Editar ${editing.code}` : 'Novo plano'}</h2>
          <div className="grid gap-3 sm:grid-cols-3">
            <Field label="Código" id="code"><Input id="code" value={editing.code} disabled={!!editing.id} onChange={(e) => setEditing({ ...editing, code: e.target.value.toUpperCase() })} /></Field>
            <Field label="Nome" id="name"><Input id="name" value={editing.name} onChange={(e) => setEditing({ ...editing, name: e.target.value })} /></Field>
            <Field label="Preço (centavos)" id="price"><Input id="price" type="number" value={editing.priceCents} onChange={(e) => setEditing({ ...editing, priceCents: Number(e.target.value) })} /></Field>
            <Field label="Dias de teste" id="trial"><Input id="trial" type="number" value={editing.trialDays} onChange={(e) => setEditing({ ...editing, trialDays: Number(e.target.value) })} /></Field>
            <Field label="Intervalo (meses)" id="int"><Input id="int" type="number" value={editing.intervalMonths} onChange={(e) => setEditing({ ...editing, intervalMonths: Number(e.target.value) })} /></Field>
            <Field label="Ordem" id="order"><Input id="order" type="number" value={editing.sortOrder} onChange={(e) => setEditing({ ...editing, sortOrder: Number(e.target.value) })} /></Field>
          </div>
          <Field label="Descrição" id="desc"><Input id="desc" value={editing.description ?? ''} onChange={(e) => setEditing({ ...editing, description: e.target.value })} /></Field>
          <Field label="Benefícios (um por linha)" id="ben"><Textarea id="ben" rows={4} value={editing.benefits.join('\n')} onChange={(e) => setEditing({ ...editing, benefits: e.target.value.split('\n').filter(Boolean) })} /></Field>
          <Field label="Limites (JSON) — -1 = ilimitado" id="lim">
            <Textarea id="lim" rows={4} value={JSON.stringify(editing.limits, null, 1)} onChange={(e) => { try { setEditing({ ...editing, limits: JSON.parse(e.target.value) }); } catch { /* aguarda JSON válido */ } }} />
          </Field>
          <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={editing.isActive} onChange={(e) => setEditing({ ...editing, isActive: e.target.checked })} /> Ativo</label>
          <div className="flex gap-2">
            <Button onClick={save}>Salvar</Button>
            <Button variant="secondary" onClick={() => setEditing(null)}>Cancelar</Button>
          </div>
        </Card>
      )}
    </div>
  );
}
