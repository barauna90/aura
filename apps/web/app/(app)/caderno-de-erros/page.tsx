'use client';

import { useState } from 'react';
import { EXAM_AREAS, EXAM_AREA_SHORT, type ExamArea } from '@sip-enem/shared';
import { api } from '@/lib/api';
import { useApi } from '@/lib/hooks';
import { dateBR } from '@/lib/format';
import { Badge, Button, Card, Empty, ErrorBox, Field, PageHeader, Select, Spinner, Textarea } from '@/components/ui';

interface Entry {
  id: string;
  note: string | null;
  reviewed: boolean;
  nextReviewAt: string | null;
  question: { id: string; number: number; area: ExamArea; page: number | null; year: number; examTitle: string; official: string | null; topic: string | null; resolution: string | null; resolutionNotice: string | null };
}

export default function ErrorNotebookPage() {
  const [f, setF] = useState({ area: '', year: '', due: false });
  const qs = new URLSearchParams({ ...(f.area ? { area: f.area } : {}), ...(f.year ? { year: f.year } : {}), ...(f.due ? { due: 'true' } : {}) }).toString();
  const { data, error, loading, reload } = useApi<Entry[]>(`/error-notebook?${qs}`, [qs]);
  const [notes, setNotes] = useState<Record<string, string>>({});

  const saveNote = async (id: string) => {
    await api(`/error-notebook/${id}/note`, { method: 'PUT', json: { note: notes[id] ?? '' } });
    reload();
  };
  const review = async (id: string, quality: number) => {
    await api(`/error-notebook/${id}/review`, { method: 'POST', json: { quality } });
    reload();
  };

  const years = [...new Set((data ?? []).map((e) => e.question.year))].sort((a, b) => b - a);

  return (
    <div className="space-y-6">
      <PageHeader title="Meu caderno de erros" subtitle="Questões oficiais que você errou, com revisão por repetição espaçada." />
      <Card className="grid gap-3 sm:grid-cols-3">
        <Field label="Área" id="n-area">
          <Select id="n-area" value={f.area} onChange={(e) => setF({ ...f, area: e.target.value })}>
            <option value="">Todas</option>
            {EXAM_AREAS.filter((a) => a !== 'REDACAO').map((a) => (
              <option key={a} value={a}>
                {EXAM_AREA_SHORT[a]}
              </option>
            ))}
          </Select>
        </Field>
        <Field label="Ano" id="n-year">
          <Select id="n-year" value={f.year} onChange={(e) => setF({ ...f, year: e.target.value })}>
            <option value="">Todos</option>
            {years.map((y) => (
              <option key={y} value={y}>
                {y}
              </option>
            ))}
          </Select>
        </Field>
        <div className="flex items-end">
          <Button variant={f.due ? 'primary' : 'secondary'} onClick={() => setF({ ...f, due: !f.due })}>
            {f.due ? 'Mostrando: para revisar hoje' : 'Só as de hoje'}
          </Button>
        </div>
      </Card>

      {loading && <Spinner />}
      <ErrorBox error={error} />
      {data && data.length === 0 && <Empty title="Nenhuma questão no caderno de erros.">Questões erradas em provas entram aqui automaticamente.</Empty>}

      <ul className="space-y-3">
        {(data ?? []).map((e) => (
          <li key={e.id}>
            <Card>
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap items-center gap-2 text-sm">
                  <Badge>{EXAM_AREA_SHORT[e.question.area]}</Badge>
                  <span className="font-medium">
                    Questão {e.question.number} · ENEM {e.question.year}
                  </span>
                  <span className="text-muted">{e.question.examTitle}</span>
                  {e.question.page && <span className="text-xs text-muted">p.{e.question.page}</span>}
                  {e.question.topic && <Badge tone="primary">{e.question.topic}</Badge>}
                </div>
                <div className="text-xs text-muted">
                  {e.reviewed ? 'Revisada' : 'Não revisada'} · próxima revisão {dateBR(e.nextReviewAt)}
                </div>
              </div>
              <p className="mt-2 text-sm">
                Gabarito oficial: <strong>{e.question.official ?? '—'}</strong>
              </p>
              <p className="mt-1 text-sm text-muted">{e.question.resolution ?? e.question.resolutionNotice}</p>
              <div className="mt-3 grid gap-3 md:grid-cols-[1fr_auto]">
                <div>
                  <Textarea
                    rows={2}
                    placeholder="Minha anotação sobre esta questão…"
                    defaultValue={e.note ?? ''}
                    onChange={(ev) => setNotes({ ...notes, [e.id]: ev.target.value })}
                    aria-label="Anotação"
                  />
                  <Button size="sm" variant="secondary" className="mt-2" onClick={() => saveNote(e.id)}>
                    Salvar anotação
                  </Button>
                </div>
                <div className="text-sm">
                  <p className="mb-1 text-xs text-muted">Como foi rever esta questão?</p>
                  <div className="flex gap-1">
                    {[
                      [1, 'Errei de novo'],
                      [3, 'Com dificuldade'],
                      [5, 'Fácil'],
                    ].map(([q, l]) => (
                      <Button key={q} size="sm" variant="secondary" onClick={() => review(e.id, Number(q))}>
                        {l}
                      </Button>
                    ))}
                  </div>
                </div>
              </div>
            </Card>
          </li>
        ))}
      </ul>
    </div>
  );
}
