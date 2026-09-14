<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('essays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_session_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('essay_prompt_id')->constrained();
            $table->longText('draft_text')->nullable();
            $table->longText('final_text')->nullable();
            $table->unsignedSmallInteger('line_count')->nullable();
            $table->string('status', 12)->default('DRAFT'); // DRAFT | SUBMITTED | EVALUATING | EVALUATED | FAILED
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('essay_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('essay_id')->constrained()->cascadeOnDelete();
            $table->char('evaluator', 1); // A | B | C
            $table->string('provider', 30);
            $table->string('model', 60);
            $table->boolean('zero_score')->default(false);
            $table->string('zero_reason', 40)->nullable();
            $table->unsignedSmallInteger('total');
            $table->json('positives');
            $table->json('improvements');
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });

        Schema::create('essay_competency_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('essay_evaluation_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('competency');
            $table->unsignedSmallInteger('score');
            $table->text('justification');
            $table->json('problematic_excerpts');
            $table->unique(['essay_evaluation_id', 'competency']);
        });

        Schema::create('essay_final_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('essay_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('total');
            $table->json('competency_scores');
            $table->boolean('used_third_evaluator')->default(false);
            $table->unsignedSmallInteger('divergence')->default(0);
            $table->json('consistency_flags');
            $table->json('checklist');
            $table->json('study_recommendation');
            $table->timestamps();
        });

        Schema::create('study_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_topic_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->longText('body');
            $table->string('source_type', 30)->default('EDITORIAL');
            $table->string('review_status', 12)->default('PENDING');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('study_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10); // REGULAR | INTENSIVO
            $table->date('target_date')->nullable();
            $table->unsignedSmallInteger('weekly_hours');
            $table->unsignedSmallInteger('days_left')->nullable();
            $table->json('rationale');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('study_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_topic_id')->nullable()->constrained()->nullOnDelete();
            $table->date('scheduled_on');
            $table->string('kind', 10); // QUESTOES | REVISAO | PROVA | REDACAO | LEITURA
            $table->string('title', 160);
            $table->unsignedSmallInteger('minutes');
            $table->string('status', 8)->default('PENDING'); // PENDING | DONE | SKIPPED
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['study_plan_id', 'scheduled_on']);
        });

        Schema::create('error_notebook_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->boolean('reviewed')->default(false);
            $table->timestamp('next_review_at')->nullable();
            $table->unsignedSmallInteger('interval_days')->default(1);
            $table->decimal('ease', 4, 2)->default(2.5);
            $table->timestamps();
            $table->unique(['user_id', 'question_id']);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'question_id']);
        });

        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20); // QUESTOES_DIA | HORAS_SEMANA | REDACOES_MES | PROVAS_MES | SEQUENCIA
            $table->unsignedSmallInteger('target');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('title', 80);
            $table->string('description', 200);
        });

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->timestamp('earned_at')->useCurrent();
            $table->unique(['user_id', 'achievement_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'user_achievements', 'achievements', 'goals', 'favorites', 'error_notebook_entries', 'study_tasks',
            'study_plans', 'study_materials', 'essay_final_results', 'essay_competency_scores', 'essay_evaluations', 'essays',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
