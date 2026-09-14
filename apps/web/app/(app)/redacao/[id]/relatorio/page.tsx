'use client';

import { useParams } from 'next/navigation';
import { useApi, useInterval } from '@/lib/hooks';
import { Badge, Card, ErrorBox, LinkButton, Notice, PageHeader, Spinner, Stat } from '@/components/ui';

interface Report {
  id: string;
  status: string;
  prompt: { theme: string; year: number; examTitle: string };
  text: string | null;
  scoreLabel: string;
  simulatedTotal: number | null;
  competencies: Array<{
    competency: number;
    label: string;
    score: number | null;
    analyses: Array<{ evaluator: string; score: number; justification: string; problematicExcerpts: string[] }>;
  }>;
  positives: string[];
  improvements: string[];
  checklist: Array<{ item: string; ok: boolean }>;
  studyRecommendation: { focusCompetencies: number[]; suggestions: string[]; nextStep: string } | null;
  usedThirdEvaluator: boolean;
  consistencyFlags: string[];
  notice: string;
  improveParagraphOffer: string | null;
}

export default function EssayReportPage() {
  const { id } = useParams<{ id: string }>();
  const { data, error, loading, reload } = useApi<Report>(`/essays/${id}/report`);
  useInterval(reload, data && (data.status === 'SUBMITTED' || data.status === 'EVALUATING') ? 5000 : null);

  if (loading && !data) return <Spinner />;
  if (error || !data) return <ErrorBox error={error} />;

  const pending = data.status === 'SUBMITTED' || data.status === 'EVALUATING';

  return (
    <div className="space-y-6">
      <PageHeader
        title="Relatório da redação"
        subtitle={`${data.prompt.theme} · ENEM ${data.prompt.year}`}
        actions={
          <LinkButton href="/redacao" variant="secondary">
            Minhas redações
          </LinkButton>
        }
      />
      <Notice tone="neutral">{data.notice}</Notice>

      {pending && (
        <Notice tone="primary" title="Avaliação em andamento">
          Dois avaliadores independentes analisam sua redação pelas cinco competências. Se houver divergência, um terceiro avaliador é acionado. Esta página atualiza automaticamente.
        </Notice>
      )}
      {data.status === 'FAILED' && <Notice tone="danger">A avaliação falhou. Nossa equipe foi notificada; tente reenviar mais tarde.</Notice>}

      {data.status === 'EVALUATED' && (
        <>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <Stat label={data.scoreLabel} value={data.simulatedTotal ?? '—'} hint={data.usedThirdEvaluator ? 'Com terceiro avaliador' : 'Dois avaliadores'} />
            {data.competencies.map((c) => (
              <Stat key={c.competency} label={`Competência ${c.competency}`} value={c.score ?? '—'} />
            ))}
          </div>

          <div className="space-y-4">
            {data.competencies.map((c) => (
              <Card key={c.competency}>
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                  <h2 className="font-semibold">
                    Competência {c.competency} <span className="ml-2 text-lg">{c.score}</span>
                  </h2>
                  <div className="flex gap-1">
                    {c.analyses.map((a) => (
                      <Badge key={a.evaluator}>
                        Avaliador {a.evaluator}: {a.score}
                      </Badge>
                    ))}
                  </div>
                </div>
                <p className="mt-1 text-sm text-muted">{c.label}</p>
                <div className="mt-3 grid gap-3 md:grid-cols-2">
                  {c.analyses.map((a) => (
                    <div key={a.evaluator} className="rounded-md border border-border bg-surface-2 p-3 text-sm">
                      <p className="text-xs font-medium uppercase text-muted">Avaliador {a.evaluator}</p>
                      <p className="mt-1">{a.justification || '—'}</p>
                      {a.problematicExcerpts.length > 0 && (
                        <ul className="mt-2 space-y-1">
                          {a.problematicExcerpts.map((t, i) => (
                            <li key={i} className="border-l-2 border-warning pl-2 italic text-muted">
                              “{t}”
                            </li>
                          ))}
                        </ul>
                      )}
                    </div>
                  ))}
                </div>
              </Card>
            ))}
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <Card>
              <h2 className="font-semibold">Principais pontos positivos</h2>
              <ul className="mt-2 list-disc space-y-1 pl-5 text-sm">{data.positives.map((p) => <li key={p}>{p}</li>)}</ul>
            </Card>
            <Card>
              <h2 className="font-semibold">Principais erros e o que melhorar</h2>
              <ul className="mt-2 list-disc space-y-1 pl-5 text-sm">{data.improvements.map((p) => <li key={p}>{p}</li>)}</ul>
            </Card>
            <Card>
              <h2 className="font-semibold">Checklist de evolução</h2>
              <ul className="mt-2 space-y-1 text-sm">
                {data.checklist.map((c) => (
                  <li key={c.item} className="flex items-center gap-2">
                    <span aria-hidden className={c.ok ? 'text-success' : 'text-muted'}>{c.ok ? '✓' : '○'}</span>
                    <span className="sr-only">{c.ok ? 'atingido' : 'pendente'}</span>
                    {c.item}
                  </li>
                ))}
              </ul>
            </Card>
            <Card>
              <h2 className="font-semibold">Plano de estudo recomendado</h2>
              {data.studyRecommendation && (
                <>
                  <ul className="mt-2 list-disc space-y-1 pl-5 text-sm">{data.studyRecommendation.suggestions.map((s) => <li key={s}>{s}</li>)}</ul>
                  <p className="mt-2 text-sm text-muted">{data.studyRecommendation.nextStep}</p>
                </>
              )}
              {data.improveParagraphOffer && <p className="mt-3 text-xs text-muted">{data.improveParagraphOffer}</p>}
            </Card>
          </div>
        </>
      )}

      <Card>
        <h2 className="font-semibold">Seu texto</h2>
        <p className="essay-sheet mt-3 whitespace-pre-wrap px-2 text-base">{data.text}</p>
      </Card>
    </div>
  );
}
