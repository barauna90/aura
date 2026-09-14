import Link from 'next/link';
import type { ButtonHTMLAttributes, InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';

function cx(...parts: Array<string | false | null | undefined>) {
  return parts.filter(Boolean).join(' ');
}

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'success';
const variants: Record<Variant, string> = {
  primary: 'bg-primary text-white hover:bg-primary-hover',
  secondary: 'bg-surface border border-border text-text hover:bg-surface-2',
  ghost: 'text-primary hover:bg-primary-soft',
  danger: 'bg-danger text-white hover:opacity-90',
  success: 'bg-success text-white hover:opacity-90',
};

export function Button({
  variant = 'primary',
  size = 'md',
  className,
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: Variant; size?: 'sm' | 'md' | 'lg' }) {
  return (
    <button
      className={cx(
        'inline-flex items-center justify-center gap-2 rounded-md font-medium transition disabled:opacity-50 disabled:cursor-not-allowed',
        size === 'sm' ? 'px-3 py-1.5 text-sm' : size === 'lg' ? 'px-5 py-3 text-base' : 'px-4 py-2 text-sm',
        variants[variant],
        className,
      )}
      {...props}
    />
  );
}

export function LinkButton({ href, variant = 'primary', className, children }: { href: string; variant?: Variant; className?: string; children: ReactNode }) {
  return (
    <Link href={href} className={cx('inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition', variants[variant], className)}>
      {children}
    </Link>
  );
}

export function Card({ children, className, id, as: Tag = 'section' }: { children: ReactNode; className?: string; id?: string; as?: 'section' | 'div' | 'article' }) {
  return (
    <Tag id={id} className={cx('rounded-lg border border-border bg-surface p-5', className)}>
      {children}
    </Tag>
  );
}

export function PageHeader({ title, subtitle, actions }: { title: string; subtitle?: ReactNode; actions?: ReactNode }) {
  return (
    <header className="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
        {subtitle && <p className="mt-1 text-sm text-muted">{subtitle}</p>}
      </div>
      {actions && <div className="flex gap-2">{actions}</div>}
    </header>
  );
}

export function Stat({ label, value, hint }: { label: string; value: ReactNode; hint?: string }) {
  return (
    <div className="rounded-lg border border-border bg-surface p-4">
      <p className="text-xs uppercase tracking-wide text-muted">{label}</p>
      <p className="mt-1 text-2xl font-semibold">{value}</p>
      {hint && <p className="mt-1 text-xs text-muted">{hint}</p>}
    </div>
  );
}

export function Badge({ children, tone = 'neutral' }: { children: ReactNode; tone?: 'neutral' | 'success' | 'warning' | 'danger' | 'primary' }) {
  const tones = {
    neutral: 'bg-surface-2 text-muted',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
    primary: 'bg-primary-soft text-primary',
  };
  return <span className={cx('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', tones[tone])}>{children}</span>;
}

export function Notice({ children, tone = 'neutral', title }: { children: ReactNode; tone?: 'neutral' | 'warning' | 'danger' | 'success' | 'primary'; title?: string }) {
  const tones = {
    neutral: 'border-border bg-surface-2',
    warning: 'border-warning/30 bg-warning-soft',
    danger: 'border-danger/30 bg-danger-soft',
    success: 'border-success/30 bg-success-soft',
    primary: 'border-primary/30 bg-primary-soft',
  };
  return (
    <div role="note" className={cx('rounded-md border px-4 py-3 text-sm', tones[tone])}>
      {title && <p className="mb-1 font-medium">{title}</p>}
      <div>{children}</div>
    </div>
  );
}

export function Field({ label, children, hint, id }: { label: string; children: ReactNode; hint?: string; id?: string }) {
  return (
    <div className="space-y-1">
      <label htmlFor={id} className="block text-sm font-medium">
        {label}
      </label>
      {children}
      {hint && <p className="text-xs text-muted">{hint}</p>}
    </div>
  );
}

const inputCls = 'w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text placeholder:text-muted focus:border-primary';

export function Input(props: InputHTMLAttributes<HTMLInputElement>) {
  return <input {...props} className={cx(inputCls, props.className)} />;
}
export function Select(props: SelectHTMLAttributes<HTMLSelectElement>) {
  return <select {...props} className={cx(inputCls, props.className)} />;
}
export function Textarea(props: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return <textarea {...props} className={cx(inputCls, props.className)} />;
}

export function ProgressBar({ value, label }: { value: number; label?: string }) {
  const v = Math.max(0, Math.min(100, value));
  return (
    <div>
      {label && (
        <div className="mb-1 flex justify-between text-xs text-muted">
          <span>{label}</span>
          <span>{v.toFixed(0)}%</span>
        </div>
      )}
      <div className="h-2 w-full overflow-hidden rounded-full bg-surface-2" role="progressbar" aria-valuenow={v} aria-valuemin={0} aria-valuemax={100} aria-label={label}>
        <div className="h-full rounded-full bg-success transition-all" style={{ width: `${v}%` }} />
      </div>
    </div>
  );
}

export function Empty({ title, children }: { title: string; children?: ReactNode }) {
  return (
    <div className="rounded-lg border border-dashed border-border p-8 text-center">
      <p className="font-medium">{title}</p>
      {children && <div className="mt-2 text-sm text-muted">{children}</div>}
    </div>
  );
}

export function Spinner({ label = 'Carregando…' }: { label?: string }) {
  return (
    <p className="py-8 text-center text-sm text-muted" role="status" aria-live="polite">
      {label}
    </p>
  );
}

export function ErrorBox({ error }: { error: { message: string } | null }) {
  if (!error) return null;
  return (
    <Notice tone="danger" title="Algo deu errado">
      {error.message}
    </Notice>
  );
}
