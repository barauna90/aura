#!/usr/bin/env node
/**
 * Importa uma prova oficial a partir de um manifesto JSON + PDFs oficiais,
 * usando a API administrativa (mesmo fluxo do painel /admin/conteudo).
 *
 * Uso:
 *   node tools/import-exam.mjs manifest.json --api http://localhost:3001 --email admin@sip-enem.local --password 'Admin123!Troque'
 *
 * Formato do manifesto (exemplo em tools/manifest.example.json):
 * {
 *   "exam": { "year": 2023, "application": "REGULAR", "day": 1, "title": "...", "durationMinutes": 330,
 *             "areas": ["LINGUAGENS","HUMANAS","REDACAO"], "sourceUrl": "https://download.inep.gov.br/...", "documentVersion": "v1" },
 *   "booklets": [ { "color": "AZUL", "label": "Caderno 1 — Azul", "pageCount": 32, "pdf": "./2023-d1-azul.pdf",
 *                   "sourceUrl": "...", "documentVersion": "v1",
 *                   "answerKey": { "sourceUrl": "...", "documentVersion": "v1", "gabaritoPdf": "./gabarito.pdf",
 *                                  "answers": [ { "number": 1, "area": "LINGUAGENS", "correct": "B", "foreignLanguage": "INGLES", "page": 2 }, ... ] } } ],
 *   "essayPrompt": { "theme": "...", "motivatingTexts": [ { "title": "Texto I", "body": "..." } ], "sourceUrl": "...", "documentVersion": "v1", "maxLines": 30 }
 * }
 *
 * O conteúdo entra como PENDING/IMPORTED. A publicação continua exigindo o fluxo
 * de auditoria (validação automática → revisão 1 → revisão 2 → publicar).
 */
import { readFile } from 'node:fs/promises';
import { resolve, dirname } from 'node:path';

const args = process.argv.slice(2);
const manifestPath = args.find((a) => !a.startsWith('--'));
const opt = (name, fallback) => {
  const i = args.indexOf(`--${name}`);
  return i >= 0 ? args[i + 1] : fallback;
};
if (!manifestPath) {
  console.error('Informe o caminho do manifesto JSON.');
  process.exit(1);
}
const API = opt('api', 'http://localhost:3001');
const email = opt('email');
const password = opt('password');
if (!email || !password) {
  console.error('Informe --email e --password de um ADMIN.');
  process.exit(1);
}

const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
const base = dirname(resolve(manifestPath));

const login = await fetch(`${API}/api/auth/login`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email, password }) });
if (!login.ok) throw new Error(`Login falhou: ${login.status}`);
const { accessToken } = await login.json();
const auth = { Authorization: `Bearer ${accessToken}` };

async function post(path, body, isForm = false) {
  const res = await fetch(`${API}/api${path}`, {
    method: 'POST',
    headers: isForm ? auth : { ...auth, 'Content-Type': 'application/json' },
    body: isForm ? body : JSON.stringify(body),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(`${path} → ${res.status}: ${JSON.stringify(json)}`);
  return json;
}

// 1) Prova
const examForm = new FormData();
for (const [k, v] of Object.entries(manifest.exam)) examForm.set(k, Array.isArray(v) ? v.join(',') : String(v));
const exam = await post('/admin/content/exams', examForm, true);
console.log(`Prova criada: ${exam.id} (${exam.title})`);

// 2) Cadernos + gabaritos
for (const b of manifest.booklets ?? []) {
  const form = new FormData();
  form.set('color', b.color);
  form.set('label', b.label);
  form.set('pageCount', String(b.pageCount));
  form.set('sourceUrl', b.sourceUrl);
  form.set('documentVersion', b.documentVersion);
  form.set('pdf', new Blob([await readFile(resolve(base, b.pdf))], { type: 'application/pdf' }), 'caderno.pdf');
  const booklet = await post(`/admin/content/exams/${exam.id}/booklets`, form, true);
  console.log(`  Caderno ${booklet.label}: ${booklet.id} · checksum ${booklet.pdfChecksum.slice(0, 12)}…`);

  if (b.answerKey) {
    const ak = b.answerKey;
    const set = await post(`/admin/content/booklets/${booklet.id}/answer-key`, { sourceUrl: ak.sourceUrl, documentVersion: ak.documentVersion, answers: ak.answers });
    console.log(`  Gabarito registrado: ${set.id} · ${ak.answers.length} questões · checksum ${set.checksum.slice(0, 12)}…`);
  }
}

// 3) Redação
if (manifest.essayPrompt) {
  const p = await post(`/admin/content/exams/${exam.id}/essay-prompt`, manifest.essayPrompt);
  console.log(`  Proposta de redação: ${p.id}`);
}

console.log('\nImportação concluída como PENDING. Próximos passos no painel /admin/conteudo:');
console.log('  1. Validar agora (estrutura + integridade)');
console.log('  2. Avançar: AUTO_VALIDATED → HUMAN_REVIEW_1 (revisor A) → HUMAN_REVIEW_2 (revisor B) → PUBLISHED (admin)');
