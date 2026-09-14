# Integração Asaas — cobrança e liberação automática de acesso

Implementação em `app/Services/Billing/` (`AsaasGateway`, `SubscriptionService`, `AsaasWebhookHandler`, `AccessService`) e `app/Http/Controllers/WebhookController.php`.

## Configuração (painel administrativo)

`Administração → Configurações`:

| Campo | Onde é usado |
|---|---|
| Ambiente (`sandbox` / `production`) | Base URL `https://api-sandbox.asaas.com/v3` ou `https://api.asaas.com/v3`. Chaves de sandbox e produção são independentes. |
| Chave de API | Enviada no header `access_token` em toda chamada. Guardada **criptografada** (`settings.value`, `Crypt::encryptString`). |
| Token do webhook | Comparado (em tempo constante) com o header `asaas-access-token` de cada notificação. Não use a chave de API como token. |

A tela mostra a URL do webhook a cadastrar no Asaas (`POST /webhooks/asaas`) e o botão **Testar conexão** (`GET /myAccount`). Os valores do banco têm prioridade sobre `ASAAS_*` no `.env`.

## Fluxo de assinatura

1. `POST /assinatura/assinar` (`SubscriptionController@checkout`) valida plano, forma de pagamento (`PIX`, `CREDIT_CARD`, `BOLETO`), CPF (obrigatório pelo Asaas; guardado só como hash) e cupom.
2. `AsaasGateway::ensureCustomer` → `POST /customers` (uma vez por usuário; `users.asaas_customer_id`).
3. Cria `subscriptions` local (`PENDING`, ou `TRIALING` se houver trial) e chama `POST /subscriptions` com `billingType`, `value`, `nextDueDate`, `cycle: MONTHLY`, `externalReference: sub:{id}`.
4. Busca a primeira cobrança em `GET /subscriptions/{id}/payments`; para PIX também `GET /payments/{id}/pixQrCode`. Grava `payments` (`PENDING`, `invoice_url`, `bank_slip_url`, `pix_payload`).
5. A tela `/assinatura/pagamento/{payment}` mostra QR Code PIX / boleto / link da fatura hospedada pelo Asaas (dados de cartão nunca passam pela plataforma) e recarrega a cada 15 s.

**O navegador nunca libera acesso.** `AccessService::resolve` só considera `subscriptions.status ∈ {TRIALING, ACTIVE}` com `current_period_end` futuro — e `ACTIVE` só é escrito pelo webhook.

## Webhook

`POST /webhooks/asaas` (sem CSRF; token obrigatório; responde 200 rapidamente).

- Idempotência: cada evento é gravado em `webhook_events` (`gateway + event_id` únicos); repetições retornam `{"status":"duplicate"}` (entrega "at least once" do Asaas).
- Cobranças recorrentes futuras chegam sem registro local e são vinculadas pela `payment.subscription` → `subscriptions.gateway_subscription_id`.

| Evento | Efeito |
|---|---|
| `PAYMENT_CONFIRMED`, `PAYMENT_RECEIVED` | `payments.status = CONFIRMED`; assinatura `ACTIVE`, período estendido em `interval_months`; notificação ao aluno; `ReferralService::onPaymentConfirmed` cria a comissão (`PENDING`, antifraude). |
| `PAYMENT_OVERDUE` | `OVERDUE`; assinatura `PAST_DUE` se o período já venceu. |
| `PAYMENT_REFUNDED` | `REFUNDED`; assinatura `REFUNDED`; comissões canceladas/revertidas. |
| `PAYMENT_CHARGEBACK_REQUESTED`, `PAYMENT_CHARGEBACK_DISPUTE` | `CHARGEBACK`; assinatura `SUSPENDED`; comissões revertidas. |
| `PAYMENT_DELETED` | cobrança pendente → `FAILED`. |
| demais | apenas registrados. |

Falhas geram `system_alerts` (visíveis no dashboard admin) e ficam em `webhook_events.error`.

## Cancelamento e expiração

`POST /assinatura/cancelar` chama `DELETE /subscriptions/{id}` no Asaas e marca `cancel_at_period_end`; o acesso continua até `current_period_end`. O agendador (`routes/console.php`, a cada hora) muda `ACTIVE/TRIALING` vencidos para `CANCELED`, `EXPIRED` ou `PAST_DUE`.

## Testando localmente

`tests/Feature/BillingTest.php` simula a API com `Http::fake` e envia webhooks assinados. Para testar com o sandbox real: configure a chave de sandbox, exponha a aplicação (ex.: túnel HTTPS), cadastre o webhook e use o painel de sandbox do Asaas para confirmar a cobrança.
