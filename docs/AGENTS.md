# ENEM ORCHESTRATOR e agentes

O **ENEM ORCHESTRATOR** é a composição raiz da API (`apps/api/src/app.module.ts`). Cada "agente" da especificação é um módulo NestJS com responsabilidade única; a comunicação entre eles é por injeção de dependência e transações no banco — nunca por escrita direta em conteúdo oficial fora do guardião.

| Agente (spec) | Módulo | Responsabilidade | Regras que ele garante |
|---|---|---|---|
| ENEM ORCHESTRATOR | `app.module.ts` + guards globais | Autenticação, RBAC, rate limit, composição | JWT → Roles → Throttler em toda rota |
| ENEM OFFICIAL CONTENT GUARDIAN / OFFICIAL CONTENT AGENT | `content/guardian.*` | Integridade, versionamento, fluxo de auditoria | Só `VERIFIED+PUBLISHED+OFFICIAL_INEP` aparece; checksum de PDF e gabarito; dois revisores distintos |
| Importação (CMS) | `content/content-import.service.ts` | Cadastro de provas, cadernos, gabaritos, propostas, regras de zero | Tudo entra `PENDING/IMPORTED`; checksum calculado na importação |
| EXAM ENGINE AGENT | `exam-engine/exam-engine.service.ts` | Sessões, modos, cronômetro do servidor, encerramento automático | Prova Real: sem pausa/extensão; expiração pelo servidor + cron por minuto |
| ANSWER SHEET AGENT | `exam-engine` (`saveAnswers`) | Cartão-resposta com autosave e contagem de alterações | Só o cartão é corrigido; bloqueado após encerramento |
| OBJECTIVE GRADING AGENT | `exam-engine/grading.rules.ts` | Correção pelo gabarito oficial, por área/disciplina | Idioma escolhido; anuladas; nunca "nota ENEM" |
| ESSAY AGENT | `essays/essays.service.ts` | Folha/rascunho sem IA, envio pós-prova, relatório | Prova Real: envio só após encerrar |
| REDACTION EVALUATION ORCHESTRATOR | `essays/essay-evaluation.orchestrator.ts` | ZERO SCORE VALIDATOR → AVALIADOR A ∥ B → AVALIADOR C → CONSISTENCY AUDITOR → agregação | A e B independentes; C por divergência configurável; zero semântico só com maioria |
| STUDY PLAN AGENT / STUDY PLAN ORCHESTRATOR | `study/study-plan.*` | Plano regular e intensivo, calendário, metas | Priorização determinística por desempenho real; só questões oficiais |
| Caderno de erros | `study/error-notebook.service.ts` | Erros automáticos, anotações, repetição espaçada | — |
| ANALYTICS AGENT | `analytics/analytics.service.ts` | Dashboard, mapa de desempenho, séries, tópicos fracos | Números nunca "suavizados" |
| SUBSCRIPTION AGENT | `subscriptions/*` | Planos configuráveis, checkout, cancelamento, acesso | Acesso premium só com `TRIALING/ACTIVE` confirmados por webhook |
| PAYMENT AGENT | `payments/*` | Provider abstrato + webhooks assinados e idempotentes | Nunca confia no retorno do navegador |
| REFERRAL AGENT | `referrals/*` | Códigos, cliques, comissões, saques, antifraude | Ciclo `PENDING→…→PAID`; bloqueio por autoindicação/CPF/instrumento/chargeback |
| PROMOTION AGENT | `promotions/*` | Cupons e campanhas | Vigência, limites, planos permitidos |
| Bolsas | `scholarships/*` | Bolsas por prazo, acesso integral, patrocinadores com vagas | — |
| NOTIFICATION | `notifications/*` | In-app; e-mail/push respeitam consentimento; anti-spam | 1 notificação do mesmo tipo/dia |
| PROFESSOR ENEM IA | `tutor/*` | Tutor pós-prova com resultado + resoluções verificadas + base oficial | Bloqueado durante Prova Real; não inventa regras |
| RAG / BASE OFICIAL | `knowledge-base/*` | Busca em documentos oficiais versionados | Sem resultado → frase fixa |
| AI provider | `ai/*` | Provider abstrato (`mock`, `anthropic`), registro de uso/custo | IA sem escrita em conteúdo oficial |
| SECURITY AGENT | `common/guards`, `main.ts`, `auth/*` | Helmet, CORS, JWT + refresh rotativo, bcrypt, throttling, validação de DTO | — |
| COMPLIANCE AGENT (LGPD) | `users/*` | Consentimentos, exportação, exclusão/anonimização | Minimização (CPF só como hash) |
| ADMIN SERVICE | `admin/*` | Dashboard (usuários, MRR, churn, alertas, uso de IA), logs | — |
| AUDIT SERVICE | `audit/*` (global) | `AuditLog` e `SystemAlert` | Registros nunca removidos |
| QA AGENT | `*.spec.ts` | Testes das regras puras | Regra de ouro: guardião + checksum |
| PRODUCT AGENT | `apps/web` | Experiência do aluno e do admin | Disclaimers de `@sip-enem/shared` |

## Fluxos principais

**Prova (Modo Prova Real)**: `POST /sessions` (verifica acesso, idioma, cria cartão com as questões aplicáveis) → `POST /sessions/:id/start` (grava `startedAt` e `expectedEndAt`) → cliente faz autosave em `POST /sessions/:id/answers` e reconcilia `GET /sessions/:id` a cada 30 s → `POST /sessions/:id/finish` ou expiração (cron) → correção → `GET /sessions/:id/result` → redação liberada para envio.

**Redação**: `POST /essays` (sessão ou proposta) → `PUT /essays/:id/draft` (autosave) → `POST /essays/:id/submit` (fila) → orquestrador de avaliação → `GET /essays/:id/report`.

**Publicação de conteúdo**: importação → `GET /admin/content/exams/:id/validate` → `POST …/stage` por etapa → `PUBLISHED` propaga `VERIFIED` para cadernos, questões, gabaritos e fontes.

**Assinatura**: `POST /subscriptions/checkout` (cupom opcional) → provider cria cobrança → webhook assinado `POST /payments/webhook/:provider` → `ACTIVE` → comissão de indicação (`PENDING`) → cron diário libera após validação.
