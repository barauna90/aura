import { canPause, computeExpectedEnd, formatHHMMSS, isExpired, remainingSeconds, resume, timeUsedSeconds, TimerState } from './timer.rules';

const base = (over: Partial<TimerState> = {}): TimerState => ({
  mode: 'PROVA_REAL',
  status: 'IN_PROGRESS',
  durationMinutes: 330,
  startedAt: new Date('2026-09-14T13:30:00Z'),
  expectedEndAt: new Date('2026-09-14T19:00:00Z'),
  pausedAt: null,
  pausedSeconds: 0,
  ...over,
});

describe('Cronômetro (seção 7)', () => {
  it('calcula o horário previsto de encerramento pela duração da edição', () => {
    expect(computeExpectedEnd(new Date('2026-09-14T13:30:00Z'), 330).toISOString()).toBe('2026-09-14T19:00:00.000Z');
  });

  it('tempo restante é calculado no servidor e não fica negativo', () => {
    expect(remainingSeconds(base(), new Date('2026-09-14T18:59:30Z'))).toBe(30);
    expect(remainingSeconds(base(), new Date('2026-09-14T19:05:00Z'))).toBe(0);
  });

  it('atualizar a página não reinicia o tempo (estado deriva de startedAt)', () => {
    const t = base();
    const before = remainingSeconds(t, new Date('2026-09-14T15:00:00Z'));
    const afterReload = remainingSeconds({ ...t }, new Date('2026-09-14T15:00:10Z'));
    expect(afterReload).toBe(before! - 10);
  });

  it('encerra automaticamente quando o tempo termina', () => {
    expect(isExpired(base(), new Date('2026-09-14T19:00:00Z'))).toBe(true);
    expect(isExpired(base(), new Date('2026-09-14T18:00:00Z'))).toBe(false);
  });

  it('MODO PROVA REAL não permite pausar', () => {
    expect(canPause(base()).ok).toBe(false);
    expect(canPause(base({ mode: 'ESTUDO' })).ok).toBe(true);
  });

  it('retomar no modo estudo preserva o tempo restante', () => {
    const paused = base({ mode: 'ESTUDO', status: 'PAUSED', pausedAt: new Date('2026-09-14T15:00:00Z') });
    expect(remainingSeconds(paused, new Date('2026-09-14T16:00:00Z'))).toBe(4 * 3600);
    const r = resume(paused, new Date('2026-09-14T16:00:00Z'));
    expect(r.pausedSeconds).toBe(3600);
    expect(r.expectedEndAt.toISOString()).toBe('2026-09-14T20:00:00.000Z');
  });

  it('tempo utilizado desconta pausas', () => {
    expect(timeUsedSeconds(base({ pausedSeconds: 600 }), new Date('2026-09-14T14:30:00Z'))).toBe(3000);
  });

  it('formata HH:MM:SS', () => {
    expect(formatHHMMSS(19800)).toBe('05:30:00');
    expect(formatHHMMSS(-5)).toBe('00:00:00');
  });
});
