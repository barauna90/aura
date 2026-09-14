<?php

namespace App\Services\Billing;

use App\Models\Notification;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use App\Services\AuditService;
use App\Services\Referral\ReferralService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processa webhooks do Asaas. O acesso premium depende EXCLUSIVAMENTE do que
 * chega aqui (token validado + idempotente por evento). Entrega "at least once":
 * eventos repetidos são ignorados.
 */
class AsaasWebhookHandler
{
    public function __construct(private readonly AuditService $audit, private readonly ReferralService $referrals) {}

    /** @return array{status:string} */
    public function handle(array $payload): array
    {
        $event = (string) ($payload['event'] ?? '');
        $pay = $payload['payment'] ?? [];
        $eventId = (string) ($payload['id'] ?? ($event.':'.($pay['id'] ?? '').':'.($payload['dateCreated'] ?? '')));
        if ($eventId === '') {
            return ['status' => 'ignored'];
        }

        $stored = WebhookEvent::firstOrCreate(['gateway' => 'asaas', 'event_id' => $eventId], ['type' => $event, 'payload' => $payload]);
        if (! $stored->wasRecentlyCreated) {
            return ['status' => 'duplicate'];
        }

        try {
            $this->apply($event, $pay, $payload);
            $stored->update(['processed_at' => now()]);

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            $stored->update(['error' => $e->getMessage()]);
            $this->audit->alert('ERROR', 'asaas.webhook', "Falha ao processar {$event}: {$e->getMessage()}", ['event_id' => $eventId]);
            Log::error('Webhook Asaas', ['event' => $event, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function apply(string $event, array $pay, array $payload): void
    {
        if (! isset($pay['id'])) {
            return;
        }
        $payment = Payment::with('subscription.plan')->where('gateway_payment_id', $pay['id'])->first();

        // Cobranças recorrentes futuras chegam sem registro local: vincula pela assinatura.
        if (! $payment && ! empty($pay['subscription'])) {
            $sub = Subscription::with('plan')->where('gateway_subscription_id', $pay['subscription'])->first();
            if ($sub) {
                $payment = Payment::create([
                    'user_id' => $sub->user_id, 'subscription_id' => $sub->id, 'gateway' => 'asaas', 'gateway_payment_id' => $pay['id'],
                    'billing_type' => $pay['billingType'] ?? $sub->billing_type ?? 'UNDEFINED',
                    'amount_cents' => (int) round(((float) ($pay['value'] ?? 0)) * 100),
                    'status' => 'PENDING', 'due_date' => $pay['dueDate'] ?? null,
                    'invoice_url' => $pay['invoiceUrl'] ?? null, 'bank_slip_url' => $pay['bankSlipUrl'] ?? null,
                ]);
                $payment->setRelation('subscription', $sub);
            }
        }
        if (! $payment) {
            $this->audit->alert('WARN', 'asaas.webhook', "Pagamento {$pay['id']} não encontrado ({$event})");

            return;
        }

        switch ($event) {
            case 'PAYMENT_CONFIRMED':
            case 'PAYMENT_RECEIVED':
                if ($payment->status === 'CONFIRMED') {
                    return;
                }
                $payment->update([
                    'status' => 'CONFIRMED', 'confirmed_at' => now(), 'raw' => $payload,
                    'instrument_hash' => isset($pay['creditCard']['creditCardNumber']) ? hash('sha256', $pay['creditCard']['creditCardBrand'].$pay['creditCard']['creditCardNumber']) : null,
                ]);
                if ($sub = $payment->subscription) {
                    $months = $sub->plan->interval_months ?: 1;
                    $base = $sub->current_period_end->isFuture() && $sub->status === 'ACTIVE' ? $sub->current_period_end : now();
                    $sub->update(['status' => 'ACTIVE', 'current_period_start' => now(), 'current_period_end' => $base->copy()->addMonths($months)]);
                    $this->audit->log('subscription.active', null, 'Subscription', $sub->id, ['payment' => $pay['id']]);
                    Notification::create(['user_id' => $sub->user_id, 'kind' => 'PAYMENT', 'title' => 'Pagamento confirmado', 'body' => 'Seu acesso premium está liberado. Bons estudos!', 'sent_at' => now()]);
                    $this->referrals->onPaymentConfirmed($payment);
                }
                break;

            case 'PAYMENT_OVERDUE':
                $payment->update(['status' => 'OVERDUE', 'raw' => $payload]);
                if (($sub = $payment->subscription) && $sub->status === 'ACTIVE' && $sub->current_period_end->isPast()) {
                    $sub->update(['status' => 'PAST_DUE']);
                }
                break;

            case 'PAYMENT_REFUNDED':
                $payment->update(['status' => 'REFUNDED', 'refunded_at' => now(), 'raw' => $payload]);
                $payment->subscription?->update(['status' => 'REFUNDED']);
                $this->referrals->onPaymentReversed($payment, 'REFUND');
                break;

            case 'PAYMENT_CHARGEBACK_REQUESTED':
            case 'PAYMENT_CHARGEBACK_DISPUTE':
                $payment->update(['status' => 'CHARGEBACK', 'raw' => $payload]);
                $payment->subscription?->update(['status' => 'SUSPENDED']);
                $this->referrals->onPaymentReversed($payment, 'CHARGEBACK');
                break;

            case 'PAYMENT_DELETED':
                if ($payment->status === 'PENDING') {
                    $payment->update(['status' => 'FAILED', 'raw' => $payload]);
                }
                break;

            default:
                // PAYMENT_CREATED, PAYMENT_UPDATED, PAYMENT_BANK_SLIP_VIEWED etc.: apenas registrados.
                break;
        }
    }
}
