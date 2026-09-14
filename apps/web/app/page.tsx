'use client';

import Link from 'next/link';
import { DISCLAIMERS } from '@sip-enem/shared';
import { useApi } from '@/lib/hooks';
import { brl } from '@/lib/format';
import { LinkButton } from '@/components/ui';

interface Plan {
  code: string;
  name: string;
  description: string | null;
  priceCents: number;
  trialDays: number;
  benefits: string[];
}

export default function Landing() {
  const { data: plans } = useApi<Plan[]>('/subscriptions/plans');
  return (
    <div className="min-h-screen">
      <header className="border-b border-border bg-surface">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
          <span className="text-lg font-semibold tracking-tight">
            SIP<span className="text-primary">·</span>ENEM
          </span>
          <nav className="flex gap-2">
            <LinkButton href="/entrar" variant="secondary">
              Entrar
            </LinkButton>
            <LinkButton href="/cadastro">Criar conta</LinkButton>
          </nav>
        </div>
      </header>

      <main>
        <section className="mx-auto max-w-6xl px-4 py-16 md:py-24">
          <p className="text-sm font-medium uppercase tracking-wide text-primary">Preparação intensiva para o ENEM</p>
          <h1 className="mt-3 max-w-3xl text-4xl font-semibold tracking-tight md:text-5xl">
            Não consegue pagar um cursinho? Aqui você tem uma ferramenta séria para estudar.
          </h1>
          <p className="mt-5 max-w-2xl text-lg text-muted">
            Provas oficiais anteriores exibidas exatamente como o Inep publicou. Cronômetro real, cartão-resposta,
            correção pelo gabarito oficial, redação avaliada pelas cinco competências e um plano de estudos que se adapta
            aos seus resultados.
          </p>
          <div className="mt-8 flex flex-wrap gap-3">
            <LinkButton href="/cadastro">Começar gratuitamente</LinkButton>
            <LinkButton href="/entrar" variant="secondary">
              Já tenho conta
            </LinkButton>
          </div>
        </section>

        <section className="border-y border-border bg-surface">
          <div className="mx-auto grid max-w-6xl gap-8 px-4 py-14 md:grid-cols-3">
            {[
              ['Provas oficiais, sem invenção', 'Cada prova guarda sua fonte no Inep, versão e checksum. Nenhuma questão é reescrita, resumida ou gerada por IA.'],
              ['Simulação fiel', 'Modo Prova Real com a duração daquela edição, sem pausa, sem dicas. Só o que está no cartão-resposta é corrigido.'],
              ['Evolução com dados', 'Acertos por área, tempo por questão, caderno de erros com revisão espaçada e plano de estudos personalizado.'],
            ].map(([t, d]) => (
              <div key={t}>
                <h2 className="font-semibold">{t}</h2>
                <p className="mt-2 text-sm text-muted">{d}</p>
              </div>
            ))}
          </div>
        </section>

        <section className="mx-auto max-w-6xl px-4 py-16">
          <h2 className="text-2xl font-semibold">Planos acessíveis</h2>
          <p className="mt-1 text-sm text-muted">Sem taxas escondidas, sem renovação enganosa. Cancele quando quiser.</p>
          <div className="mt-8 grid gap-4 md:grid-cols-3">
            {(plans ?? []).map((p) => (
              <div key={p.code} className="rounded-lg border border-border bg-surface p-6">
                <h3 className="font-semibold">{p.name}</h3>
                <p className="mt-2 text-3xl font-semibold">
                  {p.priceCents === 0 ? 'Grátis' : brl(p.priceCents)}
                  {p.priceCents > 0 && <span className="text-sm font-normal text-muted">/mês</span>}
                </p>
                {p.trialDays > 0 && <p className="text-xs text-success">{p.trialDays} dias de teste gratuito</p>}
                <p className="mt-2 text-sm text-muted">{p.description}</p>
                <ul className="mt-4 space-y-1 text-sm">
                  {p.benefits.map((b) => (
                    <li key={b}>• {b}</li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
          <p className="mt-6 text-sm text-muted">
            Estudante sem condições de pagar? Conheça o <Link href="/ajuda" className="text-primary underline">programa de bolsas</Link>.
          </p>
        </section>
      </main>

      <footer className="border-t border-border px-4 py-8 text-xs text-muted">
        <div className="mx-auto max-w-6xl space-y-2">
          <p>{DISCLAIMERS.INDEPENDENCE}</p>
          <p>Provas oficiais têm origem identificada. Resultados são educacionais. Simulações de nota não equivalem ao resultado oficial.</p>
        </div>
      </footer>
    </div>
  );
}
