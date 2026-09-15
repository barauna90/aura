<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assinatura, pagamentos (Asaas), cupons, promoções, indicação, bolsas.
 * Valores de plano/comissão vivem no banco — nunca no código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique(); // REDE_PUBLICA | ESTUDANTE | INTENSIVO
            $table->string('name', 80);
            $table->string('description', 300)->nullable();
            $table->unsignedInteger('price_cents');
            $table->unsignedTinyInteger('interval_months')->default(1);
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->json('limits');
            $table->json('benefits');
            $table->string('badge', 30)->nullable(); // ex.: PROMOÇÃO
            $table->boolean('is_featured')->default(false); // plano em destaque na vitrine
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('status', 12)->default('PENDING'); // PENDING | TRIALING | ACTIVE | PAST_DUE | CANCELED | EXPIRED | REFUNDED | SUSPENDED
            $table->string('gateway', 20)->default('asaas');
            $table->string('gateway_subscription_id', 40)->nullable()->index();
            $table->string('billing_type', 15)->nullable(); // PIX | CREDIT_CARD | BOLETO
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('coupon_id')->nullable()->index(); // tabela coupons é criada abaixo
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 20)->default('asaas');
            $table->string('gateway_payment_id', 40)->unique();
            $table->string('billing_type', 15);
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('discount_cents')->default(0);
            $table->string('status', 15)->default('PENDING'); // PENDING | CONFIRMED | FAILED | OVERDUE | REFUNDED | CHARGEBACK
            $table->date('due_date')->nullable();
            $table->string('invoice_url', 500)->nullable();
            $table->string('bank_slip_url', 500)->nullable();
            $table->text('pix_payload')->nullable();
            $table->string('instrument_hash', 64)->nullable(); // fingerprint antifraude — nunca o número
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 20);
            $table->string('event_id', 80);
            $table->string('type', 60);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['gateway', 'event_id']);
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('description', 300)->nullable();
            $table->string('kind', 25); // CUPOM | PRIMEIRO_MES | TESTE_GRATUITO | CAMPANHA_INDICACAO
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('type', 12); // PERCENT | FIXED | FIRST_MONTH | N_MONTHS | FREE_TRIAL
            $table->unsignedInteger('value');
            $table->unsignedTinyInteger('months')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedSmallInteger('max_uses_per_user')->default(1);
            $table->unsignedInteger('min_amount_cents')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('affiliate_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('coupon_plan', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'plan_id']);
        });

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['coupon_id', 'user_id']);
        });

        Schema::create('referral_settings', function (Blueprint $table) {
            $table->id();
            // Padrão do produto: R$ 10 de desconto para o indicado na assinatura; o indicador
            // resgata R$ 40 a cada 4 indicações com pagamento confirmado e validadas por 7 dias.
            $table->unsignedSmallInteger('milestone_referrals')->default(4);
            $table->unsignedInteger('milestone_reward_cents')->default(4000);
            $table->unsignedInteger('referred_discount_cents')->default(1000);
            $table->unsignedSmallInteger('validation_days')->default(7);
            $table->unsignedInteger('min_withdrawal_cents')->default(4000);
            $table->json('payout_methods');
            $table->timestamps();
        });

        Schema::create('referral_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['referrer_id', 'created_at']);
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('method', 20);
            $table->string('pix_key_hash', 64)->nullable();
            $table->string('status', 10)->default('REQUESTED'); // REQUESTED | APPROVED | PAID | REJECTED
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // Bônus do indicador: um registro a cada N indicações validadas (agrupa referral_conversions).
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->unsignedSmallInteger('conversions_count');
            $table->string('status', 10)->default('AVAILABLE'); // AVAILABLE | REQUESTED | PAID | CANCELED | REVERSED
            $table->string('blocked_reason', 300)->nullable();
            $table->foreignId('withdrawal_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['affiliate_id', 'status']);
        });

        // Indicação efetivada: primeiro pagamento CONFIRMADO de um indicado; validada após o prazo de bloqueio.
        Schema::create('referral_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10)->default('PENDING'); // PENDING | VALIDATED | CANCELED | REVERSED
            $table->timestamp('available_at')->nullable();
            $table->json('fraud_flags')->nullable();
            $table->string('blocked_reason', 300)->nullable();
            $table->foreignId('commission_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique('referred_user_id'); // cada indicado conta uma única vez
            $table->index(['affiliate_id', 'status']);
        });

        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->unsignedInteger('seats');
            $table->unsignedInteger('used_seats')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('scholarships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sponsor_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('days')->nullable(); // nulo = acesso integral
            $table->timestamp('starts_at')->useCurrent();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('granted_by')->constrained('users');
            $table->string('reason', 300)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['user_id', 'active']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10)->default('IN_APP');
            $table->string('kind', 30);
            $table->string('title', 120);
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject', 160);
            $table->text('body');
            $table->string('status', 12)->default('OPEN');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'support_tickets', 'notifications', 'scholarships', 'sponsors', 'referral_conversions', 'commissions', 'withdrawals', 'referral_clicks',
            'referral_settings', 'coupon_usages', 'coupon_plan', 'coupons', 'promotions', 'webhook_events', 'payments',
            'subscriptions', 'plans',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
