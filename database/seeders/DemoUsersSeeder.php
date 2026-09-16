<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Scholarship;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuários de teste — um para cada perfil e plano. NUNCA rode em produção
 * (senhas conhecidas). Uso: php artisan db:seed --class=DemoUsersSeeder
 *
 * As assinaturas são criadas já ACTIVE localmente (sem passar pelo Asaas),
 * com um pagamento CONFIRMED de referência, para simular o pós-webhook.
 */
class DemoUsersSeeder extends Seeder
{
    public const PASSWORD = 'Teste123!';

    /** @return array<int, array{email:string, name:string, role:string, plan?:string, scholarship?:bool, code:string}> */
    public static function users(): array
    {
        return [
            ['email' => 'admin.teste@aura.local', 'name' => 'Admin Teste', 'role' => 'ADMIN', 'code' => 'ADMTST01'],
            ['email' => 'superadmin.teste@aura.local', 'name' => 'Super Admin Teste', 'role' => 'SUPER_ADMIN', 'code' => 'SUPTST01'],
            ['email' => 'revisor.teste@aura.local', 'name' => 'Revisor Teste', 'role' => 'REVIEWER', 'code' => 'REVTST01'],
            ['email' => 'aluno.redepublica@aura.local', 'name' => 'Ana Rede Pública', 'role' => 'STUDENT', 'plan' => 'REDE_PUBLICA', 'code' => 'ANARP001'],
            ['email' => 'aluno.estudante@aura.local', 'name' => 'Bruno Estudante', 'role' => 'STUDENT', 'plan' => 'ESTUDANTE', 'code' => 'BRUEST01'],
            ['email' => 'aluno.intensivo@aura.local', 'name' => 'Carla Intensivo', 'role' => 'STUDENT', 'plan' => 'INTENSIVO', 'code' => 'CARINT01'],
            ['email' => 'aluno.bolsista@aura.local', 'name' => 'Diego Bolsista', 'role' => 'STUDENT', 'scholarship' => true, 'code' => 'DIEBOL01'],
            ['email' => 'aluno.semplano@aura.local', 'name' => 'Elisa Sem Plano', 'role' => 'STUDENT', 'code' => 'ELISEM01'],
        ];
    }

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoUsersSeeder ignorado em produção.');

            return;
        }

        $admin = User::where('role', 'SUPER_ADMIN')->orderBy('id')->firstOrFail();

        foreach (self::users() as $u) {
            $user = User::updateOrCreate(['email' => $u['email']], [
                'name' => $u['name'], 'password' => self::PASSWORD, 'role' => $u['role'],
                'referral_code' => $u['code'], 'onboarding_done' => true, 'is_active' => true,
                'goal' => 'Aprovação no ENEM', 'weekly_hours' => 10,
            ]);

            if (! empty($u['plan'])) {
                $plan = Plan::where('code', $u['plan'])->firstOrFail();
                $sub = Subscription::updateOrCreate(['user_id' => $user->id, 'plan_id' => $plan->id], [
                    'status' => 'ACTIVE', 'gateway' => 'seed', 'billing_type' => 'PIX',
                    'gateway_subscription_id' => 'seed_sub_'.$user->id,
                    'current_period_start' => now()->startOfDay(), 'current_period_end' => now()->addMonth()->startOfDay(),
                ]);
                Payment::updateOrCreate(['gateway_payment_id' => 'seed_pay_'.$user->id], [
                    'user_id' => $user->id, 'subscription_id' => $sub->id, 'gateway' => 'seed', 'billing_type' => 'PIX',
                    'amount_cents' => $plan->price_cents, 'discount_cents' => 0, 'status' => 'CONFIRMED',
                    'due_date' => now()->toDateString(), 'confirmed_at' => now(),
                ]);
            }

            if (! empty($u['scholarship'])) {
                Scholarship::updateOrCreate(['user_id' => $user->id], [
                    'days' => 90, 'starts_at' => now(), 'ends_at' => now()->addDays(90), 'granted_by' => $admin->id,
                    'reason' => 'Bolsa de teste (seed)', 'active' => true,
                ]);
            }
        }

        $this->command?->table(['E-mail', 'Perfil', 'Plano / acesso', 'Senha'], array_map(fn ($u) => [
            $u['email'], $u['role'], $u['plan'] ?? (! empty($u['scholarship']) ? 'Bolsa 90 dias' : '—'), self::PASSWORD,
        ], self::users()));
    }
}
