# SIP-ENEM — Plataforma intensiva de preparação para o ENEM

Plataforma SaaS educacional para estudantes que não podem pagar um cursinho tradicional: provas oficiais anteriores exibidas **exatamente como o Inep publicou**, cronômetro real, cartão-resposta digital, correção pelo gabarito oficial, redação avaliada pelas cinco competências (dois avaliadores independentes + terceiro em caso de divergência), plano de estudos adaptativo, caderno de erros, assinatura acessível, programa de indicação e bolsas.

> **Princípio inegociável:** o sistema nunca inventa questões, alternativas, gabaritos, textos motivadores, temas, durações, notas oficiais ou regras do Inep. Todo conteúdo oficial guarda fonte, versão, checksum e status de auditoria, e só aparece como "prova oficial" quando `VERIFIED` + `PUBLISHED`. Veja [docs/OFFICIAL_CONTENT_POLICY.md](docs/OFFICIAL_CONTENT_POLICY.md).

## Estrutura do monorepo

```
apps/api        NestJS 11 + Prisma (PostgreSQL) — todos os serviços/agentes
apps/web        Next.js 15 (App Router) + Tailwind 4 — aluno e admin
packages/shared enums, disclaimers obrigatórios, menu, tipos
docs/           arquitetura, agentes, política de conteúdo, roadmap
tools/          importador de provas por manifesto
```

Documentação: [ARCHITECTURE](docs/ARCHITECTURE.md) · [AGENTS](docs/AGENTS.md) · [OFFICIAL_CONTENT_POLICY](docs/OFFICIAL_CONTENT_POLICY.md) · [ROADMAP](docs/ROADMAP.md)

## Rodando localmente

Pré-requisitos: Node 20+, PostgreSQL 16 (ou Docker). Redis e MinIO são opcionais em desenvolvimento.

```bash
cp .env.example .env            # ajuste DATABASE_URL e segredos
docker compose up -d postgres   # (ou use um Postgres já instalado)
npm install
npm run db:generate
npm run prisma:deploy -w @sip-enem/api   # aplica prisma/migrations
npm run db:seed                 # planos, admin, revisor, tópicos do guia — NÃO cria provas
npm run dev:api                 # http://localhost:3001/api
npm run dev:web                 # http://localhost:3000
```

Credenciais criadas pelo seed (troque em produção): `admin@sip-enem.local / Admin123!Troque` e `revisor@sip-enem.local / Revisor123!Troque`.

Sem Redis, as correções de redação rodam em processo. Sem `ANTHROPIC_API_KEY`, o provider de IA é `mock` (avaliação determinística para desenvolvimento). Sem gateway de pagamento, o provider é `mock` e os webhooks podem ser simulados:

```bash
# body assinado com HMAC-SHA256 usando PAYMENT_WEBHOOK_SECRET
curl -X POST http://localhost:3001/api/payments/webhook/mock \
  -H "Content-Type: application/json" -H "x-signature: <hmac>" \
  -d '{"eventId":"evt_1","type":"PAYMENT_CONFIRMED","providerRef":"mock_...","amountCents":1990}'
```

## Testes

```bash
npm test -w @sip-enem/api
```

49 testes cobrem: cronômetro (sem pausa/extensão no Modo Prova Real, encerramento automático, retomada no modo estudo), correção objetiva (idioma, anuladas, em branco, por área/disciplina, ausência de "nota ENEM"), guardião de conteúdo (fonte oficial, checksum do gabarito, fluxo de auditoria com revisores distintos), redação (validador de zero versionado por edição, agregação A/B/C, auditor de consistência), comissões e antifraude, cupons, plano de estudos e repetição espaçada, assinatura de webhook.

## Importando uma prova oficial

1. Baixe o caderno e o gabarito oficiais no site do Inep.
2. Em `/admin/conteudo`: cadastre a prova (ano, aplicação, dia, **duração oficial daquela edição**, URL e versão), adicione o caderno (PDF — o checksum é calculado na importação), registre o gabarito (número; área; letra; idioma; página) e a proposta de redação (transcrição fiel).
3. **Validar agora** → avance `IMPORTED → AUTO_VALIDATED → HUMAN_REVIEW_1 → HUMAN_REVIEW_2 → PUBLISHED`. As duas revisões humanas exigem pessoas diferentes; publicar exige ADMIN e reverifica checksum do PDF e do gabarito.

Ou por linha de comando: `node tools/import-exam.mjs manifest.json --email admin@... --password ...` (veja `tools/manifest.example.json`).

## Avisos institucionais

Esta plataforma é independente e não possui vínculo, patrocínio ou afiliação com o Instituto Nacional de Estudos e Pesquisas Educacionais Anísio Teixeira — Inep — ou com o Ministério da Educação. Resultados são educacionais; estimativas de nota não equivalem ao resultado oficial (o Inep utiliza a TRI).
