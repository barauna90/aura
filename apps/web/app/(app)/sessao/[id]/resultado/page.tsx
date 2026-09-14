'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { Fragment, useState } from 'react';
import { EXAM_AREA_SHORT, type ExamArea } from '@sip-enem/shared';
import { api } from '@/lib/api';
import { useApi } from '@/lib/hooks';
import { hhmmss } from '@/lib/format';
import { Badge, Button, Card, ErrorBox, LinkButton, Notice, PageHeader, ProgressBar, Spinner, Stat } from '@/components/ui';

interface Result {
  session: { id: string; mode: string; status: string; language: string | null; finishedAt: string; exam: { id: string; title: string; year: number; hasEssay: boolean } };
  objective: {
    totalQuestions: number;
    correct: number;
    wrong: number;
    blank: number;
    percent: number;
    timeUsedSeconds: number;
    avgSecondsPerQuestion: number;
    changedAnswers: number;
    byArea: Array<{ area: ExamArea; total: number; correct: number; wrong: number; blank: number; percent: number }>;
    byDiscipline: Array<{ discipline: string; total: number; correct: number; percent: number }> | null;
    label: string;
    scoreEstimateNotice: string;
  };
  essay: { id: string; status: string; simulatedScore: number | null; label: string } | null;
  questions: Array<{
    questionId: string;
    number: number;
    area: ExamArea;
    page: number | null;
    marked: string | null;
    official: string | null;
    annulled: boolean;
    status: 'CORRECT' | 'WRONG' | 'BLANK' | 'ANNULLED';
    changeCount: number;
    topic: string | null;
    discipline: string | null;
    skill: string | null;
    resolution: { body: string } | null;
    resolutionNotice: string | null;
  }>;
}

const STATUS: Record<string, { label: string; tone: 'success' | 'danger' | 'neutral' | 'warning' }> = {
  CORRECT: { label: 'Acertou', tone: 'success' },
  WRONG: { label: 'Errou', tone: 'danger' },
  BLANK: { label: 'Em branco', tone: 'neutral' },
  ANNULLED: { label: 'Anulada', tone: 'warning' },
};

export default function ResultPage() {
  const { id } = useParams<{ id: string }>();
  const { data, error, loading } = useApi<Result>(`/sessions/${id}/result`);
  const [filter, setFilter] = useState<'ALL' | 'WRONG' | 'BLANK'>('ALL');
  const [open, setOpen] = useState<string | null>(null);
  const [essayBusy, setEssayBusy] = useState(false);
  const [essayErr, setEssayErr] = useState<{ message: string } | null>(null);

  if (loading) return <Spinner />;
  if (error || !data) return <ErrorBox error={error} />;
  const o = data.objective;

  const openEssay = async () => {
    setEssayBusy(true);
    setEssayErr(null);
    try {
      const e = await api<{ id: string }>('/essays', { method: 'POST', json: { sessionId: id } });
      window.location.href = `/redacao/${e.id}`;
    } catch (err) {
      setEssayErr(err as { message: string });
      setEssayBusy(false);
    }
  };

  const questions = data.questions.filter((q) => filter === 'ALL' || q.status === filter);

  return (
    <div className="space-y-6">
      <PageHeader
        title="Simulado concluído"
        subtitle={`${data.session.exam.title} · ${data.session.mode === 'PROVA_REAL' ? 'Modo Prova Real' : 'Modo Estudo'}${data.session.status === 'EXPIRED' ? ' · encerrada automaticamente pelo tempo' : ''}`}
        actions={
          <>
            <LinkButton href="/provas" variant="secondary">
              Refazer / outra prova
            </LinkButton>
            <LinkButton href="/desempenho">Meu desempenho</LinkButton>
          </>
        }
      />

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Stat label={o.label} value={`${o.correct} / ${o.totalQuestions}`} hint={`${o.percent}% de acertos`} />
        <Stat label="Erros · Em branco" value={`${o.wrong} · ${o.blank}`} />
        <Stat label="Tempo utilizado" value={hhmmss(o.timeUsedSeconds)} hint={`${Math.round(o.avgSecondsPerQuestion)}s por questão em média`} />
        <Stat label="Respostas alteradas" value={o.changedAnswers} hint="Questões com mudança no cartão" />
      </div>

      <Notice tone="neutral">{o.scoreEstimateNotice}</Notice>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <h2 className="font-semibold">Veja onde você pode melhorar</h2>
          <div className="mt-4 space-y-3">
            {o.byArea.map((a) => (
              <div key={a.area}>
                <ProgressBar value={a.percent} label={`${EXAM_AREA_SHORT[a.area]} — ${a.correct}/${a.total} (${a.blank} em branco)`} />
              </div>
            ))}
          </div>
          {o.byDiscipline && o.byDiscipline.length > 0 && (
            <div className="mt-5">
              <h3 className="text-sm font-medium">Por disciplina (classificação pedagógica validada)</h3>
              <ul className="mt-2 grid gap-1 text-sm sm:grid-cols-2">
                {o.byDiscipline.map((d) => (
                  <li key={d.discipline} className="flex justify-between rounded border border-border px-3 py-1.5">
                    <span>{d.discipline}</span>
                    <span className="text-muted">
                      {d.correct}/{d.total} · {d.percent}%
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </Card>

        <Card>
          <h2 className="font-semibold">Redação</h2>
          {!data.session.exam.hasEssay ? (
            <p className="mt-2 text-sm text-muted">Esta prova não possui redação.</p>
          ) : data.essay ? (
            <>
              <p className="mt-2 text-sm">
                Status: <Badge tone={data.essay.status === 'EVALUATED' ? 'success' : 'primary'}>{data.essay.status}</Badge>
              </p>
              {data.essay.simulatedScore != null && (
                <p className="mt-2 text-2xl font-semibold">
                  {data.essay.simulatedScore} <span className="text-xs font-normal text-muted">{data.essay.label}</span>
                </p>
              )}
              <LinkButton href={data.essay.status === 'DRAFT' ? `/redacao/${data.essay.id}` : `/redacao/${data.essay.id}/relatorio`} className="mt-3">
                {data.essay.status === 'DRAFT' ? 'Enviar redação para avaliação' : 'Ver relatório da redação'}
              </LinkButton>
            </>
          ) : (
            <>
              <p className="mt-2 text-sm text-muted">Agora que a prova foi encerrada, sua redação pode ser enviada para avaliação simulada.</p>
              <ErrorBox error={essayErr} />
              <Button className="mt-3" onClick={openEssay} disabled={essayBusy}>
                Abrir minha redação
              </Button>
            </>
          )}
          <p className="mt-4 text-xs text-muted">
            <Link href="/ajuda#professor" className="text-primary underline">
              Perguntar ao Professor IA por que errei
            </Link>
          </p>
        </Card>
      </div>

      <Card>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 className="font-semibold">Relatório de questões</h2>
          <div className="flex gap-1">
            {(['ALL', 'WRONG', 'BLANK'] as const).map((f) => (
              <Button key={f} size="sm" variant={filter === f ? 'primary' : 'secondary'} onClick={() => setFilter(f)}>
                {f === 'ALL' ? 'Todas' : f === 'WRONG' ? 'Erradas' : 'Em branco'}
              </Button>
            ))}
          </div>
        </div>
        <div className="mt-4 overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="text-left text-xs uppercase text-muted">
              <tr>
                <th className="py-2 pr-3">Questão</th>
                <th className="py-2 pr-3">Área</th>
                <th className="py-2 pr-3">Marcada</th>
                <th className="py-2 pr-3">Gabarito oficial</th>
                <th className="py-2 pr-3">Status</th>
                <th className="py-2 pr-3">Assunto</th>
                <th className="py-2"></th>
              </tr>
            </thead>
            <tbody>
              {questions.map((q) => (
                <Fragment key={q.questionId}>
                  <tr className="border-t border-border">
                    <td className="py-2 pr-3 font-medium">
                      {q.number}
                      {q.page && <span className="ml-1 text-xs text-muted">p.{q.page}</span>}
                    </td>
                    <td className="py-2 pr-3">{EXAM_AREA_SHORT[q.area]}</td>
                    <td className="py-2 pr-3">{q.marked ?? '—'}</td>
                    <td className="py-2 pr-3">{q.annulled ? 'Anulada' : (q.official ?? '—')}</td>
                    <td className="py-2 pr-3">
                      <Badge tone={STATUS[q.status].tone}>{STATUS[q.status].label}</Badge>
                    </td>
                    <td className="py-2 pr-3 text-muted">
                      {q.topic ?? '—'}
                      {q.skill && <span className="ml-1 text-xs">· {q.skill}</span>}
                    </td>
                    <td className="py-2 text-right">
                      <button className="text-xs text-primary underline" onClick={() => setOpen(open === q.questionId ? null : q.questionId)}>
                        {open === q.questionId ? 'Fechar' : 'Resolução'}
                      </button>
                    </td>
                  </tr>
                  {open === q.questionId && (
                    <tr className="bg-surface-2">
                      <td colSpan={7} className="px-3 py-3 text-sm">
                        {q.resolution ? <p className="whitespace-pre-wrap">{q.resolution.body}</p> : <p className="text-muted">{q.resolutionNotice}</p>}
                      </td>
                    </tr>
                  )}
                </Fragment>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}
