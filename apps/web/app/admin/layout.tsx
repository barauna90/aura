'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { AppShell } from '@/components/AppShell';
import { useAuth } from '@/lib/auth';
import { Notice } from '@/components/ui';

const ADMIN_MENU = [
  { href: '/admin', label: 'Visão geral' },
  { href: '/admin/conteudo', label: 'Conteúdo oficial' },
  { href: '/admin/planos', label: 'Planos' },
  { href: '/admin/cupons', label: 'Cupons e promoções' },
  { href: '/admin/indicacoes', label: 'Indicações e comissões' },
  { href: '/admin/bolsas', label: 'Bolsas e patrocínios' },
];

export default function AdminLayout({ children }: { children: ReactNode }) {
  const { user } = useAuth();
  const pathname = usePathname();
  return (
    <AppShell>
      {user && user.role === 'STUDENT' ? (
        <Notice tone="danger">Acesso restrito à equipe administrativa.</Notice>
      ) : (
        <div className="space-y-6">
          <nav aria-label="Menu administrativo" className="flex flex-wrap gap-2 border-b border-border pb-3">
            {ADMIN_MENU.map((m) => (
              <Link key={m.href} href={m.href} className={`rounded-md px-3 py-1.5 text-sm ${pathname === m.href ? 'bg-primary text-white' : 'border border-border hover:bg-surface-2'}`}>
                {m.label}
              </Link>
            ))}
          </nav>
          {children}
        </div>
      )}
    </AppShell>
  );
}
