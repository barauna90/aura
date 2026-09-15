<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conteúdo oficial — proveniência obrigatória (fonte, versão, checksum, status).
 * Só conteúdo VERIFIED + PUBLISHED de fonte OFFICIAL_INEP aparece como "prova oficial".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_sources', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 30); // OFFICIAL_INEP | EDITORIAL | AI_GENERATED_EDUCATIONAL
            $table->string('source_url', 500);
            $table->unsignedSmallInteger('source_year');
            $table->string('document_version', 60);
            $table->timestamp('import_date')->useCurrent();
            $table->timestamp('last_validation')->nullable();
            $table->string('checksum', 64);
            $table->string('review_status', 12)->default('PENDING'); // PENDING | VALIDATING | VERIFIED | REJECTED
            $table->string('description', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('exam_editions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('name', 40);
            $table->string('rules_version', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_edition_id')->constrained()->cascadeOnDelete();
            $table->string('application', 15); // REGULAR | REAPLICACAO | PPL | DIGITAL
            $table->unsignedTinyInteger('day');
            $table->string('title', 160);
            $table->unsignedSmallInteger('duration_minutes'); // duração oficial daquela edição/dia
            $table->boolean('has_essay')->default(false);
            $table->boolean('has_foreign_language')->default(false);
            $table->json('areas');
            $table->string('structure_note', 500)->nullable();
            $table->foreignId('content_source_id')->constrained();
            $table->string('review_status', 12)->default('PENDING');
            $table->string('pipeline_stage', 16)->default('IMPORTED'); // IMPORTED | AUTO_VALIDATED | HUMAN_REVIEW_1 | HUMAN_REVIEW_2 | PUBLISHED
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_free_sample')->default(false);
            $table->timestamps();
            $table->unique(['exam_edition_id', 'application', 'day']);
            $table->index(['review_status', 'pipeline_stage']);
        });

        Schema::create('exam_booklets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->string('color', 15)->default('NAO_APLICAVEL');
            $table->string('label', 80);
            $table->string('pdf_path', 255);
            $table->string('pdf_checksum', 64);
            $table->unsignedSmallInteger('page_count');
            $table->foreignId('content_source_id')->constrained();
            $table->string('review_status', 12)->default('PENDING');
            $table->timestamps();
            $table->unique(['exam_id', 'color']);
        });

        Schema::create('exam_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_booklet_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('page_number');
            $table->string('image_path', 255)->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unique(['exam_booklet_id', 'page_number']);
        });

        Schema::create('study_topics', function (Blueprint $table) {
            $table->id();
            $table->string('area', 12);
            $table->string('discipline', 60);
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('recurrence')->default(0);
            $table->string('matrix_skill', 20)->nullable();
            $table->string('source_type', 30)->default('EDITORIAL');
            $table->string('review_status', 12)->default('PENDING');
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_booklet_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('original_number');
            $table->string('area', 12);
            $table->string('foreign_language', 10)->nullable(); // INGLES | ESPANHOL
            $table->unsignedSmallInteger('page_number')->nullable();
            $table->text('statement_text')->nullable();
            $table->string('source_type', 30)->default('OFFICIAL_INEP');
            $table->string('review_status', 12)->default('PENDING');
            $table->string('pipeline_stage', 16)->default('IMPORTED');
            $table->unsignedInteger('version')->default(1);
            $table->string('checksum', 64)->nullable();
            $table->timestamps();
            // Questões 1–5 existem em duas versões (inglês/espanhol) com o mesmo número.
            $table->unique(['exam_booklet_id', 'original_number', 'foreign_language']);
            $table->index('area');
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->char('letter', 1);
            $table->text('text')->nullable();
            $table->unique(['question_id', 'letter']);
        });

        Schema::create('official_answer_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_booklet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_source_id')->constrained();
            $table->string('pdf_path', 255)->nullable();
            $table->string('checksum', 64);
            $table->string('review_status', 12)->default('PENDING');
            $table->timestamps();
        });

        Schema::create('official_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('official_answer_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('correct', 1)->nullable(); // nulo quando anulada
            $table->boolean('annulled')->default(false);
            $table->string('review_status', 12)->default('PENDING');
            $table->timestamps();
        });

        Schema::create('question_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('discipline', 60)->nullable();
            $table->foreignId('study_topic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('competency', 20)->nullable();
            $table->string('skill', 20)->nullable();
            $table->string('review_status', 12)->default('PENDING');
            $table->timestamps();
        });

        Schema::create('question_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('body');
            $table->string('source_type', 30)->default('EDITORIAL');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_status', 12)->default('PENDING');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('essay_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('theme', 300);
            $table->json('motivating_texts');
            $table->string('pdf_path', 255)->nullable();
            $table->unsignedTinyInteger('max_lines')->default(30);
            $table->foreignId('content_source_id')->constrained();
            $table->string('review_status', 12)->default('PENDING');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        // Regras de nota zero versionadas por edição (nunca copiadas de outro ano sem fonte).
        Schema::create('essay_zero_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_edition_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('description', 400);
            $table->string('source_url', 500);
            $table->string('review_status', 12)->default('PENDING');
            $table->timestamps();
            $table->unique(['exam_edition_id', 'code']);
        });

        Schema::create('official_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_source_id')->constrained();
            $table->string('title', 200);
            $table->unsignedSmallInteger('year');
            $table->string('kind', 40); // EDITAL | MATRIZ_REFERENCIA | CARTILHA_REDACAO ...
            $table->string('storage_path', 255);
            $table->unsignedInteger('version')->default(1);
            $table->string('review_status', 12)->default('PENDING');
            $table->timestamps();
        });

        Schema::create('official_document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('official_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('ordinal');
            $table->text('content');
            $table->unsignedSmallInteger('page')->nullable();
            $table->index(['official_document_id', 'ordinal']);
        });

        Schema::create('content_versions', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 60);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedInteger('version');
            $table->json('previous_data')->nullable();
            $table->json('new_data');
            $table->string('reason', 500);
            $table->foreignId('author_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'content_versions', 'official_document_chunks', 'official_documents', 'essay_zero_rules', 'essay_prompts',
            'question_resolutions', 'question_classifications', 'official_answers', 'official_answer_sets',
            'question_options', 'questions', 'study_topics', 'exam_pages', 'exam_booklets', 'exams', 'exam_editions', 'content_sources',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
