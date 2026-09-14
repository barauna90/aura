export function brl(cents: number) {
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export function hhmmss(totalSeconds: number) {
  const s = Math.max(0, Math.floor(totalSeconds));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = s % 60;
  return [h, m, sec].map((n) => String(n).padStart(2, '0')).join(':');
}

export function dateBR(d: string | Date | null | undefined, withTime = false) {
  if (!d) return '—';
  const date = new Date(d);
  return withTime ? date.toLocaleString('pt-BR') : date.toLocaleDateString('pt-BR');
}

export function minutesLabel(min: number) {
  const h = Math.floor(min / 60);
  const m = min % 60;
  return h ? `${h}h${m ? ` ${m}min` : ''}` : `${m}min`;
}
