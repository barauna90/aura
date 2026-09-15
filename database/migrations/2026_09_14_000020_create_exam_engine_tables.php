<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained();
            $table->foreignId('exam_booklet_id')->constrained();
            $table->string('mode', 12); // PROVA_REAL | ESTUDO
            $table->string('status', 12)->default('CREATED'); // CREATED | IN_PROGRESS | PAUSED | FINISHED | EXPIRED
            $table->string('language', 10)->nullable();
            $table->json('selected_areas')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expected_end_at')->nullable(); // fonte da verdade do cronômetro
            $table->timestamp('paused_at')->nullable();
            $table->unsignedInteger('paused_seconds')->default(0);
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('time_used_seconds')->nullable();
            $table->string('device', 40)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'expected_end_at']);
        });

        Schema::create('answer_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('answer_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained();
            $table->unsignedSmallInteger('question_number');
            $table->char('option', 1)->nullable(); // marcada no CARTÃO-RESPOSTA (única considerada na correção)
            $table->char('draft_option', 1)->nullable(); // marcada no CADERNO (rascunho, não corrigida)
            $table->unsignedSmallInteger('change_count')->default(0);
            $table->timestamp('answered_at')->nullable();
            $table->unsignedInteger('time_spent_sec')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->unique(['answer_sheet_id', 'question_id']);
        });

        Schema::create('session_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('flagged')->default(false);
            $table->timestamps();
        });

        Schema::create('session_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('total_questions');
            $table->unsignedSmallInteger('correct');
            $table->unsignedSmallInteger('wrong');
            $table->unsignedSmallInteger('blank');
            $table->decimal('percent', 5, 1);
            $table->unsignedInteger('time_used_seconds');
            $table->decimal('avg_seconds_per_question', 8, 1);
            $table->unsignedSmallInteger('changed_answers');
            $table->json('by_area');
            $table->json('by_discipline')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['session_results', 'session_notes', 'answers', 'answer_sheets', 'exam_sessions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
