<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 20)->default('STUDENT'); // STUDENT | REVIEWER | ADMIN | SUPER_ADMIN
            $table->boolean('is_active')->default(true);
            $table->string('referral_code', 32)->unique();
            $table->foreignId('referred_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cpf_hash', 64)->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->string('asaas_customer_id', 40)->nullable()->index();
            // Perfil / onboarding
            $table->string('goal', 60)->nullable();
            $table->date('target_exam_date')->nullable();
            $table->unsignedSmallInteger('weekly_hours')->nullable();
            $table->string('main_difficulty', 200)->nullable();
            $table->boolean('onboarding_done')->default(false);
            $table->unsignedSmallInteger('font_scale')->default(100);
            $table->string('theme', 10)->default('light');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // terms, privacy, marketing_email, web_push
            $table->string('version', 20);
            $table->boolean('granted');
            $table->timestamps();
            $table->index(['user_id', 'type']);
        });

        // Configurações administrativas (chaves de API ficam criptografadas).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->boolean('encrypted')->default(false);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('entity_type', 60)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['actor_id', 'created_at']);
        });

        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('level', 10); // INFO | WARN | ERROR
            $table->string('source', 60);
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ai_usages', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('model', 60);
            $table->string('purpose', 20); // ESSAY_EVAL | STUDY_PLAN | TUTOR | RAG
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cost_cents')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->boolean('success')->default(true);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['purpose', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
        Schema::dropIfExists('system_alerts');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('consents');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
