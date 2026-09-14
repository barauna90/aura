'use client';

import { useParams, useRouter } from 'next/navigation';
import { useEffect } from 'react';
import { api } from '@/lib/api';
import { Spinner } from '@/components/ui';

/** Link de indicação: plataforma.com.br/r/CODIGO → registra clique, grava cookie e envia ao cadastro. */
export default function ReferralLanding() {
  const { code } = useParams<{ code: string }>();
  const router = useRouter();
  useEffect(() => {
    const c = String(code).toUpperCase();
    api(`/referrals/click/${c}`, { method: 'POST' }).catch(() => undefined);
    document.cookie = `sip_ref=${encodeURIComponent(c)}; path=/; max-age=${30 * 86400}; SameSite=Lax`;
    router.replace(`/cadastro?ref=${c}`);
  }, [code, router]);
  return <Spinner label="Redirecionando…" />;
}
