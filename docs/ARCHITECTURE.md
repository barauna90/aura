# Arquitetura

## Stack

- **Frontend**: Next.js 15 (App Router, React 19, TypeScript), Tailwind 4 com tokens CSS (tema claro/escuro, escala de fonte), sem bibliotecas de UI. PDF oficial exibido pelo visualizador nativo do navegador a partir de um blob autenticado.
- **Backend**: NestJS 11, Prisma 6, PostgreSQL 16. Redis/BullMQ opcional para a fila de correção de redação (fallback in-process). Object storage compatível com S3 via interface `ObjectStorage` (implementação padrão em disco).
- **Autenticação**: JWT de acesso (15 min) + refresh rotativo (30 dias, hash em banco, revogação), bcrypt (custo 12), RBAC hierárquico (`STUDENT < REVIEWER < ADMIN < SUPER_ADMIN`).
- **IA**: provider abstrato (`AiProvider`). `mock` para desenvolvimento/testes; `anthropic` usa o SDK oficial com adaptive thinking, fallback server-side e cache de prompt. Modelo configurável (`AI_ESSAY_MODEL`, padrão `claude-opus-5`).
- **Pagamentos**: provider abstrato (`PaymentProvider`) com `mock`; um gateway brasileiro é adicionado implementando `createCharge`, `cancelRecurring` e `verifyAndParseWebhook` (assinatura obrigatória).

## Módulos (serviços independentes)

```
auth · users · content (import + guardian + catálogo + storage) · exam-engine · essays
study (plano, caderno de erros, guia, metas, calendário) · analytics · subscriptions (+ access)
payments · referrals · promotions · scholarships · notifications · ai · knowledge-base · tutor
admin · audit
```

Cada módulo pode ser extraído para um serviço próprio: a comunicação é por interfaces de serviço e pelo banco, sem estado em memória compartilhado (exceto a fila).

## Modelo de dados

`apps/api/prisma/schema.prisma` — 57 tabelas, incluindo todas as entidades da seção 42: `users, profiles, roles (enum), plans, subscriptions, payments, coupons, promotions, referrals (ReferralSettings/User.referredBy), referral_clicks, commissions, withdrawals, exams, exam_editions, exam_booklets, exam_pages, questions, question_options, official_answers (+ official_answer_sets), exam_sessions, answer_sheets, answers, essays, essay_evaluations, essay_competencies (EssayCompetencyScore), study_topics, study_plans, study_tasks, error_notebook, favorites, achievements, notifications, content_sources, content_versions, audit_logs, scholarships, support_tickets` — mais `EssayFinalResult`, `EssayZeroRule` (regras de zero por edição), `OfficialDocument/Chunk` (RAG), `WebhookEvent` (idempotência), `Sponsor`, `Goal`, `SessionNote`, `SessionResult`, `AiUsage`, `SystemAlert`, `Consent`, `RefreshToken`.

## Cronômetro

Fonte da verdade: `ExamSession.startedAt` + `Exam.durationMinutes` (cadastrada por edição) → `expectedEndAt`. O cliente apenas exibe `remainingSeconds` calculado pelo servidor e reconcilia a cada 30 s; o autosave devolve o restante; um cron por minuto expira sessões abandonadas. Pausa só no Modo Estudo, deslocando `expectedEndAt` pelo tempo pausado.

## Autosave e resiliência

- Cartão-resposta: lote a cada 4 s, `visibilitychange`/`beforeunload`, reenfileiramento em falha e cópia local em `localStorage`. Nada é perdido por queda de conexão.
- Redação: rascunho salvo a cada 5 s no servidor e no `localStorage`.
- Retornar à sessão no Modo Prova Real nunca reinicia o tempo.

## Segurança

Helmet, CORS restrito, validação/whitelist de DTOs, rate limit global (120/min) e específico em login/registro, senhas bcrypt, refresh rotativo com revogação, RBAC, webhooks HMAC com comparação em tempo constante e idempotência por `eventId`, CPF e chaves PIX apenas como hash, auditoria de ações sensíveis, cabeçalhos de segurança no Next. MFA opcional está modelado (`mfaEnabled/mfaSecret`) e é um item do roadmap.

## Observabilidade

`AiUsage` (tokens, latência, custo, sucesso por finalidade), `SystemAlert` (falhas de webhook, correção, integridade, fraude), `AuditLog` (autor, ação, entidade, IP), `WebhookEvent` (payload, erro). O dashboard admin exibe alertas abertos e uso de IA do mês.

## Performance

PDF entregue com `ETag` = checksum e cache privado imutável; cliente carrega o PDF uma vez por sessão; catálogo filtrado no banco com índices; correção de redação em fila; páginas do Next pré-renderizadas onde não há parâmetros.

## Extensões previstas

- **RAG vetorial**: trocar a busca lexical de `KnowledgeBaseService.search` por pgvector mantendo o contrato.
- **Páginas como imagem**: `ExamPage.imageKey` está pronto para servir páginas renderizadas quando o navegador não exibir PDF.
- **E-mail/Push**: `NotificationsService.notify` já respeita consentimento; falta o transporte.
- **Gateway de pagamento**: implementar `PaymentProvider` (PIX, cartão recorrente, boleto) em `payments/providers`.
