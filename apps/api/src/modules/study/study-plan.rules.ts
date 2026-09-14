import { ExamArea } from '@prisma/client';

/**
 * STUDY PLAN ORCHESTRATOR — regras puras (seções 20 e 53).
 * Gera tarefas a partir do desempenho real do aluno. Não usa IA para decidir
 * prioridades: a priorização é determinística e auditável. A IA (opcional)
 * apenas redige recomendações de conteúdo em cima deste esqueleto.
 */

export interface AreaPerformance {
  area: ExamArea;
  percent: number | null; // null = sem dados
  blankRate: number; // 0..1
  avgSecondsPerQuestion: number | null;
}

export interface PlannerInput {
  kind: 'REGULAR' | 'INTENSIVO';
  startDate: Date;
  targetDate: Date | null;
  weeklyHours: number;
  areas: AreaPerformance[];
  essayAverage: number | null; // nota simulada média
  essayWeakCompetencies: number[]; // 1..5
  errorNotebookDue: number;
  weakTopics: Array<{ slug: string; name: string; area: ExamArea }>;
}

export interface PlannedTask {
  scheduledOn: Date;
  kind: 'QUESTOES' | 'REVISAO' | 'PROVA' | 'REDACAO' | 'LEITURA';
  title: string;
  minutes: number;
  area?: ExamArea;
  topicSlug?: string;
  payload?: Record<string, unknown>;
}

const OBJECTIVE_AREAS: ExamArea[] = ['LINGUAGENS', 'HUMANAS', 'NATUREZA', 'MATEMATICA'];

/** Ordena as áreas da mais fraca para a mais forte. Sem dados = prioridade máxima (diagnóstico). */
export function prioritizeAreas(areas: AreaPerformance[]): ExamArea[] {
  const score = (a: AreaPerformance) => (a.percent === null ? -1 : a.percent - a.blankRate * 20);
  return [...areas]
    .filter((a) => OBJECTIVE_AREAS.includes(a.area))
    .sort((a, b) => score(a) - score(b))
    .map((a) => a.area);
}

/** Distribui as horas semanais pelas áreas (mais fracas recebem mais). */
export function allocateWeeklyMinutes(weeklyHours: number, prioritized: ExamArea[], essayShare = 0.15): Record<string, number> {
  const total = Math.max(1, Math.round(weeklyHours * 60));
  const essay = Math.round(total * essayShare);
  const remaining = total - essay;
  const weights = prioritized.map((_, i) => prioritized.length - i); // 4,3,2,1
  const sum = weights.reduce((a, b) => a + b, 0);
  const out: Record<string, number> = { REDACAO: essay };
  prioritized.forEach((area, i) => (out[area] = Math.round((remaining * weights[i]) / sum)));
  return out;
}

export function buildWeek(input: PlannerInput, weekIndex = 0): PlannedTask[] {
  const tasks: PlannedTask[] = [];
  const prioritized = prioritizeAreas(input.areas);
  const minutes = allocateWeeklyMinutes(input.weeklyHours, prioritized, input.kind === 'INTENSIVO' ? 0.2 : 0.15);
  const start = new Date(input.startDate);
  start.setDate(start.getDate() + weekIndex * 7);
  const day = (offset: number) => {
    const d = new Date(start);
    d.setDate(d.getDate() + offset);
    d.setHours(0, 0, 0, 0);
    return d;
  };
  const daysPerWeek = input.kind === 'INTENSIVO' ? 6 : 5;

  // Blocos de questões oficiais por área, espalhados pela semana.
  let slot = 0;
  for (const area of prioritized) {
    const areaMinutes = minutes[area] ?? 0;
    if (areaMinutes <= 0) continue;
    const blocks = Math.max(1, Math.round(areaMinutes / 50));
    const per = Math.round(areaMinutes / blocks);
    const weakTopic = input.weakTopics.find((t) => t.area === area);
    for (let b = 0; b < blocks; b++) {
      tasks.push({
        scheduledOn: day(slot % daysPerWeek),
        kind: 'QUESTOES',
        area,
        topicSlug: weakTopic?.slug,
        title: weakTopic ? `Questões oficiais de ${weakTopic.name}` : `Questões oficiais — ${labelOf(area)}`,
        minutes: per,
        payload: { area, topic: weakTopic?.slug ?? null, source: 'OFFICIAL_INEP' },
      });
      slot++;
    }
  }

  // Revisão do caderno de erros (repetição espaçada).
  if (input.errorNotebookDue > 0) {
    tasks.push({
      scheduledOn: day(2),
      kind: 'REVISAO',
      title: `Revisar ${Math.min(input.errorNotebookDue, 20)} questões do caderno de erros`,
      minutes: Math.min(60, 3 * Math.min(input.errorNotebookDue, 20)),
      payload: { due: input.errorNotebookDue },
    });
  }

  // Redação
  const essayMinutes = minutes.REDACAO ?? 0;
  if (essayMinutes > 0) {
    const focus = input.essayWeakCompetencies[0];
    tasks.push({
      scheduledOn: day(daysPerWeek - 1),
      kind: 'REDACAO',
      area: 'REDACAO',
      title: focus ? `Redação com foco na competência ${focus}` : 'Redação — proposta oficial',
      minutes: Math.max(60, essayMinutes),
      payload: { focusCompetency: focus ?? null },
    });
  }

  // Prova completa: semanal no intensivo, quinzenal no regular.
  if (input.kind === 'INTENSIVO' || weekIndex % 2 === 1) {
    tasks.push({
      scheduledOn: day(daysPerWeek),
      kind: 'PROVA',
      title: 'Prova completa oficial — Modo Prova Real',
      minutes: 300,
      payload: { mode: 'PROVA_REAL' },
    });
  }
  return tasks;
}

export function buildPlan(input: PlannerInput): PlannedTask[] {
  const weeks =
    input.targetDate && input.targetDate > input.startDate
      ? Math.min(16, Math.max(1, Math.ceil((input.targetDate.getTime() - input.startDate.getTime()) / (7 * 86_400_000))))
      : 4;
  const tasks: PlannedTask[] = [];
  for (let w = 0; w < weeks; w++) tasks.push(...buildWeek(input, w));
  return tasks.filter((t) => !input.targetDate || t.scheduledOn <= input.targetDate);
}

function labelOf(area: ExamArea) {
  return { LINGUAGENS: 'Linguagens', HUMANAS: 'Ciências Humanas', NATUREZA: 'Ciências da Natureza', MATEMATICA: 'Matemática', REDACAO: 'Redação' }[area];
}
