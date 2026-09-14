'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useState, type ReactNode } from 'react';
import { DISCLAIMERS, MAIN_MENU } from '@sip-enem/shared';
import { useAuth } from '@/lib/auth';
import { Spinner } from './ui';

export function AppShell({ children }: { children: ReactNode }) {
  const { user, loading, logout } = useAuth();
  const router = useRouter();
  const pathname = usePathname();
  const [open, setOpen] = useState(false);

  useEffect(() => {
    if (!loading && !user) router.replace(`/entrar?next=${encodeURIComponent(pathname)}`);
  }, [loading, user, router, pathname]);

  useEffect(() => setOpen(false), [pathname]);

  if (loading || !user) return <Spinner label="Verificando sessão…" />;

  const isAdmin = user.role === 'ADMIN' || user.role === 'SUPER_ADMIN' || user.role === 'REVIEWER';

  const nav = (
    <nav aria-label="Menu principal" className="flex flex-col gap-0.5">
      {MAIN_MENU.map((item) => {
        const active = pathname === item.href || pathname.startsWith(item.href + '/');
        return (
          <Link
            key={item.href}
            href={item.href}
            aria-current={active ? 'page' : undefined}
            className={`rounded-md px-3 py-2 text-sm transition ${active ? 'bg-primary-soft font-medium text-primary' : 'text-text hover:bg-surface-2'}`}
          >
            {item.label}
          </Link>
        );
      })}
      {isAdmin && (
        <Link href="/admin" className={`mt-2 rounded-md border border-border px-3 py-2 text-sm ${pathname.startsWith('/admin') ? 'bg-primary-soft text-primary' : 'hover:bg-surface-2'}`}>
          Administração
        </Link>
      )}
    </nav>
  );

  return (
    <div className="min-h-screen lg:grid lg:grid-cols-[240px_1fr]">
      <a href="#conteudo" className="skip-link">
        Pular para o conteúdo
      </a>
      <aside className="hidden border-r border-border bg-surface lg:flex lg:flex-col">
        <div className="border-b border-border px-5 py-4">
          <Link href="/dashboard" className="text-lg font-semibold tracking-tight">
            SIP<span className="text-primary">·</span>ENEM
          </Link>
          <p className="text-xs text-muted">Preparação intensiva</p>
        </div>
        <div className="flex-1 overflow-y-auto p-3">{nav}</div>
        <div className="border-t border-border p-3 text-xs text-muted">
          <p className="truncate font-medium text-text">{user.profile?.fullName ?? user.email}</p>
          <button onClick={logout} className="mt-1 text-primary hover:underline">
            Sair
          </button>
        </div>
      </aside>

      <div className="flex min-h-screen flex-col">
        <header className="flex items-center justify-between border-b border-border bg-surface px-4 py-3 lg:hidden">
          <Link href="/dashboard" className="font-semibold">
            SIP·ENEM
          </Link>
          <button aria-expanded={open} aria-controls="menu-mobile" onClick={() => setOpen((o) => !o)} className="rounded-md border border-border px-3 py-1.5 text-sm">
            {open ? 'Fechar' : 'Menu'}
          </button>
        </header>
        {open && (
          <div id="menu-mobile" className="border-b border-border bg-surface p-3 lg:hidden">
            {nav}
            <button onClick={logout} className="mt-2 px-3 text-sm text-primary">
              Sair
            </button>
          </div>
        )}
        <main id="conteudo" className="flex-1 px-4 py-6 md:px-8 md:py-8">
          <div className="mx-auto max-w-6xl">{children}</div>
        </main>
        <footer className="border-t border-border px-4 py-4 text-xs text-muted md:px-8">
          <p>{DISCLAIMERS.INDEPENDENCE}</p>
          <p className="mt-1">Provas oficiais têm origem identificada. Resultados são educacionais. Simulações de nota não equivalem ao resultado oficial.</p>
        </footer>
      </div>
    </div>
  );
}
