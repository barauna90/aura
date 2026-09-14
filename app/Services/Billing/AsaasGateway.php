<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Integração Asaas (API v3). A chave de API e o ambiente são configurados pelo
 * administrador no painel (Configurações → Asaas) e ficam criptografados no banco.
 *
 * Endpoints usados: POST /customers, POST /subscriptions, GET /subscriptions/{id}/payments,
 * GET /payments/{id}/pixQrCode, DELETE /subscriptions/{id}.
 * Webhook: header `asaas-access-token` deve coincidir com o token configurado.
 */
class AsaasGateway implements PaymentGateway
{
    public function __construct(private readonly SettingsService $settings) {}

    public function name(): string
    {
        return 'asaas';
    }

    public function isConfigured(): bool
    {
        return $this->settings->isSet('asaas.api_key');
    }

    public function baseUrl(): string
    {
        return $this->settings->get('asaas.environment') === 'production'
            ? 'https://api.asaas.com/v3'
            : 'https://api-sandbox.asaas.com/v3';
    }

    public function ensureCustomer(User $user, ?string $cpf, ?string $phone): string
    {
        if ($user->asaas_customer_id) {
            return $user->asaas_customer_id;
        }
        $payload = array_filter([
            'name' => $user->name,
            'email' => $user->email,
            'cpfCnpj' => $cpf ? preg_replace('/\D/', '', $cpf) : null,
            'mobilePhone' => $phone ? preg_replace('/\D/', '', $phone) : null,
            'externalReference' => 'user:'.$user->id,
            'notificationDisabled' => false,
        ]);
        $data = $this->post('/customers', $payload);
        $user->forceFill(['asaas_customer_id' => $data['id']])->save();

        return $data['id'];
    }

    public function createSubscription(User $user, string $customerId, Plan $plan, string $billingType, int $amountCents, string $description, string $externalReference): array
    {
        $sub = $this->post('/subscriptions', [
            'customer' => $customerId,
            'billingType' => $billingType,
            'value' => round($amountCents / 100, 2),
            'nextDueDate' => now()->addDay()->toDateString(),
            'cycle' => 'MONTHLY',
            'description' => $description,
            'externalReference' => $externalReference,
        ]);
        $payments = $this->get("/subscriptions/{$sub['id']}/payments");
        $first = $payments['data'][0] ?? null;
        if (! $first) {
            throw new RuntimeException('Asaas não gerou a primeira cobrança da assinatura.');
        }
        $pix = $billingType === 'PIX' ? $this->pixQrCode($first['id']) : ['payload' => null, 'image' => null, 'expiration' => null];

        return [
            'subscription_id' => $sub['id'],
            'payment' => [
                'id' => $first['id'],
                'status' => $first['status'],
                'due_date' => $first['dueDate'],
                'invoice_url' => $first['invoiceUrl'] ?? null,
                'bank_slip_url' => $first['bankSlipUrl'] ?? null,
                'pix_payload' => $pix['payload'],
                'pix_image' => $pix['image'],
                'value' => (float) $first['value'],
            ],
        ];
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        $this->client()->delete("{$this->baseUrl()}/subscriptions/{$subscriptionId}")->throw();
    }

    public function pixQrCode(string $paymentId): array
    {
        try {
            $d = $this->get("/payments/{$paymentId}/pixQrCode");

            return ['payload' => $d['payload'] ?? null, 'image' => $d['encodedImage'] ?? null, 'expiration' => $d['expirationDate'] ?? null];
        } catch (\Throwable) {
            return ['payload' => null, 'image' => null, 'expiration' => null];
        }
    }

    public function validateWebhook(?string $token): bool
    {
        $expected = $this->settings->get('asaas.webhook_token');

        return $expected !== null && $expected !== '' && $token !== null && hash_equals($expected, $token);
    }

    /** Testa a chave configurada (usado pelo painel). */
    public function ping(): array
    {
        $r = $this->client()->get("{$this->baseUrl()}/myAccount");

        return ['ok' => $r->successful(), 'status' => $r->status(), 'account' => $r->successful() ? ($r->json('name') ?? $r->json('email')) : null];
    }

    private function client(): PendingRequest
    {
        $key = $this->settings->get('asaas.api_key');
        if (! $key) {
            throw new RuntimeException('Chave de API do Asaas não configurada. Configure em Administração → Configurações.');
        }

        return Http::withHeaders(['access_token' => $key, 'accept' => 'application/json'])
            ->withUserAgent('AuraSimulados/1.0')->timeout(30)->retry(2, 500, throw: false);
    }

    private function post(string $path, array $payload): array
    {
        $r = $this->client()->post($this->baseUrl().$path, $payload);
        if ($r->failed()) {
            $msg = $r->json('errors.0.description') ?? $r->body();
            throw new RuntimeException("Asaas {$path}: {$msg}");
        }

        return $r->json();
    }

    private function get(string $path): array
    {
        return $this->client()->get($this->baseUrl().$path)->throw()->json();
    }
}
