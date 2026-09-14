'use client';

import { useApi } from '@/lib/hooks';
import { brl, dateBR } from '@/lib/format';
import { Badge, Card, ErrorBox, PageHeader, Spinner, Stat } from '@/components/ui';

interface AdminDash {
  users: number;
  subscribers: number;
  trials: number;
  mrrCents: number;
  revenueMonthCents: number;
  cancellationsMonth: number;
  churnRate: number;
  coupons: number;
  affiliates: number;
  commissions: Array<{ status: string; _sum: { amountCents: number | null }; _count: { _all: number } }>;
  exams: Array<{ reviewStatus: string; _count: { _all: number } }>;
  questions: number;
  essays: Array<{ status: string; _count: { _all: number } }>;
  sessions30d: number;
  ai: { _sum: { costCents: number | null; inputTokens: number | null; outputTokens: number | null }; _count: { _all: number } };
  alerts: Array<{ id: string; level: string; source: string; message: string; createdAt: string }>;
}

export default function AdminHome() {
  const { data, error, loading } = useApi<AdminDash>('/admin/dashboard');
  if (loading) return <Spinner />;
  if (error || !data) return <ErrorBox error={error} />;
  return (
    <div className="space-y-6">
      <PageHeader title="Dashboard administrativo" />
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Stat label="Usuários" value={data.users} />
        <Stat label="Assinantes ativos" value={data.subscribers} hint={`${data.trials} em trial`} />
        <Stat label="MRR" value={brl(data.mrrCents)} />
        <Stat label="Receita no mês" value={brl(data.revenueMonthCents)} />
        <Stat label="Cancelamentos no mês" value={data.cancellationsMonth} hint={`Churn ${data.churnRate}%`} />
        <Stat label="Cupons ativos" value={data.coupons} />
        <Stat label="Afiliados" value={data.affiliates} />
        <Stat label="Sessões (30 dias)" value={data.sessions30d} />
        <Stat label="Questões cadastradas" value={data.questions} />
        <Stat label="Uso de IA no mês" value={data.ai._count._all} hint={`${((data.ai._sum.inputTokens ?? 0) + (data.ai._sum.outputTokens ?? 0)).toLocaleString('pt-BR')} tokens · ${brl(data.ai._sum.costCents ?? 0)}`} />
      </div>
      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <h2 className="font-semibold">Provas por status</h2>
          <ul className="mt-2 text-sm">{data.exams.map((e) => <li key={e.reviewStatus} className="flex justify-between py-1"><span>{e.reviewStatus}</span><span>{e._count._all}</span></li>)}</ul>
        </Card>
        <Card>
          <h2 className="font-semibold">Redações por status</h2>
          <ul className="mt-2 text-sm">{data.essays.map((e) => <li key={e.status} className="flex justify-between py-1"><span>{e.status}</span><span>{e._count._all}</span></li>)}</ul>
        </Card>
        <Card>
          <h2 className="font-semibold">Comissões</h2>
          <ul className="mt-2 text-sm">{data.commissions.map((c) => <li key={c.status} className="flex justify-between py-1"><span>{c.status}</span><span>{c._count._all} · {brl(c._sum.amountCents ?? 0)}</span></li>)}</ul>
        </Card>
      </div>
      <Card>
        <h2 className="font-semibold">Alertas do sistema</h2>
        {data.alerts.length === 0 && <p className="mt-2 text-sm text-muted">Nenhum alerta aberto.</p>}
        <ul className="mt-2 divide-y divide-border text-sm">
          {data.alerts.map((a) => (
            <li key={a.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
              <span><Badge tone={a.level === 'ERROR' ? 'danger' : a.level === 'WARN' ? 'warning' : 'neutral'}>{a.level}</Badge> <span className="ml-2 text-muted">{a.source}</span> {a.message}</span>
              <span className="text-xs text-muted">{dateBR(a.createdAt, true)}</span>
            </li>
          ))}
        </ul>
      </Card>
    </div>
  );
}
