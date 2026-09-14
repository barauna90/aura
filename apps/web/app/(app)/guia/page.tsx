'use client';

import { useState } from 'react';
import { EXAM_AREAS, EXAM_AREA_LABEL, EXAM_AREA_SHORT, type ExamArea } from '@sip-enem/shared';
import { useApi } from '@/lib/hooks';
import { Badge, Button, Card, Empty, ErrorBox, Notice, PageHeader, Spinner } from '@/components/ui';

interface Guide {
  notice: string;
  topics: Array<{
    id: string;
    area: ExamArea;
    discipline: string;
    name: string;
    description: string | null;
    recurrence: number;
    recurrenceLabel: string | null;
    matrixSkill: string | null;
    matrixLabel: string | null;
    materials: Array<{ id: string; title: string }>;
  }>;
}

export default function GuidePage() {
  const [area, setArea] = useState<ExamArea | ''>('');
  const { data, error, loading } = useApi<Guide>(`/study/guide${area ? `?area=${area}` : ''}`, [area]);

  return (
    <div className="space-y-6">
      <PageHeader title="Guia ENEM — o que estudar" subtitle="Organizado pelos grandes eixos da prova, com base na Matriz de Referência e no conteúdo efetivamente presente nas provas oficiais." />
      <Notice tone="neutral">Nunca afirmamos que um conteúdo “vai cair”. Indicamos o que é recorrente nas provas analisadas e o que é relevante na Matriz de Referência.</Notice>
      <div className="flex flex-wrap gap-1">
        <Button size="sm" variant={area === '' ? 'primary' : 'secondary'} onClick={() => setArea('')}>
          Todos
        </Button>
        {EXAM_AREAS.map((a) => (
          <Button key={a} size="sm" variant={area === a ? 'primary' : 'secondary'} onClick={() => setArea(a)}>
            {EXAM_AREA_SHORT[a]}
          </Button>
        ))}
      </div>
      {loading && <Spinner />}
      <ErrorBox error={error} />
      {data && data.topics.length === 0 && <Empty title="Nenhum tópico verificado ainda." />}
      {data &&
        EXAM_AREAS.filter((a) => !area || a === area).map((a) => {
          const topics = data.topics.filter((t) => t.area === a);
          if (!topics.length) return null;
          return (
            <section key={a}>
              <h2 className="mb-3 font-semibold">{EXAM_AREA_LABEL[a]}</h2>
              <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                {topics.map((t) => (
                  <Card key={t.id}>
                    <p className="text-xs text-muted">{t.discipline}</p>
                    <h3 className="font-medium">{t.name}</h3>
                    {t.description && <p className="mt-1 text-sm text-muted">{t.description}</p>}
                    <div className="mt-2 flex flex-wrap gap-1">
                      {t.recurrence > 0 && <Badge tone="success">{t.recurrence} questões oficiais classificadas — {t.recurrenceLabel}</Badge>}
                      {t.matrixSkill && <Badge tone="primary">{t.matrixSkill} — {t.matrixLabel}</Badge>}
                    </div>
                    {t.materials.length > 0 && (
                      <ul className="mt-2 text-sm">
                        {t.materials.map((m) => (
                          <li key={m.id} className="text-primary underline">
                            {m.title}
                          </li>
                        ))}
                      </ul>
                    )}
                  </Card>
                ))}
              </div>
            </section>
          );
        })}
    </div>
  );
}
