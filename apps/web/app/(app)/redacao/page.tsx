'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState } from 'react';
import { DISCLAIMERS } from '@sip-enem/shared';
import { api } from '@/lib/api';
import { useApi } from '@/lib/hooks';
import { dateBR } from '@/lib/format';
import { Badge, Button, Card, Empty, ErrorBox, Notice, PageHeader, Spinner } from '@/components/ui';

interface Prompt {
  id: string;
  theme: string;
  maxLines: number;
  exam: { title: string; edition: { year: number } };
}
interface Essay {
  id: string;
  status: string;
  createdAt: string;
  submittedAt: string | null;
  sessionId: string | null;
  prompt: { theme: string; exam: { edition: { year: number } } };
  finalResult: { total: number } | null;
}

export default function EssaysPage() {
  const router = useRouter();
  const { data: prompts, loading } = useApi<Prompt[]>('/essays/prompts');
  const { data: essays, reload } = useApi<Essay[]>('/essays');
  const [busy, setBusy] = useState<string | null>(null);
  const [err, setErr] = useState<{ message: string } | null>(null);

  const start = async (promptId: string) => {
    setBusy(promptId);
    setErr(null);
    try {
      const e = await api<{ id: string }>('/essays', { method: 'POST', json: { promptId } });
      router.push(`/redacao/${e.id}`);
    } catch (e) {
      setErr(e as { message: string });
      setBusy(null);
      reload();
    }
  };

  return (
    <div className="space-y-6">
      <PageHeader title="Redação" subtitle="Treine com propostas oficiais e receba uma correção simulada pelas cinco competências." />
      <Notice tone="neutral">{DISCLAIMERS.ESSAY_EVALUATION}</Notice>
      <ErrorBox error={err} />

      <section>
        <h2 className="mb-3 font-semibold">Propostas oficiais</h2>
        {loading && <Spinner />}
        {prompts && prompts.length === 0 && <Empty title="Nenhuma proposta oficial verificada ainda." />}
        <div className="grid gap-3 md:grid-cols-2">
          {(prompts ?? []).map((p) => (
            <Card key={p.id}>
              <p className="text-xs text-muted">ENEM {p.exam.edition.year} · {p.exam.title}</p>
              <p className="mt-1 font-medium">{p.theme}</p>
              <p className="mt-1 text-xs text-muted">Até {p.maxLines} linhas</p>
              <Button className="mt-3" size="sm" disabled={busy === p.id} onClick={() => start(p.id)}>
                Escrever
              </Button>
            </Card>
          ))}
        </div>
      </section>

      <section>
        <h2 className="mb-3 font-semibold">Minhas redações</h2>
        {essays && essays.length === 0 && <Empty title="Você ainda não escreveu nenhuma redação." />}
        <ul className="space-y-2">
          {(essays ?? []).map((e) => (
            <li key={e.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-surface px-4 py-3 text-sm">
              <div>
                <p className="font-medium">{e.prompt.theme}</p>
                <p className="text-xs text-muted">
                  ENEM {e.prompt.exam.edition.year} · {dateBR(e.submittedAt ?? e.createdAt)} {e.sessionId ? '· prova completa' : '· treino avulso'}
                </p>
              </div>
              <div className="flex items-center gap-3">
                {e.finalResult && <span className="font-semibold">{e.finalResult.total}</span>}
                <Badge tone={e.status === 'EVALUATED' ? 'success' : e.status === 'FAILED' ? 'danger' : 'primary'}>{e.status}</Badge>
                <Link href={e.status === 'DRAFT' ? `/redacao/${e.id}` : `/redacao/${e.id}/relatorio`} className="text-primary underline">
                  {e.status === 'DRAFT' ? 'Continuar' : 'Relatório'}
                </Link>
              </div>
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
