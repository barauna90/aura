/** Gráfico de linhas em SVG puro (sem dependências), acessível via tabela oculta. */
export function LineChart({
  series,
  max = 100,
  height = 180,
  ariaLabel,
}: {
  series: Array<{ name: string; color: string; points: number[] }>;
  max?: number;
  height?: number;
  ariaLabel: string;
}) {
  const width = 600;
  const pad = 28;
  const n = Math.max(...series.map((s) => s.points.length), 1);
  const x = (i: number) => pad + (n <= 1 ? (width - 2 * pad) / 2 : (i * (width - 2 * pad)) / (n - 1));
  const y = (v: number) => height - pad - (Math.max(0, Math.min(max, v)) / max) * (height - 2 * pad);

  return (
    <figure>
      <svg viewBox={`0 0 ${width} ${height}`} className="w-full" role="img" aria-label={ariaLabel}>
        {[0, 0.25, 0.5, 0.75, 1].map((f) => (
          <g key={f}>
            <line x1={pad} x2={width - pad} y1={y(f * max)} y2={y(f * max)} stroke="var(--border)" strokeWidth={1} />
            <text x={4} y={y(f * max) + 4} fontSize={10} fill="var(--muted)">
              {Math.round(f * max)}
            </text>
          </g>
        ))}
        {series.map((s) => (
          <g key={s.name}>
            <polyline fill="none" stroke={s.color} strokeWidth={2} points={s.points.map((p, i) => `${x(i)},${y(p)}`).join(' ')} />
            {s.points.map((p, i) => (
              <circle key={i} cx={x(i)} cy={y(p)} r={3} fill={s.color} />
            ))}
          </g>
        ))}
      </svg>
      <figcaption className="mt-2 flex flex-wrap gap-3 text-xs text-muted">
        {series.map((s) => (
          <span key={s.name} className="flex items-center gap-1">
            <span className="inline-block h-2 w-3 rounded-sm" style={{ background: s.color }} /> {s.name}
          </span>
        ))}
      </figcaption>
      <table className="sr-only">
        <caption>{ariaLabel}</caption>
        <tbody>
          {series.map((s) => (
            <tr key={s.name}>
              <th scope="row">{s.name}</th>
              {s.points.map((p, i) => (
                <td key={i}>{p}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </figure>
  );
}
