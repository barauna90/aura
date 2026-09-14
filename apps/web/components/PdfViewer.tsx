'use client';

import { useEffect, useState } from 'react';
import { API_URL, getTokens } from '@/lib/api';
import { Button } from './ui';

/**
 * Exibe o PDF oficial, inalterado, por meio do visualizador nativo do navegador.
 * O arquivo é obtido com autenticação e servido de um blob local — o conteúdo
 * nunca passa por OCR, IA ou reconstrução.
 */
export function PdfViewer({ bookletId, pageCount, page, onPage }: { bookletId: string; pageCount: number; page: number; onPage: (p: number) => void }) {
  const [blobUrl, setBlobUrl] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let url: string | null = null;
    const { access } = getTokens();
    fetch(`${API_URL}/api/exams/booklets/${bookletId}/pdf`, { headers: access ? { Authorization: `Bearer ${access}` } : {} })
      .then(async (r) => {
        if (!r.ok) throw new Error('Não foi possível carregar o caderno oficial.');
        url = URL.createObjectURL(await r.blob());
        setBlobUrl(url);
      })
      .catch((e) => setError(e.message));
    return () => {
      if (url) URL.revokeObjectURL(url);
    };
  }, [bookletId]);

  if (error) return <p className="p-4 text-sm text-danger">{error}</p>;
  if (!blobUrl) return <p className="p-4 text-sm text-muted">Carregando caderno oficial…</p>;

  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center justify-between gap-2 border-b border-border bg-surface px-3 py-2 text-sm">
        <Button size="sm" variant="secondary" onClick={() => onPage(Math.max(1, page - 1))} disabled={page <= 1} aria-label="Página anterior">
          ‹ Anterior
        </Button>
        <span>
          Página{' '}
          <input
            type="number"
            min={1}
            max={pageCount}
            value={page}
            onChange={(e) => onPage(Math.min(pageCount, Math.max(1, Number(e.target.value) || 1)))}
            className="w-14 rounded border border-border bg-surface px-1 text-center"
            aria-label="Número da página"
          />{' '}
          de {pageCount}
        </span>
        <Button size="sm" variant="secondary" onClick={() => onPage(Math.min(pageCount, page + 1))} disabled={page >= pageCount} aria-label="Próxima página">
          Próxima ›
        </Button>
      </div>
      <iframe
        key={page}
        title="Caderno oficial da prova"
        src={`${blobUrl}#page=${page}&toolbar=0&navpanes=0&view=FitH`}
        className="h-full w-full flex-1 bg-surface-2"
      />
    </div>
  );
}
