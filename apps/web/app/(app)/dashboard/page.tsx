'use client';

import Link from 'next/link';
import { EXAM_AREA_SHORT, type ExamArea } from '@sip-enem/shared';
import { useApi } from '@/lib/hooks';
import { Card, ErrorBox, LinkButton, ProgressBar, Spinner, Stat } from '@/components/ui';

interface Dashboard {
  greeting: string;
  firstAccess: boolean;
  onboardingDone: boolean;
  overview: {
    examsCompleted: number;
    questionsAnswered: number;
    accuracy: number | null;
    hoursStudied: number;
    studyDays: number;
    strongestArea: string | null;
    weakestArea: string | null;
    byArea: Array<{ area: string; percent: number | null }>;
    essay: { count: number; average: number | null; series: Array<{ total: number }> };
  };
  lastSession: { id: string; title: string; year: number; status: string; percent: number | null } | null;
  continueSession: string | null;
  goals: Array<{ id: string; kind: string; target: number }>;
  todayTasks: Array<{ id: string; title: string; minutes: number; status: string; kind: string }>;
  startHere: string[];
}

export default function DashboardPage() {
  const { data, error, loading } = useApi<Dashboard>('/analytics/dashboard');
  if (loading) return <Spinner />;
  if (error || !data) return <ErrorBox error={error} />;
  const o = data.overview;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">{data.greeting}</h1>
        <p className="text-sm text-muted">Acompanhe sua evolução e siga o plano de hoje.</p>
      </div>

      {!data.onboardingDone && (
        <Card className="border-primary/30 bg-primary-soft">
          <p className="font-medium">Conte seu objetivo para personalizar seu plano.</p>
          <LinkButton href="/onboarding" className="mt-3">
            Responder (2 minutos)
          </LinkButton>
        </Card>
      )}

      {data.firstAccess && (
        <Card>
          <h2 className="font-semibold">Comece por aqui</h2>
          <ol className="mt-3 grid gap-2 text-sm md:grid-cols-2">
            {data.startHere.map((s, i) => (
              <li key={s} className="flex gap-2">
                <span className="font-semibold text-primary">{i + 1}.</span> {s}
              </li>
            ))}
          </ol>
          <div className="mt-4 flex flex-wrap gap-2">
            <LinkButton href="/provas">Fazer meu primeiro diagnóstico</LinkButton>
          </div>
        </Card>
      )}

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Stat label="Provas concluídas" value={o.examsCompleted} />
        <Stat label="Questões respondidas" value={o.questionsAnswered} />
        <Stat label="Taxa de acertos" value={o.accuracy != null ? `${o.accuracy}%` : '—'} hint="Pelo gabarito oficial" />
        <Stat label="Horas estudadas" value={`${o.hoursStudied}h`} hint={`${o.studyDays} dia(s) de estudo`} />
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <h2 className="font-semibold">Progresso por área</h2>
          <div className="mt-4 space-y-3">
            {o.byArea.map((a) => (
              <ProgressBar key={a.area} value={a.percent ?? 0} label={EXAM_AREA_SHORT[a.area as ExamArea]} />
            ))}
          </div>
          <div className="mt-4 flex flex-wrap gap-4 text-sm">
            <span>
              Área mais forte: <strong>{o.strongestArea ? EXAM_AREA_SHORT[o.strongestArea as ExamArea] : '—'}</strong>
            </span>
            <span>
              Precisa de atenção: <strong>{o.weakestArea ? EXAM_AREA_SHORT[o.weakestArea as ExamArea] : '—'}</strong>
            </span>
          </div>
        </Card>

        <Card>
          <h2 className="font-semibold">Redações</h2>
          <p className="mt-2 text-3xl font-semibold">{o.essay.count}</p>
          <p className="text-sm text-muted">
            {o.essay.average != null ? `Média simulada: ${o.essay.average}` : 'Nenhuma correção simulada ainda.'}
          </p>
          {o.essay.series.length >= 2 && (
            <p className="mt-2 text-sm">
              Evolução: {o.essay.series[0].total} → {o.essay.series[o.essay.series.length - 1].total}
            </p>
          )}
          <LinkButton href="/redacao" variant="secondary" className="mt-4">
            Treinar redação
          </LinkButton>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <h2 className="font-semibold">Plano de hoje</h2>
          {data.todayTasks.length === 0 ? (
            <p className="mt-2 text-sm text-muted">
              Nenhuma tarefa para hoje. <Link href="/plano" className="text-primary underline">Gerar plano de estudos</Link>.
            </p>
          ) : (
            <ul className="mt-3 space-y-2 text-sm">
              {data.todayTasks.map((t) => (
                <li key={t.id} className="flex items-center justify-between rounded-md border border-border px-3 py-2">
                  <span className={t.status === 'DONE' ? 'line-through text-muted' : ''}>{t.title}</span>
                  <span className="text-xs text-muted">{t.minutes} min</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
        <Card>
          <h2 className="font-semibold">Continuar estudando</h2>
          {data.continueSession ? (
            <>
              <p className="mt-2 text-sm text-muted">Você tem uma prova em andamento.</p>
              <LinkButton href={`/sessao/${data.continueSession}`} className="mt-3">
                Retomar prova
              </LinkButton>
            </>
          ) : data.lastSession ? (
            <>
              <p className="mt-2 text-sm">
                Última prova: <strong>{data.lastSession.title}</strong>
                {data.lastSession.percent != null && ` — ${data.lastSession.percent}% de acertos`}
              </p>
              <div className="mt-3 flex gap-2">
                <LinkButton href={`/sessao/${data.lastSession.id}/resultado`} variant="secondary">
                  Ver resultado
                </LinkButton>
                <LinkButton href="/provas">Nova prova</LinkButton>
              </div>
            </>
          ) : (
            <LinkButton href="/provas" className="mt-3">
              Escolher uma prova oficial
            </LinkButton>
          )}
          {data.goals.length > 0 && (
            <p className="mt-4 text-xs text-muted">Próxima meta: {data.goals[0].kind.replace('_', ' ').toLowerCase()} — {data.goals[0].target}</p>
          )}
        </Card>
      </div>
    </div>
  );
}
