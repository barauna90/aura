import { allocateWeeklyMinutes, buildPlan, buildWeek, prioritizeAreas } from './study-plan.rules';
import { nextInterval } from './error-notebook.service';

const areas = [
  { area: 'LINGUAGENS' as const, percent: 70, blankRate: 0, avgSecondsPerQuestion: null },
  { area: 'HUMANAS' as const, percent: 65, blankRate: 0.1, avgSecondsPerQuestion: null },
  { area: 'NATUREZA' as const, percent: 40, blankRate: 0.3, avgSecondsPerQuestion: null },
  { area: 'MATEMATICA' as const, percent: null, blankRate: 0, avgSecondsPerQuestion: null },
];

describe('STUDY PLAN ORCHESTRATOR (seções 20, 53)', () => {
  it('prioriza áreas sem dados e depois as mais fracas', () => {
    expect(prioritizeAreas(areas)).toEqual(['MATEMATICA', 'NATUREZA', 'HUMANAS', 'LINGUAGENS']);
  });
  it('aloca mais minutos às áreas mais fracas e reserva redação', () => {
    const m = allocateWeeklyMinutes(10, ['MATEMATICA', 'NATUREZA', 'HUMANAS', 'LINGUAGENS']);
    expect(m.MATEMATICA).toBeGreaterThan(m.LINGUAGENS);
    expect(m.REDACAO).toBe(90);
    expect(Object.values(m).reduce((a, b) => a + b, 0)).toBeGreaterThanOrEqual(598);
  });
  it('semana inclui questões oficiais, revisão do caderno de erros e redação', () => {
    const tasks = buildWeek({ kind: 'REGULAR', startDate: new Date('2026-09-14'), targetDate: null, weeklyHours: 10, areas, essayAverage: 600, essayWeakCompetencies: [5], errorNotebookDue: 12, weakTopics: [{ slug: 'funcoes', name: 'Funções', area: 'MATEMATICA' }] });
    expect(tasks.some((t) => t.kind === 'QUESTOES' && t.topicSlug === 'funcoes')).toBe(true);
    expect(tasks.some((t) => t.kind === 'REVISAO')).toBe(true);
    expect(tasks.find((t) => t.kind === 'REDACAO')?.payload?.focusCompetency).toBe(5);
    expect(tasks.every((t) => t.payload?.source !== 'AI_GENERATED')).toBe(true);
  });
  it('modo intensivo inclui prova completa toda semana e respeita a data alvo', () => {
    const tasks = buildPlan({ kind: 'INTENSIVO', startDate: new Date('2026-09-14'), targetDate: new Date('2026-10-05'), weeklyHours: 20, areas, essayAverage: null, essayWeakCompetencies: [], errorNotebookDue: 0, weakTopics: [] });
    expect(tasks.filter((t) => t.kind === 'PROVA').length).toBeGreaterThanOrEqual(2);
    expect(tasks.every((t) => t.scheduledOn <= new Date('2026-10-05'))).toBe(true);
  });
});

describe('Repetição espaçada do caderno de erros (seção 21)', () => {
  it('erro na revisão volta para 1 dia; acerto aumenta o intervalo', () => {
    expect(nextInterval(6, 2.5, 1).interval).toBe(1);
    expect(nextInterval(1, 2.5, 5).interval).toBe(3);
    expect(nextInterval(3, 2.5, 4).interval).toBeGreaterThan(3);
  });
});
