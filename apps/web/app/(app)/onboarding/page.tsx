'use client';

import { useRouter } from 'next/navigation';
import { useState, type FormEvent } from 'react';
import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { Button, Card, ErrorBox, Field, Input, PageHeader, Select } from '@/components/ui';

const GOALS = ['Medicina', 'Direito', 'Engenharia', 'Licenciaturas', 'Outro'];

export default function OnboardingPage() {
  const router = useRouter();
  const { refresh } = useAuth();
  const [form, setForm] = useState({ goal: 'Outro', targetExamDate: '', weeklyHours: 10, mainDifficulty: '' });
  const [err, setErr] = useState<{ message: string } | null>(null);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    try {
      await api('/study/onboarding', { method: 'POST', json: { ...form, weeklyHours: Number(form.weeklyHours), targetExamDate: form.targetExamDate || undefined } });
      await refresh();
      router.replace('/provas');
    } catch (e2) {
      setErr(e2 as { message: string });
    }
  };

  return (
    <div className="mx-auto max-w-xl">
      <PageHeader title="Vamos personalizar seu estudo" subtitle="Quatro perguntas rápidas. Depois, faça seu diagnóstico com questões oficiais." />
      <form onSubmit={submit}>
        <Card className="space-y-4">
          <Field label="Qual seu objetivo?" id="goal">
            <Select id="goal" value={form.goal} onChange={(e) => setForm({ ...form, goal: e.target.value })}>
              {GOALS.map((g) => (
                <option key={g}>{g}</option>
              ))}
            </Select>
          </Field>
          <Field label="Quando pretende fazer o ENEM?" id="date">
            <Input id="date" type="date" value={form.targetExamDate} onChange={(e) => setForm({ ...form, targetExamDate: e.target.value })} />
          </Field>
          <Field label="Quantas horas consegue estudar por semana?" id="hours">
            <Input id="hours" type="number" min={1} max={80} value={form.weeklyHours} onChange={(e) => setForm({ ...form, weeklyHours: Number(e.target.value) })} />
          </Field>
          <Field label="Qual sua maior dificuldade?" id="diff">
            <Input id="diff" value={form.mainDifficulty} onChange={(e) => setForm({ ...form, mainDifficulty: e.target.value })} placeholder="Ex.: matemática, tempo de prova, redação…" />
          </Field>
          <ErrorBox error={err} />
          <Button type="submit" size="lg" className="w-full">
            Salvar e fazer diagnóstico
          </Button>
        </Card>
      </form>
    </div>
  );
}
