'use client';

import { useParams, useRouter } from 'next/navigation';
import { useCallback, useEffect, useRef, useState } from 'react';
import { DISCLAIMERS } from '@sip-enem/shared';
import { api } from '@/lib/api';
import { useApi, useInterval } from '@/lib/hooks';
import { Button, Card, ErrorBox, Notice, PageHeader, Spinner } from '@/components/ui';

interface EssayReport {
  id: string;
  status: string;
  promptId: string;
  prompt: { theme: string; year: number; examTitle: string };
  text: string | null;
  lineCount: number | null;
}
interface PromptDetail {
  theme: string;
  maxLines: number;
  motivatingTexts: Array<{ title?: string; body: string; sourceNote?: string }>;
  source: { sourceUrl: string };
}

/**
 * Folha de redação: sem corretor ortográfico, sem autocompletar, sem IA, sem
 * sugestões. Limite visual de linhas equivalente à folha oficial.
 */
export default function EssayEditor() {
  const { id } = useParams<{ id: string }>();
  const router = useRouter();
  const { data, error, loading } = useApi<EssayReport>(`/essays/${id}/report`);
  const { data: prompt } = useApi<PromptDetail>(data ? `/essays/prompts/${data.promptId}` : null, [data?.promptId]);
  const [tab, setTab] = useState<'proposta' | 'rascunho' | 'folha'>('proposta');
  const [draft, setDraft] = useState('');
  const [sheet, setSheet] = useState('');
  const [saved, setSaved] = useState(true);
  const [err, setErr] = useState<{ message: string } | null>(null);
  const [busy, setBusy] = useState(false);
  const lastSaved = useRef('');

  useEffect(() => {
    if (!data) return;
    if (data.status !== 'DRAFT') router.replace(`/redacao/${id}/relatorio`);
    setSheet(data.text ?? '');
    lastSaved.current = data.text ?? '';
    try {
      setDraft(localStorage.getItem(`sip.essay.draft.${id}`) ?? '');
    } catch {
      /* ignore */
    }
  }, [data, id, router]);

  const lines = sheet.split('\n').filter((l) => l.trim()).length;
  const maxLines = prompt?.maxLines ?? 30;

  const save = useCallback(async () => {
    if (sheet === lastSaved.current) return;
    try {
      await api(`/essays/${id}/draft`, { method: 'PUT', json: { draftText: sheet } });
      lastSaved.current = sheet;
      setSaved(true);
      setErr(null);
    } catch (e) {
      setErr(e as { message: string });
    }
  }, [sheet, id]);

  useInterval(save, 5000);
  useEffect(() => {
    try {
      localStorage.setItem(`sip.essay.draft.${id}`, draft);
    } catch {
      /* ignore */
    }
  }, [draft, id]);

  const submit = async () => {
    if (!window.confirm('Enviar a redação para avaliação simulada? Depois do envio o texto não pode ser alterado.')) return;
    setBusy(true);
    try {
      await save();
      await api(`/essays/${id}/submit`, { method: 'POST' });
      router.replace(`/redacao/${id}/relatorio`);
    } catch (e) {
      setErr(e as { message: string });
      setBusy(false);
    }
  };

  if (loading) return <Spinner />;
  if (error || !data) return <ErrorBox error={error} />;

  return (
    <div className="space-y-4">
      <PageHeader title="Redação" subtitle={`${data.prompt.examTitle} · ENEM ${data.prompt.year}`} />
      <div className="flex flex-wrap gap-1 border-b border-border">
        {(['proposta', 'rascunho', 'folha'] as const).map((t) => (
          <button key={t} onClick={() => setTab(t)} aria-selected={tab === t} role="tab" className={`px-4 py-2 text-sm ${tab === t ? 'border-b-2 border-primary font-medium text-primary' : 'text-muted'}`}>
            {t === 'proposta' ? 'Proposta e textos motivadores' : t === 'rascunho' ? 'Rascunho' : 'Folha de redação'}
          </button>
        ))}
      </div>

      {tab === 'proposta' && (
        <Card className="space-y-4">
          <h2 className="text-lg font-semibold">{data.prompt.theme}</h2>
          {prompt ? (
            <>
              {prompt.motivatingTexts.map((t, i) => (
                <article key={i} className="rounded-md border border-border bg-surface-2 p-4 text-sm">
                  <h3 className="font-medium">
                    Texto {i + 1}
                    {t.title ? ` — ${t.title}` : ''}
                  </h3>
                  <p className="mt-2 whitespace-pre-wrap">{t.body}</p>
                  {t.sourceNote && <p className="mt-2 text-xs text-muted">{t.sourceNote}</p>}
                </article>
              ))}
              <p className="text-xs text-muted">
                Proposta transcrita do documento oficial:{' '}
                <a href={prompt.source.sourceUrl} className="text-primary underline" target="_blank" rel="noreferrer">
                  fonte Inep
                </a>
              </p>
            </>
          ) : (
            <Spinner label="Carregando proposta oficial…" />
          )}
        </Card>
      )}

      {tab === 'rascunho' && (
        <Card>
          <p className="mb-2 text-sm text-muted">Espaço livre para planejar. O rascunho não é enviado nem avaliado.</p>
          <textarea
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            spellCheck={false}
            autoComplete="off"
            autoCorrect="off"
            autoCapitalize="off"
            rows={18}
            className="w-full rounded-md border border-border bg-surface-2 p-3 font-mono text-sm"
            aria-label="Folha de rascunho"
          />
        </Card>
      )}

      {tab === 'folha' && (
        <Card>
          <div className="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm">
            <span className={lines > maxLines ? 'text-danger' : 'text-muted'}>
              {lines} / {maxLines} linhas
            </span>
            <span className="text-xs text-muted">{saved ? 'Salvo automaticamente' : 'Salvando…'}</span>
          </div>
          <textarea
            value={sheet}
            onChange={(e) => {
              setSheet(e.target.value);
              setSaved(false);
            }}
            spellCheck={false}
            autoComplete="off"
            autoCorrect="off"
            autoCapitalize="off"
            rows={maxLines + 1}
            className="essay-sheet w-full resize-none rounded-md border border-border bg-surface px-4 text-base"
            aria-label="Folha de redação"
            aria-describedby="folha-hint"
          />
          <p id="folha-hint" className="mt-2 text-xs text-muted">
            Sem corretor ortográfico, sem sugestões e sem IA — como na prova. Uma linha da folha corresponde a uma quebra de linha aqui.
          </p>
          <ErrorBox error={err} />
          <div className="mt-4 flex flex-wrap items-center gap-3">
            <Button onClick={submit} disabled={busy || lines === 0 || lines > maxLines}>
              Enviar para avaliação simulada
            </Button>
            <Button variant="secondary" onClick={save}>
              Salvar agora
            </Button>
          </div>
          <Notice tone="neutral">{DISCLAIMERS.ESSAY_EVALUATION}</Notice>
        </Card>
      )}
    </div>
  );
}
