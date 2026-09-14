'use client';

import { useState, type FormEvent } from 'react';
import { api } from '@/lib/api';
import { useApi } from '@/lib/hooks';
import { dateBR } from '@/lib/format';
import { Badge, Button, Card, ErrorBox, Field, Input, Notice, PageHeader, Select, Spinner } from '@/components/ui';

interface Scholarship {
  id: string;
  days: number | null;
  startsAt: string;
  endsAt: string | null;
  active: boolean;
  reason: string | null;
  user: { email: string; profile: { fullName: string } | null };
  sponsor: { name: string } | null;
}
interface Sponsor {
  id: string;
  name: string;
  seats: number;
  usedSeats: number;
  active: boolean;
}

export default function ScholarshipsAdmin() {
  const { data, error, loading, reload } = useApi<Scholarship[]>('/admin/scholarships');
  const { data: sponsors, reload: reloadSponsors } = useApi<Sponsor[]>('/admin/scholarships/sponsors');
  const [msg, setMsg] = useState<string | null>(null);
  const [err, setErr] = useState<{ message: string } | null>(null);

  const grant = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const duration = String(fd.get('duration'));
    setErr(null);
    try {
      await api('/admin/scholarships', {
        method: 'POST',
        json: { email: fd.get('email'), days: duration === 'unlimited' ? undefined : Number(duration), unlimited: duration === 'unlimited', sponsorId: fd.get('sponsorId') || undefined, reason: fd.get('reason') || undefined },
      });
      setMsg('Bolsa concedida.');
      reload();
      reloadSponsors();
      e.currentTarget.reset();
    } catch (e2) {
      setErr(e2 as { message: string });
    }
  };
  const createSponsor = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    try {
      await api('/admin/scholarships/sponsors', { method: 'POST', json: { name: fd.get('name'), seats: Number(fd.get('seats')) } });
      setMsg('Patrocinador criado.');
      reloadSponsors();
      e.currentTarget.reset();
    } catch (e2) {
      setErr(e2 as { message: string });
    }
  };
  const revoke = async (id: string) => {
    if (!window.confirm('Revogar esta bolsa?')) return;
    await api(`/admin/scholarships/${id}/revoke`, { method: 'POST' });
    reload();
  };

  return (
    <div className="space-y-6">
      <PageHeader title="Bolsas e patrocínios" subtitle="Acesso gratuito por 30, 90, 180 ou 365 dias, ou integral. Empresas podem financiar vagas; o sistema controla as vagas automaticamente." />
      {msg && <Notice tone="success">{msg}</Notice>}
      <ErrorBox error={err ?? error} />
      <div className="grid gap-4 md:grid-cols-2">
        <Card>
          <h2 className="font-semibold">Conceder bolsa</h2>
          <form onSubmit={grant} className="mt-3 space-y-3">
            <Field label="E-mail do aluno" id="email"><Input id="email" name="email" type="email" required /></Field>
            <Field label="Duração" id="duration">
              <Select id="duration" name="duration">
                <option value="30">30 dias</option>
                <option value="90">90 dias</option>
                <option value="180">6 meses</option>
                <option value="365">12 meses</option>
                <option value="unlimited">Acesso integral gratuito</option>
              </Select>
            </Field>
            <Field label="Patrocinador (opcional)" id="sponsor">
              <Select id="sponsor" name="sponsorId">
                <option value="">— sem patrocínio —</option>
                {(sponsors ?? []).filter((s) => s.active).map((s) => <option key={s.id} value={s.id}>{s.name} ({s.usedSeats}/{s.seats})</option>)}
              </Select>
            </Field>
            <Field label="Motivo" id="reason"><Input id="reason" name="reason" /></Field>
            <Button type="submit">Conceder</Button>
          </form>
        </Card>
        <Card>
          <h2 className="font-semibold">Empresas patrocinadoras</h2>
          <ul className="mt-2 text-sm">
            {(sponsors ?? []).map((s) => <li key={s.id} className="flex justify-between border-b border-border py-1"><span>{s.name}</span><span className="text-muted">{s.usedSeats}/{s.seats} bolsas</span></li>)}
          </ul>
          <form onSubmit={createSponsor} className="mt-3 flex flex-wrap items-end gap-2">
            <Field label="Nome" id="sname"><Input id="sname" name="name" required /></Field>
            <Field label="Vagas" id="seats"><Input id="seats" name="seats" type="number" min={1} required className="w-24" /></Field>
            <Button type="submit" variant="secondary">Adicionar</Button>
          </form>
        </Card>
      </div>
      <Card>
        <h2 className="font-semibold">Bolsas concedidas</h2>
        {loading && <Spinner />}
        <div className="mt-3 overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="text-left text-xs uppercase text-muted"><tr><th className="py-2 pr-3">Aluno</th><th className="py-2 pr-3">Duração</th><th className="py-2 pr-3">Validade</th><th className="py-2 pr-3">Patrocínio</th><th className="py-2 pr-3">Status</th><th></th></tr></thead>
            <tbody>
              {(data ?? []).map((s) => (
                <tr key={s.id} className="border-t border-border">
                  <td className="py-2 pr-3">{s.user.profile?.fullName ?? s.user.email}<span className="ml-1 text-xs text-muted">{s.user.email}</span></td>
                  <td className="py-2 pr-3">{s.days ? `${s.days} dias` : 'Integral'}</td>
                  <td className="py-2 pr-3">{dateBR(s.startsAt)} – {s.endsAt ? dateBR(s.endsAt) : '∞'}</td>
                  <td className="py-2 pr-3">{s.sponsor?.name ?? '—'}</td>
                  <td className="py-2 pr-3"><Badge tone={s.active ? 'success' : 'neutral'}>{s.active ? 'ativa' : 'revogada'}</Badge></td>
                  <td className="py-2">{s.active && <button className="text-xs text-danger underline" onClick={() => revoke(s.id)}>Revogar</button>}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}
