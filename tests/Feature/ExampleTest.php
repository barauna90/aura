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

    public function test_new_account_goes_straight_to_plan_selection_and_has_no_access_until_payment(): void
    {
        \App\Models\Plan::create(['code' => 'ESTUDANTE', 'name' => 'Estudante', 'price_cents' => 3990, 'limits' => ['fullExamsPerMonth' => -1, 'essaysPerMonth' => 8], 'benefits' => []]);
        $this->post('/cadastro', ['name' => 'Novo Aluno', 'email' => 'novo@aura.local', 'password' => 'Senha12345!', 'password_confirmation' => 'Senha12345!', 'accept_terms' => 1])
            ->assertRedirect('/assinatura');
        $this->assertAuthenticated();
        $this->get('/assinatura')->assertOk()->assertSee('Conta criada! Escolha seu plano')->assertSee('Escolha seu plano para começar');
        foreach (['/inicio', '/onboarding', '/provas', '/simulados', '/redacao', '/guia', '/indique'] as $path) {
            $this->get($path)->assertRedirect('/assinatura');
        }
        $this->get('/perfil')->assertOk();
        $this->post('/sair')->assertRedirect();
    }
}
