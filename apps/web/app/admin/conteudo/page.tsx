'use client';

import { useState, type FormEvent } from 'react';
import { EXAM_APPLICATION_LABEL, EXAM_AREAS, EXAM_AREA_SHORT, PIPELINE_STAGES, type ExamArea, type PipelineStage } from '@sip-enem/shared';
import { api, API_URL, getTokens } from '@/lib/api';
import { useApi } from '@/lib/hooks';
import { Badge, Button, Card, ErrorBox, Field, Input, Notice, PageHeader, Select, Spinner, Textarea } from '@/components/ui';

interface AdminExam {
  id: string;
  title: string;
  application: string;
  day: number;
  durationMinutes: number;
  reviewStatus: string;
  pipelineStage: PipelineStage;
  version: number;
  edition: { year: number };
  source: { sourceUrl: string; documentVersion: string; checksum: string };
  booklets: Array<{ id: string; label: string; color: string; pageCount: number; reviewStatus: string; _count: { questions: number }; answerSets: Array<{ id: string; checksum: string; reviewStatus: string }> }>;
  essayPrompt: { id: string; theme: string; reviewStatus: string } | null;
}

const STAGE_LABEL: Record<PipelineStage, string> = { IMPORTED: 'Importado', AUTO_VALIDATED: 'Validação automática', HUMAN_REVIEW_1: 'Revisão humana 1', HUMAN_REVIEW_2: 'Revisão humana 2', PUBLISHED: 'Publicado' };

async function multipart(path: string, form: FormData) {
  const { access } = getTokens();
  const r = await fetch(`${API_URL}/api${path}`, { method: 'POST', headers: access ? { Authorization: `Bearer ${access}` } : {}, body: form });
  const body = await r.json().catch(() => ({}));
  if (!r.ok) throw new Error(Array.isArray(body.message) ? body.message.join('; ') : (body.message ?? r.statusText));
  return body;
}

export default function ContentAdmin() {
  const { data, error, loading, reload } = useApi<AdminExam[]>('/admin/content/exams');
  const [selected, setSelected] = useState<string | null>(null);
  const [msg, setMsg] = useState<string | null>(null);
  const [err, setErr] = useState<{ message: string } | null>(null);
  const [validation, setValidation] = useState<{ ok: boolean; structural: Array<{ message: string }>; integrity: Array<{ message: string }> } | null>(null);

  const run = async (fn: () => Promise<unknown>, ok: string) => {
    setErr(null);
    setMsg(null);
    try {
      await fn();
      setMsg(ok);
      reload();
    } catch (e) {
      const m = (e as { message: string; body?: { problems?: Array<{ message: string }> } });
      setErr({ message: m.body?.problems ? `${m.message}: ${m.body.problems.map((p) => p.message).join(' | ')}` : m.message });
    }
  };

  const createExam = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const form = new FormData(e.currentTarget);
    form.set('areas', form.getAll('areas').join(','));
    await run(() => multipart('/admin/content/exams', form), 'Prova importada como PENDING. Adicione cadernos e gabaritos.');
    e.currentTarget.reset();
  };

  const addBooklet = async (e: FormEvent<HTMLFormElement>, examId: string) => {
    e.preventDefault();
    const form = new FormData(e.currentTarget);
    await run(() => multipart(`/admin/content/exams/${examId}/booklets`, form), 'Caderno importado com checksum.');
  };

  const addAnswerKey = async (e: FormEvent<HTMLFormElement>, bookletId: string) => {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const raw = String(fd.get('answersCsv') ?? '');
    // Formato: numero;area;letra(ou X para anulada);idioma(opcional);pagina(opcional)
    const answers = raw
      .split(/\r?\n/)
      .map((l) => l.trim())
      .filter(Boolean)
      .map((l) => {
        const [number, area, letter, lang, page] = l.split(';').map((x) => x.trim());
        return { number: Number(number), area, correct: letter === 'X' ? undefined : letter, annulled: letter === 'X', foreignLanguage: lang || undefined, page: page ? Number(page) : undefined };
      });
    await run(
      () => api(`/admin/content/booklets/${bookletId}/answer-key`, { method: 'POST', json: { sourceUrl: fd.get('sourceUrl'), documentVersion: fd.get('documentVersion'), answers } }),
      `${answers.length} questões e gabarito registrados.`,
    );
  };

  const addPrompt = async (e: FormEvent<HTMLFormElement>, examId: string) => {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const texts = String(fd.get('texts') ?? '')
      .split(/\n---\n/)
      .map((t) => t.trim())
      .filter(Boolean)
      .map((body) => ({ body }));
    await run(
      () => api(`/admin/content/exams/${examId}/essay-prompt`, { method: 'POST', json: { theme: fd.get('theme'), motivatingTexts: texts, sourceUrl: fd.get('sourceUrl'), documentVersion: fd.get('documentVersion'), maxLines: Number(fd.get('maxLines') || 30) } }),
      'Proposta de redação registrada (PENDING).',
    );
  };

  const validate = async (examId: string) => {
    setValidation(await api(`/admin/content/exams/${examId}/validate`));
  };
  const advance = (examId: string, target: PipelineStage) => run(() => api(`/admin/content/exams/${examId}/stage`, { method: 'POST', json: { target } }), `Etapa avançada para ${STAGE_LABEL[target]}.`);

  const sel = data?.find((e) => e.id === selected) ?? null;

  return (
    <div className="space-y-6">
      <PageHeader title="Conteúdo oficial" subtitle="Importação de provas a partir dos PDFs do Inep e fluxo de auditoria. Nada é publicado sem validação automática e duas revisões humanas distintas." />
      <Notice tone="warning" title="Regra inegociável">
        Só cadastre documentos oficiais do Inep/MEC com a URL de origem. Nunca transcreva questões “de memória”. O PDF é exibido ao aluno sem alterações; o gabarito é comparado por checksum antes de qualquer publicação.
      </Notice>
      {msg && <Notice tone="success">{msg}</Notice>}
      <ErrorBox error={err} />

      <div className="grid gap-6 lg:grid-cols-[360px_1fr]">
        <div className="space-y-6">
          <Card>
            <h2 className="font-semibold">1. Cadastrar prova</h2>
            <form onSubmit={createExam} className="mt-3 space-y-3">
              <div className="grid grid-cols-2 gap-2">
                <Field label="Ano" id="year"><Input id="year" name="year" type="number" required min={1998} /></Field>
                <Field label="Dia" id="day"><Select id="day" name="day"><option value="1">1</option><option value="2">2</option></Select></Field>
              </div>
              <Field label="Aplicação" id="app">
                <Select id="app" name="application">{Object.entries(EXAM_APPLICATION_LABEL).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</Select>
              </Field>
              <Field label="Título" id="title"><Input id="title" name="title" required placeholder="ENEM 2023 — 1º dia (Linguagens, Humanas e Redação)" /></Field>
              <Field label="Duração oficial desta edição/dia (min)" id="dur" hint="Consulte o edital da edição. Não existe valor universal."><Input id="dur" name="durationMinutes" type="number" required min={30} /></Field>
              <fieldset>
                <legend className="text-sm font-medium">Áreas</legend>
                <div className="mt-1 flex flex-wrap gap-2 text-sm">
                  {EXAM_AREAS.map((a) => (
                    <label key={a} className="flex items-center gap-1"><input type="checkbox" name="areas" value={a} /> {EXAM_AREA_SHORT[a as ExamArea]}</label>
                  ))}
                </div>
              </fieldset>
              <Field label="URL oficial (Inep)" id="src"><Input id="src" name="sourceUrl" type="url" required placeholder="https://download.inep.gov.br/…" /></Field>
              <Field label="Versão do documento" id="ver"><Input id="ver" name="documentVersion" required placeholder="ex.: 2023-11-05 v1" /></Field>
              <Field label="Observação de estrutura (provas antigas)" id="note"><Input id="note" name="structureNote" /></Field>
              <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="isFreeSample" value="true" /> Prova de amostra gratuita</label>
              <Field label="PDF oficial (opcional aqui; obrigatório no caderno)" id="pdf"><Input id="pdf" name="sourcePdf" type="file" accept="application/pdf" /></Field>
              <Button type="submit">Cadastrar como PENDING</Button>
            </form>
          </Card>
        </div>

        <div className="space-y-4">
          <Card>
            <h2 className="font-semibold">Provas cadastradas</h2>
            {loading && <Spinner />}
            <ErrorBox error={error} />
            <ul className="mt-3 divide-y divide-border text-sm">
              {(data ?? []).map((e) => (
                <li key={e.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                  <button className="text-left" onClick={() => { setSelected(e.id); setValidation(null); }}>
                    <span className="font-medium">{e.title}</span>
                    <span className="ml-2 text-xs text-muted">ENEM {e.edition.year} · v{e.version}</span>
                  </button>
                  <span className="flex gap-1">
                    <Badge tone={e.reviewStatus === 'VERIFIED' ? 'success' : e.reviewStatus === 'REJECTED' ? 'danger' : 'warning'}>{e.reviewStatus}</Badge>
                    <Badge>{STAGE_LABEL[e.pipelineStage]}</Badge>
                  </span>
                </li>
              ))}
            </ul>
          </Card>

          {sel && (
            <Card className="space-y-5">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <h2 className="font-semibold">{sel.title}</h2>
                  <p className="text-xs text-muted">
                    Fonte: <a href={sel.source.sourceUrl} target="_blank" rel="noreferrer" className="underline">{sel.source.sourceUrl}</a> · {sel.source.documentVersion} · checksum {sel.source.checksum.slice(0, 12)}…
                  </p>
                </div>
                <Button size="sm" variant="secondary" onClick={() => validate(sel.id)}>Validar agora</Button>
              </div>

              {validation && (
                <Notice tone={validation.ok ? 'success' : 'danger'} title={validation.ok ? 'Validação OK — estrutura e integridade conferem com o documento oficial.' : 'Bloqueios encontrados'}>
                  <ul className="list-disc pl-5">
                    {[...validation.structural, ...validation.integrity].map((p, i) => <li key={i}>{p.message}</li>)}
                  </ul>
                </Notice>
              )}

              <div>
                <h3 className="text-sm font-medium">Fluxo de auditoria</h3>
                <ol className="mt-2 flex flex-wrap gap-2 text-xs">
                  {PIPELINE_STAGES.map((s, i) => {
                    const idx = PIPELINE_STAGES.indexOf(sel.pipelineStage);
                    const next = i === idx + 1;
                    return (
                      <li key={s} className={`rounded-full border px-3 py-1 ${i <= idx ? 'border-success bg-success-soft text-success' : 'border-border text-muted'}`}>
                        {STAGE_LABEL[s]}
                        {next && (
                          <button className="ml-2 font-medium text-primary underline" onClick={() => advance(sel.id, s)}>avançar →</button>
                        )}
                      </li>
                    );
                  })}
                </ol>
                <p className="mt-1 text-xs text-muted">Revisão 1 e Revisão 2 devem ser feitas por pessoas diferentes. Publicar exige ADMIN e passa pela verificação de integridade (checksum do PDF e do gabarito).</p>
              </div>

              <div>
                <h3 className="text-sm font-medium">Cadernos ({sel.booklets.length})</h3>
                <ul className="mt-2 space-y-3">
                  {sel.booklets.map((b) => (
                    <li key={b.id} className="rounded-md border border-border p-3 text-sm">
                      <div className="flex flex-wrap justify-between gap-2">
                        <span>{b.label} · {b.pageCount} páginas · {b._count.questions} questões</span>
                        <span className="flex gap-1"><Badge tone={b.reviewStatus === 'VERIFIED' ? 'success' : 'warning'}>{b.reviewStatus}</Badge>{b.answerSets.length > 0 && <Badge tone="primary">gabarito {b.answerSets.length}</Badge>}</span>
                      </div>
                      <details className="mt-2">
                        <summary className="cursor-pointer text-primary">Registrar gabarito oficial deste caderno</summary>
                        <form onSubmit={(e) => addAnswerKey(e, b.id)} className="mt-2 space-y-2">
                          <Field label="URL do gabarito oficial" id={`gk-${b.id}`}><Input id={`gk-${b.id}`} name="sourceUrl" type="url" required /></Field>
                          <Field label="Versão" id={`gv-${b.id}`}><Input id={`gv-${b.id}`} name="documentVersion" required /></Field>
                          <Field label="Linhas: número;área;letra (X = anulada);idioma (INGLES/ESPANHOL, opcional);página (opcional)" id={`ga-${b.id}`}>
                            <Textarea id={`ga-${b.id}`} name="answersCsv" rows={6} required placeholder={'1;LINGUAGENS;B;INGLES;2\n6;LINGUAGENS;E;;3\n46;HUMANAS;A'} />
                          </Field>
                          <Button size="sm" type="submit">Registrar gabarito</Button>
                        </form>
                      </details>
                    </li>
                  ))}
                </ul>
                <details className="mt-3">
                  <summary className="cursor-pointer text-sm text-primary">Adicionar caderno (PDF oficial)</summary>
                  <form onSubmit={(e) => addBooklet(e, sel.id)} className="mt-2 space-y-2">
                    <div className="grid grid-cols-2 gap-2">
                      <Field label="Cor" id="color"><Select id="color" name="color">{['AZUL', 'AMARELO', 'BRANCO', 'ROSA', 'CINZA', 'VERDE', 'LARANJA', 'NAO_APLICAVEL'].map((c) => <option key={c}>{c}</option>)}</Select></Field>
                      <Field label="Páginas" id="pages"><Input id="pages" name="pageCount" type="number" min={1} required /></Field>
                    </div>
                    <Field label="Rótulo" id="label"><Input id="label" name="label" required placeholder="Caderno 1 — Azul" /></Field>
                    <Field label="URL oficial do caderno" id="bsrc"><Input id="bsrc" name="sourceUrl" type="url" required /></Field>
                    <Field label="Versão" id="bver"><Input id="bver" name="documentVersion" required /></Field>
                    <Field label="PDF oficial" id="bpdf"><Input id="bpdf" name="pdf" type="file" accept="application/pdf" required /></Field>
                    <Button size="sm" type="submit">Importar caderno</Button>
                  </form>
                </details>
              </div>

              <div>
                <h3 className="text-sm font-medium">Redação</h3>
                {sel.essayPrompt ? (
                  <p className="text-sm">{sel.essayPrompt.theme} <Badge tone={sel.essayPrompt.reviewStatus === 'VERIFIED' ? 'success' : 'warning'}>{sel.essayPrompt.reviewStatus}</Badge></p>
                ) : (
                  <p className="text-sm text-muted">Nenhuma proposta cadastrada.</p>
                )}
                <details className="mt-2">
                  <summary className="cursor-pointer text-sm text-primary">Registrar proposta oficial</summary>
                  <form onSubmit={(e) => addPrompt(e, sel.id)} className="mt-2 space-y-2">
                    <Field label="Tema (exatamente como no documento oficial)" id="theme"><Input id="theme" name="theme" required /></Field>
                    <Field label="Textos motivadores (separe com uma linha contendo apenas ---)" id="texts"><Textarea id="texts" name="texts" rows={8} required /></Field>
                    <div className="grid grid-cols-2 gap-2">
                      <Field label="URL oficial" id="psrc"><Input id="psrc" name="sourceUrl" type="url" required /></Field>
                      <Field label="Versão" id="pver"><Input id="pver" name="documentVersion" required /></Field>
                    </div>
                    <Field label="Linhas da folha oficial" id="maxLines"><Input id="maxLines" name="maxLines" type="number" defaultValue={30} /></Field>
                    <Button size="sm" type="submit">Registrar proposta</Button>
                  </form>
                </details>
              </div>
            </Card>
          )}
        </div>
      </div>
    </div>
  );
}
