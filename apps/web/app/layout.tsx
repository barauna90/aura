import type { Metadata, Viewport } from 'next';
import type { ReactNode } from 'react';
import './globals.css';
import { AuthProvider } from '@/lib/auth';

export const metadata: Metadata = {
  title: { default: 'SIP-ENEM — Preparação intensiva com provas oficiais', template: '%s · SIP-ENEM' },
  description: 'Treine para o ENEM com provas oficiais anteriores, cartão-resposta, correção e plano de estudos.',
};

export const viewport: Viewport = { width: 'device-width', initialScale: 1 };

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="pt-BR" suppressHydrationWarning>
      <body>
        <AuthProvider>{children}</AuthProvider>
      </body>
    </html>
  );
}
