'use client';

import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useState, type FormEvent } from 'react';
import { useAuth } from '@/lib/auth';
import { Button, Card, ErrorBox, Field, Input } from './ui';

export function AuthForm({ mode }: { mode: 'login' | 'register' }) {
  const { login, register } = useAuth();
  const router = useRouter();
  const params = useSearchParams();
  const [form, setForm] = useState({ email: '', password: '', fullName: '', referralCode: params.get('ref') ?? '' });
  const [error, setError] = useState<{ message: string } | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      if (mode === 'login') await login(form.email, form.password);
      else await register({ email: form.email, password: form.password, fullName: form.fullName, referralCode: form.referralCode || undefined });
      router.replace(params.get('next') ?? (mode === 'register' ? '/onboarding' : '/dashboard'));
    } catch (err) {
      setError(err as { message: string });
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <Card className="w-full max-w-md">
        <Link href="/" className="text-lg font-semibold">
          SIP<span className="text-primary">·</span>ENEM
        </Link>
        <h1 className="mt-4 text-xl font-semibold">{mode === 'login' ? 'Entrar' : 'Criar conta gratuita'}</h1>
        <form onSubmit={submit} className="mt-6 space-y-4">
          {mode === 'register' && (
            <Field label="Nome completo" id="fullName">
              <Input id="fullName" required minLength={2} value={form.fullName} onChange={(e) => setForm({ ...form, fullName: e.target.value })} autoComplete="name" />
            </Field>
          )}
          <Field label="E-mail" id="email">
            <Input id="email" type="email" required value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} autoComplete="email" />
          </Field>
          <Field label="Senha" id="password" hint={mode === 'register' ? 'Mínimo de 8 caracteres.' : undefined}>
            <Input id="password" type="password" required minLength={8} value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} autoComplete={mode === 'login' ? 'current-password' : 'new-password'} />
          </Field>
          {mode === 'register' && (
            <>
              <Field label="Código de indicação (opcional)" id="ref">
                <Input id="ref" value={form.referralCode} onChange={(e) => setForm({ ...form, referralCode: e.target.value.toUpperCase() })} />
              </Field>
              <p className="text-xs text-muted">
                Ao criar a conta você aceita os <Link href="/ajuda#termos" className="underline">termos de uso</Link> e a{' '}
                <Link href="/ajuda#privacidade" className="underline">política de privacidade</Link> (LGPD).
              </p>
            </>
          )}
          <ErrorBox error={error} />
          <Button type="submit" className="w-full" disabled={busy}>
            {busy ? 'Aguarde…' : mode === 'login' ? 'Entrar' : 'Criar conta'}
          </Button>
        </form>
        <p className="mt-4 text-center text-sm text-muted">
          {mode === 'login' ? (
            <>
              Não tem conta? <Link href="/cadastro" className="text-primary underline">Cadastre-se</Link>
            </>
          ) : (
            <>
              Já tem conta? <Link href="/entrar" className="text-primary underline">Entrar</Link>
            </>
          )}
        </p>
      </Card>
    </div>
  );
}
