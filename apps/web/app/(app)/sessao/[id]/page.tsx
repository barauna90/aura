'use client';

import { useParams, useRouter } from 'next/navigation';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { AnswerOption } from '@sip-enem/shared';
import { api } from '@/lib/api';
import { useApi, useInterval } from '@/lib/hooks';
import { hhmmss } from '@/lib/format';
import { AnswerSheet, type SheetRow } from '@/components/AnswerSheet';
import { PdfViewer } from '@/components/PdfViewer';
import { Button, Card, ErrorBox, Notice, Spinner } from '@/components/ui';

interface SessionState {
  id: string;
  mode: 'PROVA_REAL' | 'ESTUDO';
  status: 'CREATED' | 'IN_PROGRESS' | 'PAUSED' | 'FINISHED' | 'EXPIRED';
  exam: { id: string; title: string; year: number; durationMinutes: number; hasEssay: boolean };
  booklet: { id: string };
  remainingSeconds: number | null;
  serverTime: string;
  answerSheet: { answered: number; blank: number; total: number; answers: SheetRow[] };
  notices: { answerSheetOnly: string; studyMode: string | null };
}

const AUTOSAVE_MS = 4000;
const SYNC_MS = 30000;

export default function SessionRunner() {
  const { id } = useParams<{ id: string }>();
  const router = useRouter();
  const { data, error, loading, reload, setData } = useApi<SessionState>(`/sessions/${id}`);
  const { data: detail } = useApi<{ booklets: Array<{ id: string; pageCount: number }> }>(data ? `/exams/${data.exam.id}` : null, [data?.exam.id]);
  const [rows, setRows] = useState<SheetRow[]>([]);
  const [page, setPage] = useState(1);
  const [remaining, setRemaining] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<{ message: string } | null>(null);
  const [saveState, setSaveState] = useState<'saved' | 'pending' | 'saving' | 'offline'>('saved');
  const dirty = useRef<Map<string, AnswerOption | null>>(new Map());
  const lastSync = useRef<number>(Date.now());

  // Sincroniza estado do servidor → local (cronômetro é do servidor).
  useEffect(() => {
    if (!data) return;
    setRows(data.answerSheet.answers);
    setRemaining(data.remainingSeconds);
    lastSync.current = Date.now();
    if (data.status === 'FINISHED' || data.status === 'EXPIRED') router.replace(`/sessao/${id}/resultado`);
  }, [data, id, router]);

  // Tique local apenas para exibição; a fonte da verdade é reconciliada a cada SYNC_MS.
  useInterval(() => {
    if (data?.status !== 'IN_PROGRESS' || remaining === null) return;
    setRemaining((r) => (r === null ? null : Math.max(0, r - 1)));
  }, 1000);

  useInterval(() => {
    if (data?.status === 'IN_PROGRESS') reload();
  }, SYNC_MS);

  useEffect(() => {
    if (remaining === 0 && data?.status === 'IN_PROGRESS') reload();
  }, [remaining, data?.status, reload]);

  const flush = useCallback(async () => {
    if (dirty.current.size === 0 || !data || data.status !== 'IN_PROGRESS') return;
    const batch = [...dirty.current.entries()].map(([questionId, option]) => ({ questionId, option }));
    dirty.current.clear();
    setSaveState('saving');
    try {
      const r = await api<{ remainingSeconds: number | null }>(`/sessions/${id}/answers`, { method: 'POST', json: { answers: batch } });
      setRemaining(r.remainingSeconds);
      setSaveState('saved');
    } catch (e) {
      // Reenfileira para nova tentativa; nada é perdido localmente.
      for (const b of batch) if (!dirty.current.has(b.questionId)) dirty.current.set(b.questionId, b.option);
      setSaveState('offline');
      if ((e as { status?: number }).status === 403) reload();
    }
  }, [data, id, reload]);

  useInterval(flush, AUTOSAVE_MS);
  useEffect(() => {
    const onHide = () => {
      if (document.visibilityState === 'hidden') flush();
    };
    document.addEventListener('visibilitychange', onHide);
    window.addEventListener('beforeunload', flush);
    return () => {
      document.removeEventListener('visibilitychange', onHide);
      window.removeEventListener('beforeunload', flush);
    };
  }, [flush]);

  // Persistência local de emergência (queda de conexão / fechamento).
  useEffect(() => {
    if (!rows.length) return;
    try {
      localStorage.setItem(`sip.sheet.${id}`, JSON.stringify(rows));
    } catch {
      /* ignore */
    }
  }, [rows, id]);

  const mark = (questionId: string, option: AnswerOption | null) => {
    setRows((rs) => rs.map((r) => (r.questionId === questionId ? { ...r, option } : r)));
    dirty.current.set(questionId, option);
    setSaveState('pending');
  };

  const action = async (path: string) => {
    setBusy(true);
    setErr(null);
    try {
      await flush();
      const s = await api<SessionState>(`/sessions/${id}/${path}`, { method: 'POST' });
      setData(s);
    } catch (e) {
      setErr(e as { message: string });
    } finally {
      setBusy(false);
    }
  };

  const finish = async () => {
    const blank = rows.filter((r) => !r.option).length;
    const ok = window.confirm(
      `Encerrar a prova agora?\n\n${blank} questão(ões) em branco.\n${data?.exam.hasEssay ? 'Após encerrar você poderá enviar a redação para avaliação.' : ''}\nEsta ação não pode ser desfeita.`,
    );
    if (!ok) return;
    setBusy(true);
    try {
      await flush();
      await api(`/sessions/${id}/finish`, { method: 'POST' });
      router.replace(`/sessao/${id}/resultado`);
    } catch (e) {
      setErr(e as { message: string });
      setBusy(false);
    }
  };

  if (loading && !data) return <Spinner />;
  if (error || !data) return <ErrorBox error={error} />;

  const pageCount = detail?.booklets.find((b) => b.id === data.booklet.id)?.pageCount ?? 1;
  const real = data.mode === 'PROVA_REAL';

  if (data.status === 'CREATED') {
    return (
      <Card className="mx-auto max-w-2xl space-y-4">
        <h1 className="text-xl font-semibold">{data.exam.title}</h1>
        <p className="text-sm text-muted">
          {real ? 'Modo Prova Real' : 'Modo Estudo'} · Tempo total: {hhmmss(data.exam.durationMinutes * 60)}
        </p>
        <Notice tone="primary">{data.notices.answerSheetOnly}</Notice>
        {real ? (
          <Notice tone="warning" title="Ao iniciar, o cronômetro não pode ser pausado, reiniciado nem estendido.">
            O tempo continua contando mesmo se você fechar a página. A prova encerra automaticamente quando o tempo terminar.
          </Notice>
        ) : (
          <Notice tone="warning">{data.notices.studyMode}</Notice>
        )}
        <ErrorBox error={err} />
        <Button size="lg" disabled={busy} onClick={() => action('start')}>
          Iniciar agora
        </Button>
      </Card>
    );
  }

  const timerTone = remaining !== null && remaining < 600 ? 'text-danger' : remaining !== null && remaining < 1800 ? 'text-warning' : 'text-text';

  return (
    <div className="-mx-4 -my-6 flex h-[calc(100vh-4rem)] flex-col md:-mx-8 md:-my-8 lg:h-screen">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-surface px-4 py-2">
        <div className="min-w-0">
          <p className="truncate text-sm font-medium">{data.exam.title}</p>
          <p className="text-xs text-muted">
            {real ? 'Modo Prova Real' : 'Modo Estudo'} ·{' '}
            {saveState === 'saved' ? 'Cartão salvo' : saveState === 'saving' ? 'Salvando…' : saveState === 'pending' ? 'Alterações pendentes' : 'Sem conexão — salvo localmente'}
          </p>
        </div>
        <div className="flex items-center gap-3">
          <div className="text-right" aria-live="polite">
            <p className="text-[10px] uppercase tracking-wide text-muted">Tempo restante</p>
            <p className={`font-mono text-2xl font-semibold tabular-nums ${timerTone}`}>{remaining !== null ? hhmmss(remaining) : '--:--:--'}</p>
          </div>
          {!real && data.status === 'IN_PROGRESS' && (
            <Button variant="secondary" size="sm" disabled={busy} onClick={() => action('pause')}>
              Pausar
            </Button>
          )}
          {!real && data.status === 'PAUSED' && (
            <Button size="sm" disabled={busy} onClick={() => action('resume')}>
              Continuar
            </Button>
          )}
          <Button variant="danger" size="sm" disabled={busy || data.status === 'PAUSED'} onClick={finish}>
            Encerrar prova
          </Button>
        </div>
      </div>
      {err && (
        <div className="px-4 py-2">
          <ErrorBox error={err} />
        </div>
      )}
      {data.status === 'PAUSED' ? (
        <div className="flex flex-1 items-center justify-center p-6">
          <Notice tone="warning" title="Prova pausada">
            O caderno e o cartão-resposta ficam ocultos enquanto a sessão está pausada.
          </Notice>
        </div>
      ) : (
        <div className="grid flex-1 min-h-0 grid-cols-1 md:grid-cols-[1fr_340px]">
          <section aria-label="Caderno oficial" className="min-h-[50vh] md:min-h-0 border-b border-border md:border-b-0 md:border-r">
            <PdfViewer bookletId={data.booklet.id} pageCount={pageCount} page={page} onPage={setPage} />
          </section>
          <section aria-label="Cartão-resposta digital" className="min-h-0 bg-surface">
            <AnswerSheet rows={rows} locked={data.status !== 'IN_PROGRESS'} onMark={mark} />
          </section>
        </div>
      )}
    </div>
  );
}
