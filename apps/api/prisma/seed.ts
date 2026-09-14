/**
 * Seed de desenvolvimento.
 *
 * IMPORTANTE: este seed NÃO cria provas, questões nem gabaritos. Conteúdo
 * oficial só entra pelo painel de importação a partir dos PDFs do Inep e passa
 * pelo fluxo de auditoria. Aqui entram apenas: planos, admin, configurações e
 * tópicos editoriais do Guia ENEM.
 */
import { PrismaClient } from '@prisma/client';
import * as bcrypt from 'bcryptjs';

const prisma = new PrismaClient();

async function main() {
  // ---- Planos (valores editáveis pelo admin; nunca fixos no código da aplicação) ----
  const plans = [
    {
      code: 'FREE',
      name: 'Plano gratuito',
      description: 'Conheça a plataforma com uma prova oficial completa por mês e uma correção de redação.',
      priceCents: 0,
      trialDays: 0,
      limits: { fullExamsPerMonth: 1, essaysPerMonth: 1, studyPlan: false, tutor: false, errorNotebook: true },
      benefits: ['1 prova oficial completa por mês', 'Modo Estudo nas provas de amostra', '1 correção simulada de redação por mês', 'Caderno de erros'],
      sortOrder: 0,
    },
    {
      code: 'ESTUDANTE',
      name: 'Plano Estudante',
      description: 'Assinatura mensal acessível com todas as provas oficiais e plano de estudos.',
      priceCents: 1990,
      trialDays: 7,
      limits: { fullExamsPerMonth: -1, essaysPerMonth: 4, studyPlan: true, tutor: true, errorNotebook: true },
      benefits: ['Todas as provas oficiais', 'Modo Prova Real ilimitado', '4 correções simuladas de redação por mês', 'Plano de estudos', 'Professor IA', 'Caderno de erros'],
      sortOrder: 1,
    },
    {
      code: 'INTENSIVO',
      name: 'Plano Intensivo',
      description: 'Para quem está na reta final: mais correções de redação e rotina intensiva.',
      priceCents: 3490,
      trialDays: 7,
      limits: { fullExamsPerMonth: -1, essaysPerMonth: 12, studyPlan: true, tutor: true, errorNotebook: true },
      benefits: ['Tudo do Estudante', '12 correções simuladas de redação por mês', 'Modo Intensivo ENEM', 'Prioridade na fila de correção'],
      sortOrder: 2,
    },
  ];
  for (const p of plans) {
    await prisma.plan.upsert({ where: { code: p.code }, update: p, create: p });
  }

  // ---- Configuração de indicação ----
  await prisma.referralSettings.upsert({ where: { id: 'default' }, update: {}, create: { id: 'default' } });

  // ---- Admin inicial ----
  const adminEmail = process.env.SEED_ADMIN_EMAIL ?? 'admin@sip-enem.local';
  const adminPassword = process.env.SEED_ADMIN_PASSWORD ?? 'Admin123!Troque';
  await prisma.user.upsert({
    where: { email: adminEmail },
    update: {},
    create: {
      email: adminEmail,
      passwordHash: await bcrypt.hash(adminPassword, 12),
      role: 'SUPER_ADMIN',
      referralCode: 'ADMIN000',
      profile: { create: { fullName: 'Administrador', onboardingDone: true } },
    },
  });

  // Segundo revisor (o fluxo exige duas pessoas distintas nas revisões humanas)
  await prisma.user.upsert({
    where: { email: 'revisor@sip-enem.local' },
    update: {},
    create: {
      email: 'revisor@sip-enem.local',
      passwordHash: await bcrypt.hash('Revisor123!Troque', 12),
      role: 'REVIEWER',
      referralCode: 'REVIS000',
      profile: { create: { fullName: 'Revisor de Conteúdo', onboardingDone: true } },
    },
  });

  // ---- Tópicos editoriais do Guia ENEM (sem afirmações sobre "o que vai cair") ----
  const topics: Array<{ area: 'LINGUAGENS' | 'HUMANAS' | 'NATUREZA' | 'MATEMATICA' | 'REDACAO'; discipline: string; name: string }> = [
    { area: 'LINGUAGENS', discipline: 'Português', name: 'Interpretação de texto' },
    { area: 'LINGUAGENS', discipline: 'Português', name: 'Funções da linguagem' },
    { area: 'LINGUAGENS', discipline: 'Literatura', name: 'Movimentos literários brasileiros' },
    { area: 'LINGUAGENS', discipline: 'Inglês/Espanhol', name: 'Leitura em língua estrangeira' },
    { area: 'HUMANAS', discipline: 'História', name: 'Brasil República' },
    { area: 'HUMANAS', discipline: 'Geografia', name: 'Urbanização e questões ambientais' },
    { area: 'HUMANAS', discipline: 'Filosofia', name: 'Filosofia moderna e contemporânea' },
    { area: 'HUMANAS', discipline: 'Sociologia', name: 'Cidadania e movimentos sociais' },
    { area: 'NATUREZA', discipline: 'Biologia', name: 'Ecologia' },
    { area: 'NATUREZA', discipline: 'Biologia', name: 'Genética' },
    { area: 'NATUREZA', discipline: 'Física', name: 'Eletricidade' },
    { area: 'NATUREZA', discipline: 'Física', name: 'Mecânica' },
    { area: 'NATUREZA', discipline: 'Química', name: 'Estequiometria' },
    { area: 'NATUREZA', discipline: 'Química', name: 'Química orgânica' },
    { area: 'MATEMATICA', discipline: 'Matemática', name: 'Razão, proporção e porcentagem' },
    { area: 'MATEMATICA', discipline: 'Matemática', name: 'Funções' },
    { area: 'MATEMATICA', discipline: 'Matemática', name: 'Geometria plana e espacial' },
    { area: 'MATEMATICA', discipline: 'Matemática', name: 'Estatística e probabilidade' },
    { area: 'REDACAO', discipline: 'Redação', name: 'Estrutura dissertativo-argumentativa' },
    { area: 'REDACAO', discipline: 'Redação', name: 'Proposta de intervenção' },
  ];
  for (const t of topics) {
    const slug = t.name.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    await prisma.studyTopic.upsert({
      where: { slug },
      update: {},
      create: { ...t, slug, sourceType: 'EDITORIAL', reviewStatus: 'VERIFIED' },
    });
  }

  console.log(`Seed concluído. Admin: ${adminEmail} / ${adminPassword}`);
  console.log('Lembrete: nenhuma prova foi criada. Importe os PDFs oficiais pelo painel /admin/conteudo.');
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());
