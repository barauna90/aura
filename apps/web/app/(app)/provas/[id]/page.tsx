'use client';

import { useParams, useRouter } from 'next/navigation';
import { useState } from 'react';
import { DISCLAIMERS, EXAM_AREA_SHORT, type ExamArea } from '@sip-enem/shared';
import { api } from '@/lib/api';
import { useApi, useIsMobile } from '@/lib/hooks';
import { minutesLabel } from '@/lib/format';
import { Badge, Button, Card, ErrorBox, Notice, PageHeader, Spinner } from '@/components/ui';

interface Detail {
  id: string;
  title: string;
  durationMinutes: number;
  areas: ExamArea[];
  hasEssay: boolean;
  hasForeignLanguage: boolean;
  structureNote: string;
  edition: { year: number };
  source: { sourceUrl: string; documentVersion: string; checksum: string; lastValidation: string | null };
  booklets: Array<{ id: string; label: string; color: string; pageCount: number; _count: { questions: number } }>;
  essayPrompt: { theme: string } | null;
  notices: { answerSheetOnly: string; mobile: string };
}

export default function ExamDetail() {
  const { id } = useParams<{ id: string }>();
  const router = useRouter();
  const mobile = useIsMobile();
  const { data, error, loading } = useApi<Detail>(`/exams/${id}`);
  const [mode, setMode] = useState<'PROVA_REAL' | 'ESTUDO'>('PROVA_REAL');
  const [language, setLanguage] = useState<'INGLES' | 'ESPANHOL' | ''>('');
  const [bookletId, setBookletId] = useState('');
  const [areas, setAreas] = useState<ExamArea[]>([]);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<{ message: string } | null>(null);

  if (loading) return <Spinner />;
  if (error || !data) return <ErrorBox error={error} />;
  const booklet = bookletId || data.booklets[0]?.id;

  const start = async () => {
    setBusy(true);
    setErr(null);
    try {
      const s = await api<{ id: string }>('/sessions', {
        method: 'POST',
        json: {
          examId: data.id,
          bookletId: booklet,
          mode,
          language: data.hasForeignLanguage ? language || undefined : undefined,
          selectedAreas: mode === 'ESTUDO' && areas.length ? areas : undefined,
          device: mobile ? 'mobile' : 'desktop',
        },
      });
      router.push(`/sessao/${s.id}`);
    } catch (e) {
      setErr(e as { message: string });
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-6">
      <PageHeader title={data.title} subtitle={`ENEM ${data.edition.year} · Duração oficial desta edição: ${minutesLabel(data.durationMinutes)}`} />

      <Notice tone="neutral">{data.structureNote}</Notice>
      {mobile && <Notice tone="warning">{data.notices.mobile}</Notice>}

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2 space-y-5">
          <fieldset>
            <legend className="font-semibold">Modo de realização</legend>
            <div className="mt-3 grid gap-3 md:grid-cols-2">
              <label className={`cursor-pointer rounded-lg border p-4 ${mode === 'PROVA_REAL' ? 'border-primary bg-primary-soft' : 'border-border'}`}>
                <input type="radio" name="mode" className="sr-only" checked={mode === 'PROVA_REAL'} onChange={() => setMode('PROVA_REAL')} />
                <p className="font-medium">Modo Prova Real</p>
                <p className="mt-1 text-sm text-muted">
                  Cronômetro oficial, sem pausa, sem dicas, sem IA, sem correção durante a prova. Encerramento automático ao fim do tempo.
                </p>
              </label>
              <label className={`cursor-pointer rounded-lg border p-4 ${mode === 'ESTUDO' ? 'border-primary bg-primary-soft' : 'border-border'}`}>
                <input type="radio" name="mode" className="sr-only" checked={mode === 'ESTUDO'} onChange={() => setMode('ESTUDO')} />
                <p className="font-medium">Modo Estudo</p>
                <p className="mt-1 text-sm text-muted">Pausar, continuar depois, escolher áreas, marcar questões e fazer anotações.</p>
                <p className="mt-2 text-xs font-medium text-warning">{DISCLAIMERS.STUDY_MODE_NOTICE}</p>
              </label>
            </div>
          </fieldset>

          {data.hasForeignLanguage && (
            <fieldset>
              <legend className="font-semibold">Língua estrangeira</legend>
              <p className="text-sm text-muted">Somente as questões da língua escolhida serão corrigidas.</p>
              <div className="mt-2 flex gap-2">
                {(['INGLES', 'ESPANHOL'] as const).map((l) => (
                  <Button key={l} type="button" variant={language === l ? 'primary' : 'secondary'} onClick={() => setLanguage(l)}>
                    {l === 'INGLES' ? 'Inglês' : 'Espanhol'}
                  </Button>
                ))}
              </div>
            </fieldset>
          )}

          {data.booklets.length > 1 && (
            <fieldset>
              <legend className="font-semibold">Caderno</legend>
              <div className="mt-2 flex flex-wrap gap-2">
                {data.booklets.map((b) => (
                  <Button key={b.id} type="button" variant={booklet === b.id ? 'primary' : 'secondary'} onClick={() => setBookletId(b.id)}>
                    {b.label}
                  </Button>
                ))}
              </div>
            </fieldset>
          )}

          {mode === 'ESTUDO' && (
            <fieldset>
              <legend className="font-semibold">Resolver apenas algumas áreas (opcional)</legend>
              <div className="mt-2 flex flex-wrap gap-2">
                {data.areas
                  .filter((a) => a !== 'REDACAO')
                  .map((a) => (
                    <Button key={a} type="button" size="sm" variant={areas.includes(a) ? 'primary' : 'secondary'} onClick={() => setAreas((s) => (s.includes(a) ? s.filter((x) => x !== a) : [...s, a]))}>
                      {EXAM_AREA_SHORT[a]}
                    </Button>
                  ))}
              </div>
            </fieldset>
          )}

          <Notice tone="primary" title="Sobre o cartão-resposta">
            {data.notices.answerSheetOnly} Você navega pelo caderno oficial à esquerda e transfere as respostas para o cartão-resposta à direita.
          </Notice>

          <ErrorBox error={err} />
          <Button size="lg" onClick={start} disabled={busy || (data.hasForeignLanguage && !language)}>
            {busy ? 'Preparando…' : mode === 'PROVA_REAL' ? 'Iniciar prova real' : 'Iniciar modo estudo'}
          </Button>
        </Card>

        <Card className="space-y-3 text-sm">
          <h2 className="font-semibold">Proveniência</h2>
          <p>
            <span className="text-muted">Fonte:</span>{' '}
            <a href={data.source.sourceUrl} target="_blank" rel="noreferrer" className="break-all text-primary underline">
              Inep/MEC
            </a>
          </p>
          <p>
            <span className="text-muted">Versão do documento:</span> {data.source.documentVersion}
          </p>
          <p className="break-all">
            <span className="text-muted">Checksum:</span> {data.source.checksum.slice(0, 16)}…
          </p>
          <p>
            <span className="text-muted">Status:</span> <Badge tone="success">Verificada</Badge>
          </p>
          <hr className="border-border" />
          {data.booklets.map((b) => (
            <p key={b.id}>
              {b.label}: {b.pageCount} páginas · {b._count.questions} questões
            </p>
          ))}
          {data.hasEssay && data.essayPrompt && (
            <p>
              <span className="text-muted">Redação:</span> proposta oficial incluída
            </p>
          )}
        </Card>
      </div>
    </div>
  );
}
