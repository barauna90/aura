'use client';

import { useState } from 'react';
import { EXAM_AREA_SHORT, type ExamArea } from '@sip-enem/shared';
import { api } from '@/lib/api';
import { useApi } from '@/lib/hooks';
import { dateBR } from '@/lib/format';
import { Badge, Button, Card, Empty, ErrorBox, Field, Input, Notice, PageHeader, Spinner } from '@/components/ui';

interface Task {
  id: string;
  scheduledOn: string;
  kind: string;
  title: string;
  minutes: number;
  status: 'PENDING' | 'DONE' | 'SKIPPED';
  topic: { name: string; area: ExamArea } | null;
}
interface Plan {
  id: string;
  kind: string;
  weeklyHours: number;
  targetDate: string | null;
  daysLeft: number | null;
  rationale: { prioritizedAreas: Array<{ area: ExamArea; percent: number | null }> };
  tasks: Task[];
}

const KIND_LABEL: Record<string, string> = { QUESTOES: 'Questões', REVISAO: 'Revisão', PROVA: 'Prova completa', REDACAO: 'Redação', LEITURA: 'Leitura' };

export default function StudyPlanPage() {
  const { data, error, loading, reload } = useApi<Plan | null>('/study/plan');
  const [form, setForm] = useState({ kind: 'REGULAR' as 'REGULAR' | 'INTENSIVO', weeklyHours: 10, daysLeft: '' });
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<{ message: string } | null>(null);

  const generate = async () => {
    setBusy(true);
    setErr(null);
    try {
      await api('/study/plan/generate', { method: 'POST', json: { kind: form.kind, weeklyHours: Number(form.weeklyHours), daysLeft: form.daysLeft ? Number(form.daysLeft) : undefined } });
      await reload();
    } catch (e) {
      setErr(e as { message: string });
    } finally {
      setBusy(false);
    }
  };

  const setStatus = async (t: Task, status: Task['status']) => {
    await api(`/study/tasks/${t.id}`, { method: 'PUT', json: { status } });
    reload();
  };
  const reschedule = async (t: Task, date: string) => {
    await api(`/study/tasks/${t.id}`, { method: 'PUT', json: { scheduledOn: new Date(date).toISOString() } });
    reload();
  };

  const byDay = new Map<string, Task[]>();
  for (const t of data?.tasks ?? []) {
    const k = t.scheduledOn.slice(0, 10);
    byDay.set(k, [...(byDay.get(k) ?? []), t]);
  }

  return (
    <div className="space-y-6">
      <PageHeader title="Plano de estudos" subtitle="Gerado a partir das suas provas, erros, redações e tempo disponível. Adapta-se conforme você evolui." />

      <Card>
        <h2 className="font-semibold">{data ? 'Regenerar plano' : 'Gerar meu plano'}</h2>
        <div className="mt-3 grid gap-3 sm:grid-cols-4">
          <Field label="Tipo" id="kind">
            <select id="kind" value={form.kind} onChange={(e) => setForm({ ...form, kind: e.target.value as 'REGULAR' | 'INTENSIVO' })} className="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm">
              <option value="REGULAR">Regular</option>
              <option value="INTENSIVO">Intensivo ENEM (reta final)</option>
            </select>
          </Field>
          <Field label="Horas por semana" id="hours">
            <Input id="hours" type="number" min={1} max={80} value={form.weeklyHours} onChange={(e) => setForm({ ...form, weeklyHours: Number(e.target.value) })} />
          </Field>
          <Field label="Dias até o ENEM (opcional)" id="days">
            <Input id="days" type="number" min={1} value={form.daysLeft} onChange={(e) => setForm({ ...form, daysLeft: e.target.value })} />
          </Field>
          <div className="flex items-end">
            <Button onClick={generate} disabled={busy} className="w-full">
              {busy ? 'Gerando…' : 'Gerar plano'}
            </Button>
          </div>
        </div>
        <ErrorBox error={err} />
      </Card>

      {loading && <Spinner />}
      <ErrorBox error={error} />
      {data === null && !loading && <Empty title="Você ainda não tem um plano.">Faça pelo menos uma prova para um plano mais preciso, ou gere um plano inicial agora.</Empty>}

      {data && (
        <>
          <Notice tone="neutral">
            {data.kind === 'INTENSIVO' ? 'Modo Intensivo' : 'Plano regular'} · {data.weeklyHours}h/semana
            {data.targetDate && ` · ENEM em ${dateBR(data.targetDate)} (${data.daysLeft} dias)`} · Prioridade:{' '}
            {data.rationale.prioritizedAreas
              .slice()
              .sort((a, b) => (a.percent ?? -1) - (b.percent ?? -1))
              .map((a) => EXAM_AREA_SHORT[a.area])
              .join(' › ')}
          </Notice>
          <div className="space-y-4">
            {[...byDay.entries()].map(([day, tasks]) => (
              <Card key={day}>
                <h3 className="font-medium">{new Date(day + 'T00:00:00').toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long' })}</h3>
                <ul className="mt-3 space-y-2">
                  {tasks.map((t) => (
                    <li key={t.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-sm">
                      <div className="flex items-center gap-2">
                        <Badge tone={t.kind === 'PROVA' ? 'primary' : t.kind === 'REDACAO' ? 'warning' : 'neutral'}>{KIND_LABEL[t.kind] ?? t.kind}</Badge>
                        <span className={t.status === 'DONE' ? 'line-through text-muted' : ''}>{t.title}</span>
                        <span className="text-xs text-muted">{t.minutes} min</span>
                      </div>
                      <div className="flex items-center gap-2">
                        <input type="date" aria-label="Reagendar" className="rounded border border-border bg-surface px-1 text-xs" onChange={(e) => e.target.value && reschedule(t, e.target.value)} />
                        {t.status !== 'DONE' ? (
                          <Button size="sm" variant="success" onClick={() => setStatus(t, 'DONE')}>
                            Concluir
                          </Button>
                        ) : (
                          <Button size="sm" variant="secondary" onClick={() => setStatus(t, 'PENDING')}>
                            Reabrir
                          </Button>
                        )}
                      </div>
                    </li>
                  ))}
                </ul>
              </Card>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
