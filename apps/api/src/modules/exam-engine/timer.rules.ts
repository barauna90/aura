import { SessionMode, SessionStatus } from '@prisma/client';

/**
 * Cronômetro — regras puras (seção 7).
 * A fonte da verdade é o servidor: startedAt + duração oficial da prova.
 * O navegador apenas exibe o tempo restante calculado aqui.
 */

export interface TimerState {
  mode: SessionMode;
  status: SessionStatus;
  durationMinutes: number;
  startedAt: Date | null;
  expectedEndAt: Date | null;
  pausedAt: Date | null;
  pausedSeconds: number;
}

export function computeExpectedEnd(startedAt: Date, durationMinutes: number): Date {
  return new Date(startedAt.getTime() + durationMinutes * 60_000);
}

/** Segundos restantes (>= 0). Nulo se a sessão ainda não começou. */
export function remainingSeconds(t: TimerState, now: Date = new Date()): number | null {
  if (!t.startedAt || !t.expectedEndAt) return null;
  const reference = t.status === 'PAUSED' && t.pausedAt ? t.pausedAt : now;
  const remaining = Math.floor((t.expectedEndAt.getTime() - reference.getTime()) / 1000);
  return Math.max(0, remaining);
}

export function isExpired(t: TimerState, now: Date = new Date()): boolean {
  if (t.status === 'FINISHED' || t.status === 'EXPIRED') return false;
  if (t.status === 'PAUSED') return false;
  const r = remainingSeconds(t, now);
  return r !== null && r <= 0;
}

/** Tempo efetivamente utilizado, descontando pausas (modo estudo). */
export function timeUsedSeconds(t: TimerState, finishedAt: Date): number {
  if (!t.startedAt) return 0;
  const gross = Math.floor((finishedAt.getTime() - t.startedAt.getTime()) / 1000);
  return Math.max(0, gross - t.pausedSeconds);
}

/** Pausar: permitido somente no MODO ESTUDO. */
export function canPause(t: TimerState): { ok: boolean; reason?: string } {
  if (t.mode === 'PROVA_REAL') return { ok: false, reason: 'O Modo Prova Real não permite pausar o cronômetro.' };
  if (t.status !== 'IN_PROGRESS') return { ok: false, reason: 'A sessão não está em andamento.' };
  return { ok: true };
}

/**
 * Retomar após pausa: o horário previsto de encerramento é deslocado pelo
 * tempo pausado, para que o tempo restante seja preservado.
 */
export function resume(t: TimerState, now: Date = new Date()): { expectedEndAt: Date; pausedSeconds: number } {
  if (!t.pausedAt || !t.expectedEndAt) throw new Error('Sessão não está pausada');
  const pausedFor = Math.floor((now.getTime() - t.pausedAt.getTime()) / 1000);
  return {
    expectedEndAt: new Date(t.expectedEndAt.getTime() + pausedFor * 1000),
    pausedSeconds: t.pausedSeconds + pausedFor,
  };
}

/** No Modo Prova Real o tempo nunca é reiniciado, estendido ou pausado. */
export function canModifyTimer(mode: SessionMode): boolean {
  return mode !== 'PROVA_REAL';
}

export function formatHHMMSS(totalSeconds: number): string {
  const s = Math.max(0, Math.floor(totalSeconds));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = s % 60;
  return [h, m, sec].map((n) => String(n).padStart(2, '0')).join(':');
}
