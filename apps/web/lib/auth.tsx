'use client';

import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';
import { api, getTokens, setTokens } from './api';

export interface Me {
  id: string;
  email: string;
  role: 'STUDENT' | 'REVIEWER' | 'ADMIN' | 'SUPER_ADMIN';
  referralCode: string;
  profile: { fullName: string; onboardingDone: boolean; fontScale: number; theme: string } | null;
}

interface AuthCtx {
  user: Me | null;
  loading: boolean;
  refresh: () => Promise<void>;
  login: (email: string, password: string) => Promise<void>;
  register: (data: { email: string; password: string; fullName: string; referralCode?: string }) => Promise<void>;
  logout: () => Promise<void>;
}

const Ctx = createContext<AuthCtx | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<Me | null>(null);
  const [loading, setLoading] = useState(true);

  const refresh = useCallback(async () => {
    if (!getTokens().access) {
      setUser(null);
      setLoading(false);
      return;
    }
    try {
      const me = await api<Me>('/auth/me');
      setUser(me);
      applyPrefs(me);
    } catch {
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    refresh();
  }, [refresh]);

  const login = async (email: string, password: string) => {
    setTokens(await api('/auth/login', { method: 'POST', json: { email, password } }));
    await refresh();
  };
  const register = async (data: { email: string; password: string; fullName: string; referralCode?: string }) => {
    const referralCode = data.referralCode ?? readCookie('sip_ref') ?? undefined;
    setTokens(await api('/auth/register', { method: 'POST', json: { ...data, referralCode, acceptTerms: true, acceptPrivacy: true } }));
    await refresh();
  };
  const logout = async () => {
    const { refresh: r } = getTokens();
    if (r) await api('/auth/logout', { method: 'POST', json: { refreshToken: r } }).catch(() => undefined);
    setTokens(null);
    setUser(null);
  };

  return <Ctx.Provider value={{ user, loading, refresh, login, register, logout }}>{children}</Ctx.Provider>;
}

export function useAuth() {
  const ctx = useContext(Ctx);
  if (!ctx) throw new Error('useAuth fora do AuthProvider');
  return ctx;
}

function applyPrefs(me: Me) {
  const root = document.documentElement;
  const theme = me.profile?.theme ?? 'system';
  if (theme === 'system') root.removeAttribute('data-theme');
  else root.setAttribute('data-theme', theme);
  root.style.setProperty('--font-scale', `${me.profile?.fontScale ?? 100}%`);
}

function readCookie(name: string) {
  if (typeof document === 'undefined') return null;
  const m = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return m ? decodeURIComponent(m[1]) : null;
}
