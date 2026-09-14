'use client';

import { useState, type FormEvent } from 'react';
import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { Button, Card, ErrorBox, Field, Input, Notice, PageHeader, Select } from '@/components/ui';

export default function ProfilePage() {
  const { user, refresh, logout } = useAuth();
  const [form, setForm] = useState({ fullName: user?.profile?.fullName ?? '', fontScale: user?.profile?.fontScale ?? 100, theme: user?.profile?.theme ?? 'system', cpf: '' });
  const [msg, setMsg] = useState<string | null>(null);
  const [err, setErr] = useState<{ message: string } | null>(null);
  const [pwd, setPwd] = useState('');

  const save = async (e: FormEvent) => {
    e.preventDefault();
    setErr(null);
    try {
      await api('/users/me/profile', { method: 'PUT', json: { fullName: form.fullName, fontScale: Number(form.fontScale), theme: form.theme, cpf: form.cpf || undefined } });
      await refresh();
      setMsg('Perfil atualizado.');
    } catch (e2) {
      setErr(e2 as { message: string });
    }
  };

  const exportData = async () => {
    const data = await api('/users/me/export');
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'meus-dados-sip-enem.json';
    a.click();
  };

  const remove = async () => {
    if (!window.confirm('Excluir sua conta? Seus dados pessoais serão anonimizados. Esta ação é irreversível.')) return;
    try {
      await api('/users/me', { method: 'DELETE', json: { password: pwd } });
      await logout();
    } catch (e) {
      setErr(e as { message: string });
    }
  };

  return (
    <div className="space-y-6">
      <PageHeader title="Perfil" />
      <form onSubmit={save}>
        <Card className="space-y-4">
          <h2 className="font-semibold">Dados</h2>
          <Field label="Nome completo" id="name">
            <Input id="name" value={form.fullName} onChange={(e) => setForm({ ...form, fullName: e.target.value })} />
          </Field>
          <Field label="E-mail" id="email">
            <Input id="email" value={user?.email ?? ''} disabled />
          </Field>
          <Field label="CPF (opcional)" id="cpf" hint="Armazenado apenas como hash para prevenir fraudes no programa de indicação.">
            <Input id="cpf" value={form.cpf} onChange={(e) => setForm({ ...form, cpf: e.target.value })} inputMode="numeric" />
          </Field>
          <h2 className="pt-2 font-semibold">Acessibilidade</h2>
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Tamanho da fonte" id="font">
              <Select id="font" value={form.fontScale} onChange={(e) => setForm({ ...form, fontScale: Number(e.target.value) })}>
                {[90, 100, 115, 130, 150].map((v) => (
                  <option key={v} value={v}>
                    {v}%
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="Tema" id="theme">
              <Select id="theme" value={form.theme} onChange={(e) => setForm({ ...form, theme: e.target.value })}>
                <option value="system">Automático</option>
                <option value="light">Claro</option>
                <option value="dark">Escuro</option>
              </Select>
            </Field>
          </div>
          <p className="text-xs text-muted">As opções de acessibilidade alteram apenas a interface; o conteúdo original das provas (PDF) nunca é modificado.</p>
          {msg && <Notice tone="success">{msg}</Notice>}
          <ErrorBox error={err} />
          <Button type="submit">Salvar</Button>
        </Card>
      </form>

      <Card className="space-y-3">
        <h2 className="font-semibold">Seus dados (LGPD)</h2>
        <p className="text-sm text-muted">Você pode consultar, exportar e excluir seus dados a qualquer momento.</p>
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" onClick={exportData}>
            Exportar meus dados (JSON)
          </Button>
        </div>
        <div className="mt-4 rounded-md border border-danger/30 p-4">
          <p className="text-sm font-medium">Excluir conta</p>
          <p className="text-xs text-muted">Cancele assinaturas ativas antes. Registros financeiros exigidos por lei são mantidos anonimizados.</p>
          <div className="mt-2 flex flex-wrap gap-2">
            <Input type="password" placeholder="Confirme sua senha" value={pwd} onChange={(e) => setPwd(e.target.value)} className="max-w-xs" aria-label="Senha" />
            <Button variant="danger" onClick={remove} disabled={!pwd}>
              Excluir minha conta
            </Button>
          </div>
        </div>
      </Card>
    </div>
  );
}
