'use client';

import { ANSWER_OPTIONS, type AnswerOption } from '@sip-enem/shared';

export interface SheetRow {
  questionId: string;
  questionNumber: number;
  option: AnswerOption | null;
}

/**
 * Cartão-resposta digital. Somente o que está marcado aqui é corrigido.
 * Acessível por teclado: cada bolha é um botão com estado aria-pressed.
 */
export function AnswerSheet({ rows, locked, onMark }: { rows: SheetRow[]; locked: boolean; onMark: (questionId: string, option: AnswerOption | null) => void }) {
  const answered = rows.filter((r) => r.option).length;
  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center justify-between border-b border-border px-3 py-2 text-sm">
        <span className="font-medium">Cartão-resposta</span>
        <span className="text-muted">
          {answered} respondidas · {rows.length - answered} em branco · {rows.length} total
        </span>
      </div>
      <ol className="flex-1 overflow-y-auto p-2" aria-label="Questões do cartão-resposta">
        {rows.map((r) => (
          <li key={r.questionId} className="flex items-center gap-2 border-b border-border/60 px-1 py-1.5 last:border-0">
            <span className="w-9 shrink-0 text-right text-sm tabular-nums text-muted">{r.questionNumber}</span>
            <div role="group" aria-label={`Questão ${r.questionNumber}`} className="flex gap-1.5">
              {ANSWER_OPTIONS.map((opt) => {
                const on = r.option === opt;
                return (
                  <button
                    key={opt}
                    type="button"
                    disabled={locked}
                    aria-pressed={on}
                    aria-label={`Questão ${r.questionNumber}, alternativa ${opt}`}
                    onClick={() => onMark(r.questionId, on ? null : opt)}
                    className={`h-8 w-8 rounded-full border text-xs font-semibold transition ${
                      on ? 'border-text bg-text text-surface' : 'border-border bg-surface hover:border-text'
                    } disabled:cursor-not-allowed disabled:opacity-60`}
                  >
                    {opt}
                  </button>
                );
              })}
            </div>
          </li>
        ))}
      </ol>
    </div>
  );
}
