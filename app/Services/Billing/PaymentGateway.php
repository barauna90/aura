<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\User;

/**
 * Contrato do gateway de pagamento. Regras de negócio nunca dependem de um
 * provedor específico; a implementação padrão é o Asaas.
 */
interface PaymentGateway
{
    public function name(): string;

    /** Garante o cliente no gateway e devolve o id remoto. */
    public function ensureCustomer(User $user, ?string $cpf, ?string $phone): string;

    /**
     * Cria a assinatura recorrente e devolve a primeira cobrança.
     *
     * @return array{subscription_id:string, payment:array{id:string, status:string, due_date:string, invoice_url:?string, bank_slip_url:?string, pix_payload:?string, pix_image:?string, value:float}}
     */
    public function createSubscription(User $user, string $customerId, Plan $plan, string $billingType, int $amountCents, string $description, string $externalReference): array;

    public function cancelSubscription(string $subscriptionId): void;

    /** @return array{payload:?string, image:?string, expiration:?string} */
    public function pixQrCode(string $paymentId): array;

    /** Valida o token do webhook (header asaas-access-token). */
    public function validateWebhook(?string $token): bool;
}
