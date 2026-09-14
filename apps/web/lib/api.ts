'use client';

/**
 * Cliente HTTP do frontend. Guarda tokens no localStorage (access de curta
 * duração + refresh rotativo) e renova automaticamente em 401.
 */
export const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:3001';

const ACCESS_KEY = 'sip.access';
const REFRESH_KEY = 'sip.refresh';

export function getTokens() {
  if (typeof window === 'undefined') return { access: null, refresh: null };
  return { access: localStorage.getItem(ACCESS_KEY), refresh: localStorage.getItem(REFRESH_KEY) };
}

export function setTokens(t: { accessToken: string; refreshToken: string } | null) {
  if (typeof window === 'undefined') return;
  if (!t) {
    localStorage.removeItem(ACCESS_KEY);
    localStorage.removeItem(REFRESH_KEY);
    return;
  }
  localStorage.setItem(ACCESS_KEY, t.accessToken);
  localStorage.setItem(REFRESH_KEY, t.refreshToken);
}

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public body?: unknown,
  ) {
    super(message);
  }
}

let refreshing: Promise<boolean> | null = null;

async function tryRefresh(): Promise<boolean> {
  const { refresh } = getTokens();
  if (!refresh) return false;
  if (!refreshing) {
    refreshing = fetch(`${API_URL}/api/auth/refresh`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ refreshToken: refresh }),
    })
      .then(async (r) => {
        if (!r.ok) {
          setTokens(null);
          return false;
        }
        setTokens(await r.json());
        return true;
      })
      .catch(() => false)
      .finally(() => {
        refreshing = null;
      });
  }
  return refreshing;
}

export async function api<T = unknown>(
  path: string,
  init: RequestInit & { json?: unknown; retry?: boolean } = {},
): Promise<T> {
  const { json, retry = true, ...rest } = init;
  const { access } = getTokens();
  const headers: Record<string, string> = { ...(rest.headers as Record<string, string>) };
  if (json !== undefined) headers['Content-Type'] = 'application/json';
  if (access) headers['Authorization'] = `Bearer ${access}`;

  const res = await fetch(`${API_URL}/api${path}`, {
    ...rest,
    headers,
    body: json !== undefined ? JSON.stringify(json) : rest.body,
  });

  if (res.status === 401 && retry && (await tryRefresh())) {
    return api<T>(path, { ...init, retry: false });
  }
  if (res.status === 204) return undefined as T;
  const text = await res.text();
  const body = text ? safeJson(text) : null;
  if (!res.ok) {
    const message = (body as { message?: string | string[] })?.message;
    throw new ApiError(res.status, Array.isArray(message) ? message.join('; ') : (message ?? res.statusText), body);
  }
  return body as T;
}

function safeJson(t: string) {
  try {
    return JSON.parse(t);
  } catch {
    return t;
  }
}

export function pdfUrl(bookletId: string) {
  return `${API_URL}/api/exams/booklets/${bookletId}/pdf`;
}
