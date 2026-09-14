/**
 * Leitura centralizada de variáveis de ambiente.
 * Nenhum valor de negócio (preço, limite, comissão) vive aqui — tudo isso é
 * configurável pelo administrador no banco de dados.
 */
import { config as loadDotenv } from 'dotenv';
import { existsSync } from 'fs';
import { resolve } from 'path';

// Carrega .env do próprio app ou da raiz do monorepo (o primeiro que existir).
for (const candidate of [resolve(process.cwd(), '.env'), resolve(process.cwd(), '../../.env'), resolve(__dirname, '../../../../.env')]) {
  if (existsSync(candidate)) {
    loadDotenv({ path: candidate });
    break;
  }
}

function str(name: string, fallback?: string): string {
  const v = process.env[name] ?? fallback;
  if (v === undefined) throw new Error(`Variável de ambiente obrigatória ausente: ${name}`);
  return v;
}
function num(name: string, fallback: number): number {
  const v = process.env[name];
  return v ? Number(v) : fallback;
}

export const env = {
  NODE_ENV: str('NODE_ENV', 'development'),
  API_PORT: num('API_PORT', 3001),
  WEB_ORIGIN: str('WEB_ORIGIN', 'http://localhost:3000'),
  REDIS_URL: process.env.REDIS_URL,
  JWT_ACCESS_SECRET: str('JWT_ACCESS_SECRET', 'dev-access-secret-change-me'),
  JWT_REFRESH_SECRET: str('JWT_REFRESH_SECRET', 'dev-refresh-secret-change-me'),
  JWT_ACCESS_TTL: str('JWT_ACCESS_TTL', '15m'),
  JWT_REFRESH_TTL_DAYS: num('JWT_REFRESH_TTL_DAYS', 30),
  AI_PROVIDER: str('AI_PROVIDER', 'mock'),
  ANTHROPIC_API_KEY: process.env.ANTHROPIC_API_KEY,
  AI_ESSAY_MODEL: str('AI_ESSAY_MODEL', 'claude-opus-5'),
  PAYMENT_PROVIDER: str('PAYMENT_PROVIDER', 'mock'),
  PAYMENT_WEBHOOK_SECRET: str('PAYMENT_WEBHOOK_SECRET', 'dev-webhook-secret'),
  LOCAL_STORAGE_DIR: str('LOCAL_STORAGE_DIR', './storage'),
  APP_URL: str('NEXT_PUBLIC_APP_URL', 'http://localhost:3000'),
};
