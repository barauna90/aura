'use client';

import Link from 'next/link';
import { useState } from 'react';
import { EXAM_AREA_SHORT, type ExamArea } from '@sip-enem/shared';
import { useApi } from '@/lib/hooks';
import { dateBR, hhmmss } from '@/lib/format';
import { LineChart } from '@/components/LineChart';
import { Badge, Button, Card, Empty, ErrorBox, Notice, PageHeader, Spinner, Stat } from '@/components/ui';

type Period = '7D' | '30D' | '90D' | 'ALL';

interface Overview {
  examsCompleted: number;
  fullExams: number;
  partialExams: number;
  questionsAnswered: number;
  accuracy: number | null;
  blankRate: number | null;
  changedAnswers: number;
  hoursStudied: number;
  avgSecondsPerQuestion: number | null;
  byArea: Array<{ area: ExamArea; total: number; correct: number; percent: number | null; blankRate: number }>;
  series: Array<{ date: string; sessionId: string; percent: number; byArea: Array<{ area: ExamArea; percent: number }> }>;
  movingAverage: number[];
  essay: { count: number; average: number | null; series: Array<{ date: string; total: number }>; label: string; notice: string };
  scoreNotice: string;
}
interface HistoryItem {
  id: string;
  exam: { title: string; year: number };
  mode: string;
  status: string;
  finishedAt: string | null;
  timeUsedSeconds: number | null;
  device: string | null;
  result: { correct: number; total: number; percent: number } | null;
  essay: { simulatedScore: number | null } | null;
}

const COLORS: Record<ExamArea, string> = { LINGUAGENS: '#1e3a8a', HUMANAS: '#b45309', NATUREZA: '#15803d', MATEMATICA: '#7c3aed', REDACAO: '#be123c' };

export default function PerformancePage() {
  const [period, setPeriod] = useState<Period>('30D');
  const { data, error, loading } = useApi<Overview>(`/analytics/overview?period=${period}`, [period]);
  const { data: history } = useApi<HistoryItem[]>('/sessions/history');
  const { data: weak } = useApi<Array<{ slug: string; name: string; area: ExamArea; errors: number }>>('/analytics/weak-topics');
  const [cmp, setCmp] = useState<string[]>([]);

  const finished = (history ?? []).filter((h) => h.result);

  return (
    <div className="space-y-6">
      <PageHeader
        title="Meu desempenho"
        actions={
          <div className="flex gap-1">
            {(['7D', '30D', '90D', 'ALL'] as Period[]).map((p) => (
              <Button key={p} size="sm" variant={period === p ? 'primary' : 'secondary'} onClick={() => setPeriod(p)}>
                {p === 'ALL' ? 'Tudo' : p.replace('D', ' dias')}
              </Button>
            ))}
          </div>
        }
      />
      {loading && <Spinner />}
      <ErrorBox error={error} />
      {data && (
        <>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Stat label="Acertos" value={data.accuracy != null ? `${data.accuracy}%` : '—'} hint="Pelo gabarito oficial" />
            <Stat label="Em branco" value={data.blankRate != null ? `${data.blankRate}%` : '—'} hint={`${data.changedAnswers} respostas alteradas`} />
            <Stat label="Tempo médio/questão" value={data.avgSecondsPerQuestion != null ? `${data.avgSecondsPerQuestion}s` : '—'} />
            <Stat label="Provas" value={`${data.fullExams} completas · ${data.partialExams} parciais`} />
          </div>

          <Card>
            <h2 className="font-semibold">Mapa de desempenho por área</h2>
            {data.series.length === 0 ? (
              <Empty title="Sem provas concluídas no período." />
            ) : (
              <div className="mt-3">
                <LineChart
                  ariaLabel="Evolução do percentual de acertos por área"
                  series={(['LINGUAGENS', 'HUMANAS', 'NATUREZA', 'MATEMATICA'] as ExamArea[]).map((a) => ({
                    name: EXAM_AREA_SHORT[a],
                    color: COLORS[a],
                    points: data.series.map((s) => s.byArea.find((x) => x.area === a)?.percent ?? 0),
                  }))}
                />
                <p className="mt-2 text-xs text-muted">Média móvel (3 provas): {data.movingAverage.map((m) => `${m}%`).join(' · ')}</p>
              </div>
            )}
          </Card>

          <div className="grid gap-4 md:grid-cols-2">
            <Card>
              <h2 className="font-semibold">Redação — {data.essay.label}</h2>
              {data.essay.series.length ? (
                <LineChart ariaLabel="Evolução da nota simulada de redação" max={1000} series={[{ name: 'Nota simulada', color: COLORS.REDACAO, points: data.essay.series.map((e) => e.total) }]} />
              ) : (
                <p className="mt-2 text-sm text-muted">Nenhuma redação avaliada no período.</p>
              )}
              <p className="mt-2 text-xs text-muted">{data.essay.notice}</p>
            </Card>
            <Card>
              <h2 className="font-semibold">Assuntos com mais erros</h2>
              <p className="text-xs text-muted">Somente questões com classificação pedagógica validada.</p>
              {weak && weak.length === 0 && <p className="mt-2 text-sm text-muted">Ainda não há dados suficientes.</p>}
              <ul className="mt-3 space-y-1 text-sm">
                {(weak ?? []).map((t) => (
                  <li key={t.slug} className="flex justify-between rounded border border-border px-3 py-1.5">
                    <span>
                      <Badge>{EXAM_AREA_SHORT[t.area]}</Badge> {t.name}
                    </span>
                    <span className="text-muted">{t.errors} erro(s)</span>
                  </li>
                ))}
              </ul>
            </Card>
          </div>
          <Notice tone="neutral">{data.scoreNotice}</Notice>
        </>
      )}

      <Card>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 className="font-semibold">Histórico</h2>
          {cmp.length === 2 && (
            <Link href={`/desempenho/comparar?a=${cmp[0]}&b=${cmp[1]}`} className="text-sm text-primary underline">
              Comparar selecionadas
            </Link>
          )}
        </div>
        <p className="text-xs text-muted">Selecione duas provas para comparar. Refazer uma prova mantém o resultado anterior.</p>
        <div className="mt-3 overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="text-left text-xs uppercase text-muted">
              <tr>
                <th className="py-2 pr-2"></th>
                <th className="py-2 pr-3">Prova</th>
                <th className="py-2 pr-3">Modo</th>
                <th className="py-2 pr-3">Data</th>
                <th className="py-2 pr-3">Tempo</th>
                <th className="py-2 pr-3">Acertos</th>
                <th className="py-2 pr-3">Redação</th>
                <th className="py-2 pr-3">Dispositivo</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {finished.map((h) => (
                <tr key={h.id} className="border-t border-border">
                  <td className="py-2 pr-2">
                    <input type="checkbox" aria-label={`Selecionar ${h.exam.title}`} checked={cmp.includes(h.id)} onChange={(e) => setCmp(e.target.checked ? [...cmp, h.id].slice(-2) : cmp.filter((x) => x !== h.id))} />
                  </td>
                  <td className="py-2 pr-3">{h.exam.title}</td>
                  <td className="py-2 pr-3">{h.mode === 'PROVA_REAL' ? 'Prova Real' : 'Estudo'}</td>
                  <td className="py-2 pr-3">{dateBR(h.finishedAt, true)}</td>
                  <td className="py-2 pr-3">{h.timeUsedSeconds != null ? hhmmss(h.timeUsedSeconds) : '—'}</td>
                  <td className="py-2 pr-3">
                    {h.result!.correct}/{h.result!.total} ({h.result!.percent}%)
                  </td>
                  <td className="py-2 pr-3">{h.essay?.simulatedScore ?? '—'}</td>
                  <td className="py-2 pr-3 text-muted">{h.device ?? '—'}</td>
                  <td className="py-2">
                    <Link href={`/sessao/${h.id}/resultado`} className="text-primary underline">
                      Ver
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}
