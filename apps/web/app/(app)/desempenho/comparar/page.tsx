'use client';

import { useSearchParams } from 'next/navigation';
import { Suspense } from 'react';
import { EXAM_AREA_SHORT, type ExamArea } from '@sip-enem/shared';
import { useApi } from '@/lib/hooks';
import { dateBR } from '@/lib/format';
import { Card, ErrorBox, LinkButton, PageHeader, Spinner } from '@/components/ui';

interface Compare {
  before: { exam: { title: string }; finishedAt: string };
  after: { exam: { title: string }; finishedAt: string };
  rows: Array<{ area: ExamArea; before: number | null; after: number | null; delta: number | null }>;
}

function CompareInner() {
  const p = useSearchParams();
  const a = p.get('a');
  const b = p.get('b');
  const { data, error, loading } = useApi<Compare>(a && b ? `/sessions/compare?a=${a}&b=${b}` : null);
  if (loading) return <Spinner />;
  if (error || !data) return <ErrorBox error={error ?? { message: 'Selecione duas provas.' }} />;
  return (
    <div className="space-y-6">
      <PageHeader title="Comparação" subtitle={`${data.before.exam.title} (${dateBR(data.before.finishedAt)}) → ${data.after.exam.title} (${dateBR(data.after.finishedAt)})`} actions={<LinkButton href="/desempenho" variant="secondary">Voltar</LinkButton>} />
      <Card>
        <table className="w-full text-sm">
          <thead className="text-left text-xs uppercase text-muted">
            <tr><th className="py-2">Área</th><th className="py-2">Anterior</th><th className="py-2">Atual</th><th className="py-2">Evolução</th></tr>
          </thead>
          <tbody>
            {data.rows.map((r) => (
              <tr key={r.area} className="border-t border-border">
                <td className="py-2 font-medium">{EXAM_AREA_SHORT[r.area] ?? r.area}{r.area === 'REDACAO' ? ' (nota simulada)' : ' (acertos)'}</td>
                <td className="py-2">{r.before ?? '—'}</td>
                <td className="py-2">{r.after ?? '—'}</td>
                <td className={`py-2 font-semibold ${r.delta == null ? '' : r.delta > 0 ? 'text-success' : r.delta < 0 ? 'text-danger' : ''}`}>
                  {r.delta == null ? '—' : r.delta > 0 ? `+${r.delta}` : r.delta}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  );
}

export default function ComparePage() {
  return (
    <Suspense>
      <CompareInner />
    </Suspense>
  );
}
