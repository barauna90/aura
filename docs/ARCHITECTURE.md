# Arquitetura

## Stack

- **PHP 8.3 · Laravel 13** (Blade + Vite + Tailwind 4, JavaScript vanilla para o runner da prova, folha de redação e checkout).
- **MySQL 8** em produção (`DB_CONNECTION=mysql`); SQLite em desenvolvimento/testes. Migrations usam tipos portáveis (`json`, `string` para enums).
- **Fila**: `QUEUE_CONNECTION=database` (job `EvaluateEssay`); `php artisan queue:work`.
- **Agendador**: `routes/console.php` (expira provas por minuto, assinaturas por hora, libera comissões diariamente).
- **Storage**: disco `official` (`storage/app/official`, nunca público) para PDFs do Inep, servidos por rota autenticada com `ETag` = checksum.
- **Pagamentos**: contrato `PaymentGateway` com implementação `AsaasGateway` (bind em `AppServiceProvider`).
- **IA**: contrato `AiProvider` (`MockAiProvider`, `AnthropicAiProvider` via Messages API com adaptive thinking, cache de prompt e fallback server-side). Provedor, chave e modelo configuráveis no painel.
- **Configurações**: `SettingsService` (banco → `.env` → padrão); segredos criptografados com `APP_KEY`.

## Estrutura

```
app/Support/            Disclaimers (textos obrigatórios), Enem (constantes de domínio)
app/Services/Content/   GuardianRules, GuardianService, ContentImportService
app/Services/Exam/      TimerRules, GradingRules, ExamEngineService
app/Services/Essay/     ZeroScoreRules, ScoringRules, EvaluatorPrompts, EssayEvaluationOrchestrator, EssayService
app/Services/Billing/   PaymentGateway, AsaasGateway, SubscriptionService, AsaasWebhookHandler, AccessService
app/Services/Referral/  CommissionRules, ReferralService
app/Services/Promotion/ CouponRules, PromotionService
app/Services/Study/     StudyPlanRules, StudyPlanService, ErrorNotebookService
app/Services/           AnalyticsService, TutorService (Professor IA + base oficial), ScholarshipService, SettingsService, AuditService
app/Http/Controllers/   área do aluno · Admin/ (conteúdo, planos, cupons, indicações, bolsas, usuários, configurações) · WebhookController
resources/views/        landing, auth/, app/ (aluno), admin/, layouts/, components/
resources/js/           exam-runner.js, essay-editor.js, subscription.js
```

Regras de negócio ficam em classes `*Rules` puras (sem banco) — testadas em `tests/Unit/RulesTest.php`; os `*Service` orquestram persistência e auditoria.

## Modelo de dados (54 tabelas)

Usuários/acesso (`users` com perfil e onboarding, `consents`, `settings`, `audit_logs`, `system_alerts`, `ai_usages`) · Conteúdo oficial (`content_sources`, `exam_editions`, `exams`, `exam_booklets`, `exam_pages`, `questions`, `question_options`, `official_answer_sets`, `official_answers`, `question_classifications`, `question_resolutions`, `essay_prompts`, `essay_zero_rules`, `official_documents(+chunks)`, `content_versions`, `study_topics`) · Execução (`exam_sessions`, `answer_sheets`, `answers`, `session_notes`, `session_results`) · Redação (`essays`, `essay_evaluations`, `essay_competency_scores`, `essay_final_results`) · Estudo (`study_materials`, `study_plans`, `study_tasks`, `error_notebook_entries`, `favorites`, `goals`, `achievements`, `user_achievements`) · Financeiro (`plans`, `subscriptions`, `payments`, `webhook_events`, `coupons`, `coupon_plan`, `coupon_usages`, `promotions`) · Indicação (`referral_settings`, `referral_clicks`, `commissions`, `withdrawals`) · Social (`sponsors`, `scholarships`) · `notifications`, `support_tickets`.

## Cronômetro e resiliência

Fonte da verdade: `exam_sessions.started_at + exams.duration_minutes → expected_end_at`. O cliente exibe o restante, envia o cartão em lote a cada 4 s (e em `visibilitychange`/`beforeunload`), reconcilia `GET /sessao/{id}/estado` a cada 30 s e guarda cópia em `localStorage`. Expiração é aplicada no servidor (no estado, no autosave e por cron). Pausa só no modo estudo (desloca `expected_end_at`). Retornar à sessão nunca reinicia o tempo.

## Segurança

Sessões com cookie `HttpOnly`, CSRF (exceto webhook, protegido por token e comparação em tempo constante), bcrypt, throttling em login/cadastro, RBAC hierárquico (`role:REVIEWER|ADMIN`), validação de todos os formulários, PDFs fora do document root, CPF e chave PIX apenas como hash, segredos criptografados, auditoria de ações sensíveis, cabeçalhos do Laravel padrão. MFA está modelado (roadmap).

## Acessibilidade e design

Tema escuro (referências: navy profundo, gradiente roxo→azul, acentos ciano/rosa) com tema claro opcional e escala de fonte por usuário (`data-theme`, `--font-scale`), foco visível, skip link, HTML semântico, `aria-pressed` no cartão-resposta, gráficos SVG com tabela oculta para leitores de tela, `prefers-reduced-motion`. Layout responsivo: sidebar no desktop, menu no mobile; o runner recomenda tela maior mas não bloqueia.
