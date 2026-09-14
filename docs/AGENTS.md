# ENEM ORCHESTRATOR e agentes

O orquestrador é a composição da aplicação Laravel (`bootstrap/app.php`, `AppServiceProvider`, rotas com middleware `auth`/`role`). Cada "agente" da especificação é um serviço com responsabilidade única; nenhum deles escreve em conteúdo oficial fora do guardião.

| Agente (spec) | Implementação | Garante |
|---|---|---|
| ENEM OFFICIAL CONTENT GUARDIAN / OFFICIAL CONTENT AGENT | `Services/Content/GuardianRules`, `GuardianService` | Só `VERIFIED+PUBLISHED+OFFICIAL_INEP` aparece; checksum de PDF e gabarito; dois revisores distintos; alteração versionada despublica |
| Importação (CMS) | `Services/Content/ContentImportService`, `Admin/ContentController` | Tudo entra `PENDING/IMPORTED`; checksum na importação |
| EXAM ENGINE AGENT | `Services/Exam/ExamEngineService`, `TimerRules` | Cronômetro do servidor, sem pausa/extensão na Prova Real, expiração por estado/autosave/cron |
| ANSWER SHEET AGENT | `ExamEngineService::saveAnswers`, `resources/js/exam-runner.js` | Só o cartão é corrigido; autosave; bloqueio após encerramento |
| OBJECTIVE GRADING AGENT | `Services/Exam/GradingRules` | Idioma, anuladas, por área/disciplina, nunca "nota ENEM" |
| ESSAY AGENT | `Services/Essay/EssayService`, `essay-editor.js` | Folha sem IA; envio só após encerrar a Prova Real; limite de linhas da folha |
| REDACTION EVALUATION ORCHESTRATOR | `Services/Essay/EssayEvaluationOrchestrator` + `ZeroScoreRules` + `ScoringRules` + `EvaluatorPrompts` + job `EvaluateEssay` | Zero determinístico por regra da edição → A ∥ B independentes → C por divergência configurável → auditor de consistência → agregação |
| STUDY PLAN AGENT | `Services/Study/StudyPlanRules`, `StudyPlanService` | Priorização determinística por desempenho; só questões oficiais |
| Caderno de erros | `Services/Study/ErrorNotebookService` | Erros automáticos, anotações, repetição espaçada |
| ANALYTICS AGENT | `Services/AnalyticsService` | Dashboard, mapa por área, séries, tópicos fracos — nada suavizado |
| SUBSCRIPTION AGENT | `Services/Billing/SubscriptionService`, `AccessService` | Planos do banco; acesso premium só com status confirmado por webhook |
| PAYMENT AGENT | `Services/Billing/PaymentGateway`, `AsaasGateway`, `AsaasWebhookHandler` | Chave configurável no painel; webhook com token, idempotente |
| REFERRAL AGENT | `Services/Referral/CommissionRules`, `ReferralService` | Ciclo `PENDING→…→PAID`; antifraude |
| PROMOTION AGENT | `Services/Promotion/CouponRules`, `PromotionService` | Vigência, limites, planos permitidos |
| Bolsas | `Services/ScholarshipService` | Prazo/integral, patrocinadores com vagas |
| NOTIFICATION | `notifications` in-app (`Notification` model) | Pagamento confirmado, bolsa concedida |
| PROFESSOR ENEM IA + RAG | `Services/TutorService` | Contexto do aluno + resoluções verificadas + base oficial; bloqueado na Prova Real |
| AI provider | `Services/Ai/*` | Provider abstrato; registro de uso; sem escrita em conteúdo |
| SECURITY / COMPLIANCE | middleware `EnsureRole`, `ProfileController` (LGPD), `SettingsService` (criptografia) | RBAC, exportação/anonimização, segredos |
| ADMIN / AUDIT | `Admin/*Controllers`, `Services/AuditService` | Dashboard, alertas, `audit_logs` |
| QA AGENT | `tests/Unit/RulesTest.php`, `tests/Feature/*` | Regra de ouro: guardião + checksum + fluxo completo |

## Fluxos

**Prova**: `POST /provas/{exam}/iniciar` → `/sessao/{id}` (tela de início) → `POST …/iniciar` → runner (autosave `POST …/respostas`, `GET …/estado`) → `POST …/encerrar` ou expiração → `/sessao/{id}/resultado` → redação liberada.

**Redação**: `POST /redacao/proposta/{prompt}` ou `/redacao/sessao/{session}` → editor (`PUT …/rascunho`) → `POST …/enviar` → job → `/redacao/{id}/relatorio`.

**Publicação**: importação → `GET /admin/conteudo/{exam}/validar` → `POST …/etapa` por etapa → `PUBLISHED` propaga `VERIFIED`.

**Assinatura**: `POST /assinatura/assinar` → Asaas cria assinatura/cobrança → `/assinatura/pagamento/{id}` → webhook `POST /webhooks/asaas` → `ACTIVE` → comissão → cron libera após validação.
