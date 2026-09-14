import Link from 'next/link';
import { Card, PageHeader } from '@/components/ui';

export const metadata = { title: 'Estudar' };

const OPTIONS = [
  { href: '/provas', title: 'Provas oficiais', desc: 'Faça uma prova completa no Modo Prova Real ou resolva áreas específicas no Modo Estudo.' },
  { href: '/redacao', title: 'Redação', desc: 'Escreva a partir de propostas oficiais e receba correção simulada pelas cinco competências.' },
  { href: '/caderno-de-erros', title: 'Caderno de erros', desc: 'Revise as questões oficiais que você errou, com repetição espaçada.' },
  { href: '/plano', title: 'Plano de estudos', desc: 'Siga o plano diário gerado a partir do seu desempenho.' },
  { href: '/guia', title: 'Guia ENEM', desc: 'Veja o que estudar em cada eixo da prova.' },
  { href: '/ajuda#estrategia', title: 'Estratégia ENEM', desc: 'Gestão do tempo, cartão-resposta, ordem de resolução e resistência.' },
];

export default function StudyHub() {
  return (
    <div>
      <PageHeader title="Estudar" subtitle="Escolha por onde continuar." />
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {OPTIONS.map((o) => (
          <Link key={o.href} href={o.href} className="block">
            <Card className="h-full transition hover:border-primary">
              <h2 className="font-semibold">{o.title}</h2>
              <p className="mt-1 text-sm text-muted">{o.desc}</p>
            </Card>
          </Link>
        ))}
      </div>
    </div>
  );
}
