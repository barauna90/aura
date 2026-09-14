<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\AccessService;
use App\Services\Referral\ReferralService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Checkout no Asaas (HTTP simulado) e liberação de acesso somente via webhook autenticado. */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Plan::create(['code' => 'FREE', 'name' => 'Grátis', 'price_cents' => 0, 'limits' => ['fullExamsPerMonth' => 1, 'essaysPerMonth' => 1], 'benefits' => []]);
        Plan::create(['code' => 'ESTUDANTE', 'name' => 'Estudante', 'price_cents' => 2990, 'trial_days' => 0, 'limits' => ['fullExamsPerMonth' => -1, 'essaysPerMonth' => 4, 'studyPlan' => true, 'tutor' => true], 'benefits' => []]);
        $settings = app(SettingsService::class);
        $settings->set('asaas.environment', 'sandbox');
        $settings->set('asaas.api_key', 'test-key');
        $settings->set('asaas.webhook_token', 'webhook-secret');

        Http::fake([
            'api-sandbox.asaas.com/v3/customers' => Http::response(['id' => 'cus_123']),
            'api-sandbox.asaas.com/v3/subscriptions' => Http::response(['id' => 'sub_abc', 'status' => 'ACTIVE']),
            'api-sandbox.asaas.com/v3/subscriptions/sub_abc/payments' => Http::response(['data' => [['id' => 'pay_1', 'status' => 'PENDING', 'value' => 29.9, 'dueDate' => '2026-09-15', 'invoiceUrl' => 'https://sandbox.asaas.com/i/pay_1', 'bankSlipUrl' => null]]]),
            'api-sandbox.asaas.com/v3/payments/pay_1/pixQrCode' => Http::response(['encodedImage' => 'AAA', 'payload' => '00020126PIX', 'expirationDate' => '2026-09-15 23:59:59']),
        ]);
    }

    public function test_checkout_creates_pending_subscription_and_webhook_activates_access_and_commission(): void
    {
        $referrer = User::factory()->create();
        $user = User::factory()->create(['referred_by_id' => $referrer->id]);

        $this->actingAs($user)->post('/assinatura/assinar', ['plan' => 'ESTUDANTE', 'billing_type' => 'PIX', 'cpf' => '123.456.789-09'])->assertRedirect();
        $sub = Subscription::where('user_id', $user->id)->firstOrFail();
        $payment = Payment::where('gateway_payment_id', 'pay_1')->firstOrFail();
        $this->assertSame('PENDING', $sub->status);
        $this->assertSame('sub_abc', $sub->gateway_subscription_id);
        $this->assertSame('00020126PIX', $payment->pix_payload);
        $this->assertSame(1000, $payment->discount_cents); // indicado ganha R$ 10 na assinatura
        $this->assertSame('FREE', app(AccessService::class)->resolve($user)['tier'] === 'FREE' ? 'FREE' : 'PREMIUM');
        Http::assertSent(fn ($r) => $r->url() === 'https://api-sandbox.asaas.com/v3/subscriptions' && $r->hasHeader('access_token', 'test-key') && $r['billingType'] === 'PIX' && $r['value'] === 19.9); // R$ 10 de desconto de indicação

        // Webhook sem token → rejeitado, nada muda.
        $this->postJson('/webhooks/asaas', ['event' => 'PAYMENT_CONFIRMED', 'payment' => ['id' => 'pay_1']])->assertStatus(401);
        $this->assertSame('PENDING', $sub->fresh()->status);

        // Webhook autenticado → ACTIVE + período + comissão pendente para o indicador.
        $payload = ['id' => 'evt_1', 'event' => 'PAYMENT_CONFIRMED', 'payment' => ['id' => 'pay_1', 'subscription' => 'sub_abc', 'value' => 29.9, 'billingType' => 'PIX']];
        $this->withHeaders(['asaas-access-token' => 'webhook-secret'])->postJson('/webhooks/asaas', $payload)->assertOk()->assertJson(['status' => 'ok']);
        $sub->refresh();
        $this->assertSame('ACTIVE', $sub->status);
        $this->assertTrue($sub->current_period_end->isFuture());
        $this->assertSame('CONFIRMED', $payment->fresh()->status);
        $this->assertSame('PREMIUM', app(AccessService::class)->resolve($user)['tier']);
        $commission = Commission::where('affiliate_id', $referrer->id)->firstOrFail();
        $this->assertSame('PENDING', $commission->status);
        $this->assertSame(1000, $commission->amount_cents); // R$ 10 fixos para o indicador
        $this->assertTrue($commission->available_at->between(now()->addDays(6), now()->addDays(8))); // bloqueio de 7 dias

        // Entrega "at least once": evento repetido é ignorado.
        $this->withHeaders(['asaas-access-token' => 'webhook-secret'])->postJson('/webhooks/asaas', $payload)->assertOk()->assertJson(['status' => 'duplicate']);
        $this->assertSame(1, Commission::count());

        // Estorno → REFUNDED, acesso revogado, comissão cancelada.
        $this->withHeaders(['asaas-access-token' => 'webhook-secret'])->postJson('/webhooks/asaas', ['id' => 'evt_2', 'event' => 'PAYMENT_REFUNDED', 'payment' => ['id' => 'pay_1']])->assertOk();
        $this->assertSame('REFUNDED', $sub->fresh()->status);
        $this->assertSame('FREE', app(AccessService::class)->resolve($user)['tier']);
        $this->assertSame('CANCELED', $commission->fresh()->status);
    }

    public function test_commission_is_not_released_if_referred_user_gives_up_within_hold_period(): void
    {
        $referrer = User::factory()->create();
        $user = User::factory()->create(['referred_by_id' => $referrer->id]);
        $this->actingAs($user)->post('/assinatura/assinar', ['plan' => 'ESTUDANTE', 'billing_type' => 'PIX', 'cpf' => '123.456.789-09'])->assertRedirect();
        $this->withHeaders(['asaas-access-token' => 'webhook-secret'])->postJson('/webhooks/asaas', ['id' => 'evt_1', 'event' => 'PAYMENT_CONFIRMED', 'payment' => ['id' => 'pay_1', 'subscription' => 'sub_abc', 'value' => 19.9]])->assertOk();
        $commission = Commission::where('affiliate_id', $referrer->id)->firstOrFail();
        $this->assertSame('PENDING', $commission->status);

        // Antes dos 7 dias nada é liberado.
        $this->assertSame(0, app(ReferralService::class)->releaseMatured());

        // Desistência dentro do prazo → comissão cancelada, mesmo depois do prazo.
        Http::fake(['api-sandbox.asaas.com/v3/subscriptions/sub_abc' => Http::response([], 200)]);
        $this->actingAs($user)->post('/assinatura/cancelar')->assertRedirect();
        $this->assertSame('CANCELED', $commission->fresh()->status);
        $this->travel(8)->days();
        $this->assertSame(0, app(ReferralService::class)->releaseMatured());

        // Sem desistência: liberada após o prazo.
        $other = User::factory()->create(['referred_by_id' => $referrer->id]);
        $sub = Subscription::create(['user_id' => $other->id, 'plan_id' => Plan::where('code', 'ESTUDANTE')->value('id'), 'status' => 'ACTIVE', 'gateway_subscription_id' => 'sub_ok', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        $this->withHeaders(['asaas-access-token' => 'webhook-secret'])->postJson('/webhooks/asaas', ['id' => 'evt_ok', 'event' => 'PAYMENT_CONFIRMED', 'payment' => ['id' => 'pay_ok', 'subscription' => 'sub_ok', 'value' => 19.9]])->assertOk();
        $ok = Commission::where('subscription_id', $sub->id)->firstOrFail();
        $this->assertSame('PENDING', $ok->status);
        $this->travel(8)->days();
        $this->assertSame(1, app(ReferralService::class)->releaseMatured());
        $this->assertSame('AVAILABLE', $ok->fresh()->status);
    }

    public function test_recurring_payment_unknown_locally_is_linked_by_subscription(): void
    {
        $user = User::factory()->create();
        $plan = Plan::where('code', 'ESTUDANTE')->first();
        $sub = Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'ACTIVE', 'gateway_subscription_id' => 'sub_xyz', 'current_period_start' => now()->subMonth(), 'current_period_end' => now()->addDay()]);
        $this->withHeaders(['asaas-access-token' => 'webhook-secret'])->postJson('/webhooks/asaas', ['id' => 'evt_9', 'event' => 'PAYMENT_RECEIVED', 'payment' => ['id' => 'pay_9', 'subscription' => 'sub_xyz', 'value' => 29.9, 'billingType' => 'BOLETO', 'dueDate' => '2026-10-15']])->assertOk();
        $this->assertDatabaseHas('payments', ['gateway_payment_id' => 'pay_9', 'status' => 'CONFIRMED', 'amount_cents' => 2990]);
        $this->assertTrue($sub->fresh()->current_period_end->gt(now()->addDays(25)));
    }

    public function test_admin_can_configure_asaas_key_encrypted(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put('/admin/configuracoes', [
            'asaas_environment' => 'production', 'asaas_api_key' => 'prod-key-1234', 'asaas_webhook_token' => 'tok', 'ai_provider' => 'mock', 'ai_model' => 'claude-opus-5',
            'essay_divergence_total' => 100, 'essay_divergence_competency' => 80,
        ])->assertRedirect();
        $this->assertSame('prod-key-1234', app(SettingsService::class)->get('asaas.api_key'));
        $this->assertNotSame('prod-key-1234', Setting::find('asaas.api_key')->value); // criptografada no banco
        $this->actingAs(User::factory()->create())->get('/admin/configuracoes')->assertForbidden();
    }
}
