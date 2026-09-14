import { Empty, Notice, PageHeader } from '@/components/ui';

export const metadata = { title: 'Simulados de Treinamento' };

/**
 * Seção separada das "Provas oficiais do ENEM". Qualquer simulado autoral ou
 * gerado por IA viverá aqui, sempre identificado como AI_GENERATED_EDUCATIONAL —
 * nunca misturado com questões oficiais.
 */
export default function SimuladosPage() {
  return (
    <div className="space-y-6">
      <PageHeader title="Simulados de Treinamento" subtitle="Seção separada das provas oficiais. Nada aqui é apresentado como questão do ENEM." />
      <Notice tone="warning">Os simulados de treinamento ainda não foram disponibilizados. Enquanto isso, use as provas oficiais anteriores em “Provas anteriores”.</Notice>
      <Empty title="Nenhum simulado de treinamento publicado." />
    </div>
  );
}
