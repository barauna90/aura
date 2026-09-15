<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamBooklet;
use App\Models\Plan;
use App\Models\User;
use App\Services\Content\ContentImportService;
use App\Services\Content\GuardianService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExamFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('official');
        Plan::create(['code' => 'FREE', 'name' => 'Grátis', 'price_cents' => 0, 'limits' => ['fullExamsPerMonth' => 1, 'essaysPerMonth' => 1], 'benefits' => []]);
        $this->admin = User::factory()->admin()->create();
        $this->reviewer = User::factory()->reviewer()->create();
    }

    /** Importa uma prova mínima (2 questões + 1 anulada + inglês/espanhol) e a publica pelo fluxo completo. */
    private function publishExam(): Exam
    {
        $importer = app(ContentImportService::class);
        $guardian = app(GuardianService::class);
        $exam = $importer->createExam([
            'year' => 2023, 'application' => 'REGULAR', 'day' => 1, 'title' => 'ENEM 2023 — 1º dia', 'duration_minutes' => 330,
            'areas' => ['LINGUAGENS', 'HUMANAS'], 'source_url' => 'https://download.inep.gov.br/x.pdf', 'document_version' => 'v1', 'is_free_sample' => true,
        ], null, $this->admin->id);
        $pdf = UploadedFile::fake()->createWithContent('caderno.pdf', "%PDF-1.4\n%fake\n");
        $booklet = $importer->addBooklet($exam, ['color' => 'AZUL', 'label' => 'Azul', 'page_count' => 4, 'source_url' => 'https://download.inep.gov.br/x.pdf', 'document_version' => 'v1'], $pdf, $this->admin->id);
        $importer->registerAnswerKey($booklet, ['source_url' => 'https://download.inep.gov.br/g.pdf', 'document_version' => 'v2'], [
            ['number' => 1, 'area' => 'LINGUAGENS', 'correct' => 'A', 'foreign_language' => 'INGLES'],
            ['number' => 2, 'area' => 'LINGUAGENS', 'correct' => 'B', 'foreign_language' => 'ESPANHOL'],
            ['number' => 6, 'area' => 'LINGUAGENS', 'correct' => 'C'],
            ['number' => 46, 'area' => 'HUMANAS', 'correct' => 'D'],
            ['number' => 47, 'area' => 'HUMANAS', 'annulled' => true],
        ], null, $this->admin->id);

        $guardian->advanceExam($exam->fresh(), 'AUTO_VALIDATED', $this->admin->id);
        $guardian->advanceExam($exam->fresh(), 'HUMAN_REVIEW_1', $this->reviewer->id);
        try {
            $guardian->advanceExam($exam->fresh(), 'HUMAN_REVIEW_2', $this->reviewer->id);
            $this->fail('A segunda revisão deveria exigir outro revisor');
        } catch (ValidationException) {
        }
        $guardian->advanceExam($exam->fresh(), 'HUMAN_REVIEW_2', $this->admin->id);
        $guardian->advanceExam($exam->fresh(), 'PUBLISHED', $this->admin->id);

        return $exam->fresh();
    }

    public function test_only_published_official_exams_are_visible_and_full_flow_grades_by_answer_sheet(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student)->get('/provas')->assertOk()->assertSee('Nenhuma prova oficial');

        $exam = $this->publishExam();
        $this->assertSame('VERIFIED', $exam->review_status);
        $this->assertSame('PUBLISHED', $exam->pipeline_stage);
        $this->actingAs($student)->get('/provas')->assertOk()->assertSee('ENEM 2023 — 1º dia');

        $booklet = ExamBooklet::first();
        $this->actingAs($student)->post("/provas/{$exam->id}/iniciar", ['booklet_id' => $booklet->id, 'mode' => 'PROVA_REAL'])
            ->assertSessionHasErrors('language'); // idioma obrigatório

        $r = $this->actingAs($student)->post("/provas/{$exam->id}/iniciar", ['booklet_id' => $booklet->id, 'mode' => 'PROVA_REAL', 'language' => 'INGLES']);
        $session = $student->examSessions()->first();
        $r->assertRedirect("/sessao/{$session->id}");
        $this->assertSame(4, $session->answerSheet->answers()->count()); // 1(EN), 6, 46, 47 — sem a questão de espanhol

        $this->actingAs($student)->get("/provas/{$exam->id}")->assertOk()->assertSee('Modo Prova Real')->assertSee('Proveniência');
        $this->actingAs($student)->get("/sessao/{$session->id}")->assertOk()->assertSee('Iniciar agora');
        $this->actingAs($student)->post("/sessao/{$session->id}/iniciar")->assertRedirect();
        $this->actingAs($student)->get("/sessao/{$session->id}")->assertOk()->assertSee('Cartão-resposta')->assertSee('id="pdf-frame"', false);
        $this->actingAs($student)->get("/cadernos/{$booklet->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs(User::factory()->create())->get("/sessao/{$session->id}")->assertNotFound();
        $this->actingAs($student)->postJson("/sessao/{$session->id}/pausar")->assertStatus(422); // Prova Real não pausa

        $ids = $session->answerSheet->answers()->pluck('question_id', 'question_number');
        $this->actingAs($student)->postJson("/sessao/{$session->id}/respostas", ['answers' => [
            ['question_id' => $ids[1], 'option' => 'A'], ['question_id' => $ids[6], 'option' => 'E'], ['question_id' => $ids[47], 'option' => 'B'],
        ]])->assertOk()->assertJsonPath('saved', 3);

        $state = $this->actingAs($student)->getJson("/sessao/{$session->id}/estado")->assertOk()->json();
        $this->assertSame(3, $state['answer_sheet']['answered']);
        $this->assertArrayNotHasKey('correct', $state); // nada de acerto/erro antes do fim

        $this->actingAs($student)->postJson("/sessao/{$session->id}/encerrar")->assertOk();
        $session->refresh();
        $this->assertSame('FINISHED', $session->status);
        $this->assertSame(3, $session->result->total_questions); // 47 anulada não conta
        $this->assertSame(1, $session->result->correct);
        $this->assertSame(1, $session->result->wrong);
        $this->assertSame(1, $session->result->blank);
        $this->assertSame(1, $student->errorNotebook()->count());

        $this->actingAs($student)->postJson("/sessao/{$session->id}/respostas", ['answers' => [['question_id' => $ids[46], 'option' => 'D']]])->assertStatus(422);
        $this->actingAs($student)->get("/sessao/{$session->id}/resultado")->assertOk()->assertSee('Acertos oficiais pelo gabarito')->assertSee('Resolução detalhada ainda não disponível');
    }

    public function test_booklet_marks_are_saved_but_only_the_answer_sheet_is_graded(): void
    {
        $exam = $this->publishExam();
        $student = User::factory()->create();
        $booklet = ExamBooklet::first();
        $this->actingAs($student)->post("/provas/{$exam->id}/iniciar", ['booklet_id' => $booklet->id, 'mode' => 'PROVA_REAL', 'language' => 'INGLES'])->assertRedirect();
        $session = $student->examSessions()->first();
        $this->actingAs($student)->post("/sessao/{$session->id}/iniciar")->assertRedirect();
        $this->actingAs($student)->get("/sessao/{$session->id}")->assertOk()->assertSee('id="booklet-marks"', false)->assertSee('Transferir para o cartão');

        $ids = $session->answerSheet->answers()->pluck('question_id', 'question_number');
        // Marca no caderno (rascunho) as questões 1 e 6; transfere só a 1 para o cartão.
        $this->actingAs($student)->postJson("/sessao/{$session->id}/respostas", ['answers' => [
            ['question_id' => $ids[1], 'draft_option' => 'A'],
            ['question_id' => $ids[6], 'draft_option' => 'C'],
        ]])->assertOk()->assertJsonPath('saved', 2);
        $this->actingAs($student)->postJson("/sessao/{$session->id}/respostas", ['answers' => [['question_id' => $ids[1], 'option' => 'A']]])->assertOk();
        $this->actingAs($student)->postJson("/sessao/{$session->id}/respostas", ['answers' => [['question_id' => $ids[6], 'draft_option' => 'X']]])->assertStatus(422);

        $state = $this->actingAs($student)->getJson("/sessao/{$session->id}/estado")->assertOk()->json('answer_sheet');
        $this->assertSame(1, $state['answered']);
        $this->assertSame(2, $state['drafted']);
        $this->assertSame(1, $state['untransferred']);
        $this->assertDatabaseHas('answers', ['question_id' => $ids[6], 'draft_option' => 'C', 'option' => null]);

        $this->actingAs($student)->postJson("/sessao/{$session->id}/encerrar")->assertOk();
        $session->refresh();
        // A questão 6 estava certa no caderno, mas em branco no cartão: não conta como acerto.
        $this->assertSame(1, $session->result->correct);
        $this->assertSame(2, $session->result->blank);
        $this->actingAs($student)->get("/sessao/{$session->id}/resultado")->assertOk()->assertSee('<th>Caderno</th>', false);
    }

    public function test_changing_an_official_answer_requires_reason_and_unpublishes_the_exam(): void
    {
        $exam = $this->publishExam();
        $question = $exam->booklets->first()->questions()->where('original_number', 6)->first();
        $this->actingAs($this->admin)->put("/admin/conteudo/questoes/{$question->id}/gabarito", ['correct' => 'D', 'annulled' => 0])->assertSessionHasErrors('reason');
        $this->actingAs($this->admin)->put("/admin/conteudo/questoes/{$question->id}/gabarito", ['correct' => 'D', 'annulled' => 0, 'reason' => 'Errata oficial publicada pelo Inep'])->assertRedirect();
        $exam->refresh();
        $this->assertSame('PENDING', $exam->review_status);
        $this->assertSame('IMPORTED', $exam->pipeline_stage);
        $this->assertDatabaseHas('content_versions', ['entity_type' => 'OfficialAnswer', 'reason' => 'Errata oficial publicada pelo Inep']);
        $this->actingAs(User::factory()->create())->get('/provas')->assertSee('Nenhuma prova oficial');
        // Republicar exige que o gabarito volte a bater com um conjunto importado (integridade).
        $problems = app(GuardianService::class)->verifyIntegrity($exam);
        $this->assertContains('ANSWER_KEY_MISMATCH', array_column($problems, 'code'));
    }

    public function test_free_plan_limits_full_exams_per_month(): void
    {
        $exam = $this->publishExam();
        $exam->update(['is_free_sample' => false]);
        $student = User::factory()->create();
        $booklet = ExamBooklet::first();
        $this->actingAs($student)->post("/provas/{$exam->id}/iniciar", ['booklet_id' => $booklet->id, 'mode' => 'PROVA_REAL', 'language' => 'INGLES'])->assertRedirect();
        $this->actingAs($student)->post("/provas/{$exam->id}/iniciar", ['booklet_id' => $booklet->id, 'mode' => 'PROVA_REAL', 'language' => 'INGLES'])->assertForbidden();
        $this->actingAs($student)->post("/provas/{$exam->id}/iniciar", ['booklet_id' => $booklet->id, 'mode' => 'ESTUDO', 'language' => 'INGLES', 'areas' => ['HUMANAS']])->assertRedirect();
    }
}
