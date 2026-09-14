'use client';

import { useState } from 'react';
import { DISCLAIMERS } from '@sip-enem/shared';
import { api } from '@/lib/api';
import { Button, Card, ErrorBox, Notice, PageHeader, Textarea } from '@/components/ui';

export default function HelpPage() {
  const [q, setQ] = useState('');
  const [answer, setAnswer] = useState<{ answer: string; usedOfficialBase: boolean } | null>(null);
  const [err, setErr] = useState<{ message: string } | null>(null);
  const [busy, setBusy] = useState(false);

  const ask = async () => {
    setBusy(true);
    setErr(null);
    try {
      setAnswer(await api('/tutor/ask', { method: 'POST', json: { question: q } }));
    } catch (e) {
      setErr(e as { message: string });
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-6">
      <PageHeader title="Ajuda" />

      <Card id="professor" className="space-y-3">
        <h2 className="font-semibold">Professor ENEM IA</h2>
        <p className="text-sm text-muted">
          Pergunte “por que errei?”, “que matéria preciso revisar?” ou “como melhorar minha competência 3?”. As respostas usam seus resultados, resoluções validadas e a base de documentos oficiais. Indisponível durante o Modo Prova Real.
        </p>
        <Textarea rows={3} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Sua pergunta…" aria-label="Pergunta ao Professor IA" />
        <Button onClick={ask} disabled={busy || q.trim().length < 5}>
          {busy ? 'Pensando…' : 'Perguntar'}
        </Button>
        <ErrorBox error={err} />
        {answer && (
          <div className="rounded-md border border-border bg-surface-2 p-4 text-sm">
            <p className="whitespace-pre-wrap">{answer.answer}</p>
            <p className="mt-2 text-xs text-muted">{answer.usedOfficialBase ? 'Resposta apoiada em documentos oficiais da base.' : 'Nenhum documento oficial da base foi usado nesta resposta.'}</p>
          </div>
        )}
      </Card>

      <Card id="bolsas" className="space-y-2">
        <h2 className="font-semibold">Programa de bolsas</h2>
        <p className="text-sm text-muted">
          Estudantes sem condições de pagar podem receber acesso gratuito por 30, 90, 180 ou 365 dias, ou acesso integral, inclusive por meio de vagas patrocinadas por empresas e instituições. Envie um pedido pelo suporte com uma breve descrição da sua situação.
        </p>
      </Card>

      <Card id="estrategia" className="space-y-2">
        <h2 className="font-semibold">Estratégia ENEM</h2>
        <ul className="list-disc space-y-1 pl-5 text-sm text-muted">
          <li>Gestão do tempo: distribua o tempo por área e reserve minutos para transferir as respostas ao cartão.</li>
          <li>Cartão-resposta: só o que está marcado nele é corrigido. Transfira aos poucos, não no final.</li>
          <li>Redação: planeje tese, argumentos e proposta de intervenção antes de escrever na folha.</li>
          <li>Ordem de resolução: comece pela área em que você rende melhor para ganhar ritmo.</li>
          <li>Resistência: faça provas completas no Modo Prova Real para treinar as horas de duração.</li>
        </ul>
        <p className="text-xs text-muted">Estratégias ajudam na organização; nenhuma delas garante nota.</p>
      </Card>

      <Card id="termos" className="space-y-2">
        <h2 className="font-semibold">Termos, privacidade e avisos</h2>
        <Notice tone="neutral">{DISCLAIMERS.INDEPENDENCE}</Notice>
        <p className="text-sm text-muted">Provas oficiais têm origem identificada (fonte, versão e checksum). Resultados são educacionais. Simulações de nota não equivalem ao resultado oficial. {DISCLAIMERS.SCORE_ESTIMATE}</p>
        <p id="privacidade" className="text-sm text-muted">
          Coletamos o mínimo necessário: e-mail, nome, respostas e textos que você produz. Você pode exportar ou excluir seus dados em <a href="/perfil" className="text-primary underline">Perfil</a>. Não vendemos dados. Comunicações por e-mail e push só com seu consentimento.
        </p>
      </Card>
    </div>
  );
}
