'use client';

import Link from 'next/link';
import { useState } from 'react';
import { EXAM_APPLICATION_LABEL, EXAM_AREAS, EXAM_AREA_SHORT, type ExamApplication, type ExamArea } from '@sip-enem/shared';
import { useApi } from '@/lib/hooks';
import { minutesLabel } from '@/lib/format';
import { Badge, Card, Empty, ErrorBox, Field, PageHeader, Select, Spinner } from '@/components/ui';

interface CatalogExam {
  id: string;
  year: number;
  title: string;
  application: ExamApplication;
  day: number;
  durationMinutes: number;
  areas: ExamArea[];
  hasEssay: boolean;
  hasForeignLanguage: boolean;
  structureNote: string;
  isFreeSample: boolean;
  source: { sourceUrl: string; documentVersion: string };
  booklets: Array<{ id: string; label: string; color: string }>;
}

export default function ExamsCatalog() {
  const [f, setF] = useState<{ year: string; day: string; area: string; application: string }>({ year: '', day: '', area: '', application: '' });
  const qs = new URLSearchParams(Object.entries(f).filter(([, v]) => v)).toString();
  const { data: years } = useApi<number[]>('/exams/years');
  const { data, error, loading } = useApi<CatalogExam[]>(`/exams?${qs}`, [qs]);

  return (
    <div>
      <PageHeader title="Provas anteriores" subtitle="Cadernos e gabaritos oficiais publicados pelo Inep. Cada prova mantém a estrutura da sua edição." />

      <Card className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Field label="Ano" id="f-year">
          <Select id="f-year" value={f.year} onChange={(e) => setF({ ...f, year: e.target.value })}>
            <option value="">Todos</option>
            {(years ?? []).map((y) => (
              <option key={y} value={y}>
                {y}
              </option>
            ))}
          </Select>
        </Field>
        <Field label="Dia" id="f-day">
          <Select id="f-day" value={f.day} onChange={(e) => setF({ ...f, day: e.target.value })}>
            <option value="">Todos</option>
            <option value="1">1º dia</option>
            <option value="2">2º dia</option>
          </Select>
        </Field>
        <Field label="Área" id="f-area">
          <Select id="f-area" value={f.area} onChange={(e) => setF({ ...f, area: e.target.value })}>
            <option value="">Todas</option>
            {EXAM_AREAS.map((a) => (
              <option key={a} value={a}>
                {EXAM_AREA_SHORT[a]}
              </option>
            ))}
          </Select>
        </Field>
        <Field label="Aplicação" id="f-app">
          <Select id="f-app" value={f.application} onChange={(e) => setF({ ...f, application: e.target.value })}>
            <option value="">Todas</option>
            {Object.entries(EXAM_APPLICATION_LABEL).map(([k, v]) => (
              <option key={k} value={k}>
                {v}
              </option>
            ))}
          </Select>
        </Field>
      </Card>

      {loading && <Spinner />}
      <ErrorBox error={error} />
      {data && data.length === 0 && (
        <Empty title="Nenhuma prova oficial publicada para estes filtros.">
          As provas passam por importação a partir dos PDFs do Inep e por dupla revisão humana antes de aparecerem aqui.
        </Empty>
      )}
      <ul className="grid gap-4 md:grid-cols-2">
        {(data ?? []).map((e) => (
          <li key={e.id}>
            <Link href={`/provas/${e.id}`} className="block h-full rounded-lg border border-border bg-surface p-5 transition hover:border-primary">
              <div className="flex items-start justify-between gap-2">
                <div>
                  <p className="text-xs text-muted">
                    ENEM {e.year} · {EXAM_APPLICATION_LABEL[e.application]} · {e.day}º dia
                  </p>
                  <h2 className="mt-1 font-semibold">{e.title}</h2>
                </div>
                {e.isFreeSample && <Badge tone="success">Gratuita</Badge>}
              </div>
              <div className="mt-3 flex flex-wrap gap-1.5">
                {e.areas.map((a) => (
                  <Badge key={a}>{EXAM_AREA_SHORT[a]}</Badge>
                ))}
                {e.hasForeignLanguage && <Badge tone="primary">Inglês/Espanhol</Badge>}
              </div>
              <p className="mt-3 text-sm text-muted">
                Duração oficial: {minutesLabel(e.durationMinutes)} · {e.booklets.length} caderno(s)
              </p>
              <p className="mt-1 text-xs text-muted">Fonte: Inep · versão {e.source.documentVersion}</p>
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}
