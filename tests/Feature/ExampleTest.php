<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_with_institutional_notice(): void
    {
        $this->get('/')->assertOk()->assertSee('Aura')->assertSee('não possui vínculo, patrocínio ou afiliação');
    }

    public function test_guests_are_redirected_and_webhook_rejects_missing_token(): void
    {
        $this->get('/inicio')->assertRedirect('/entrar');
        $this->postJson('/webhooks/asaas', ['event' => 'PAYMENT_CONFIRMED'])->assertStatus(401);
    }
}
