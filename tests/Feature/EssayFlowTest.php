<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\Essay;
use App\Models\EssayPrompt;
use App\Models\EssayZeroRule;
use App\Models\Exam;
use App\Models\ExamEdition;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Redação: rascunho sem IA, envio, avaliadores A/B (mock), zero por regra da edição. */
class EssayFlowTest extends TestCase
{
    use RefreshDatabase;

    private EssayPrompt $prompt;

    private Plan $plan;

    private function subscriber(): User
    {
        $user = User::factory()->create();
        Subscription::create(['user_id' => $user->id, 'plan_id' => $this->plan->id, 'status' => 'ACTIVE', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync']);
        // Não existe plano gratuito: os testes assinam um plano com 2 correções/mês.
        $this->plan = Plan::create(['code' => 'REDE_PUBLICA', 'name' => 'Rede Pública', 'price_cents' => 2990, 'limits' => ['fullExamsPerMonth' => -1, 'essaysPerMonth' => 2, 'studyPlan' => true, 'tutor' => true, 'errorNotebook' => true], 'benefits' => []]);
        $source = ContentSource::create(['source_type' => 'OFFICIAL_INEP', 'source_url' => 'https://download.inep.gov.br/x.pdf', 'source_year' => 2023, 'document_version' => 'v1', 'checksum' => 'abc', 'review_status' => 'VERIFIED']);
        $edition = ExamEdition::create(['year' => 2023, 'name' => 'ENEM 2023']);
        $exam = Exam::create(['exam_edition_id' => $edition->id, 'application' => 'REGULAR', 'day' => 1, 'title' => 'ENEM 2023', 'duration_minutes' => 330, 'areas' => ['REDACAO'], 'has_essay' => true, 'content_source_id' => $source->id, 'review_status' => 'VERIFIED', 'pipeline_stage' => 'PUBLISHED']);
        $this->prompt = EssayPrompt::create(['exam_id' => $exam->id, 'theme' => 'Tema oficial', 'motivating_texts' => [['title' => 'Texto I', 'body' => 'Texto motivador.']], 'content_source_id' => $source->id, 'review_status' => 'VERIFIED', 'max_lines' => 30]);
        EssayZeroRule::create(['exam_edition_id' => $edition->id, 'code' => 'INSUFICIENTE', 'description' => 'Texto insuficiente', 'source_url' => 'https://download.inep.gov.br/cartilha.pdf', 'review_status' => 'VERIFIED']);
    }

    public function test_essay_is_evaluated_by_two_independent_evaluators(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->post("/redacao/proposta/{$this->prompt->id}")->assertRedirect();
        $essay = Essay::first();
        $text = implode("\n", array_fill(0, 18, 'A sociedade brasileira precisa enfrentar o problema com políticas públicas; cabe ao Estado agir com medidas concretas.'));
        $this->actingAs($user)->putJson("/redacao/{$essay->id}/rascunho", ['text' => $text])->assertOk()->assertJsonPath('lines', 18);
        $this->actingAs($user)->putJson("/redacao/{$essay->id}/rascunho", ['text' => implode("\n", array_fill(0, 31, 'linha'))])->assertStatus(422);

        $this->actingAs($user)->post("/redacao/{$essay->id}/enviar")->assertRedirect("/redacao/{$essay->id}/relatorio");
        $essay->refresh();
        $this->assertSame('EVALUATED', $essay->status);
        $this->assertCount(2, $essay->evaluations);
        $this->assertSame(['A', 'B'], $essay->evaluations->pluck('evaluator')->sort()->values()->all());
        $this->assertNotNull($essay->finalResult);
        $this->assertGreaterThan(0, $essay->finalResult->total);
        $this->actingAs($user)->get("/redacao/{$essay->id}/relatorio")->assertOk()->assertSee('Nota estimada da correção simulada')->assertSee('não constitui uma correção oficial');
        $this->actingAs($user)->putJson("/redacao/{$essay->id}/rascunho", ['text' => 'x'])->assertStatus(403);
    }

    public function test_insufficient_text_gets_zero_from_edition_rule_without_calling_evaluators(): void
    {
        $user = $this->subscriber();
        $essay = Essay::create(['user_id' => $user->id, 'essay_prompt_id' => $this->prompt->id, 'draft_text' => "só\nduas linhas"]);
        $this->actingAs($user)->post("/redacao/{$essay->id}/enviar")->assertRedirect();
        $essay->refresh();
        $this->assertSame('EVALUATED', $essay->status);
        $this->assertSame(0, $essay->finalResult->total);
        $this->assertCount(0, $essay->evaluations);
        $this->assertContains('ZERO:INSUFICIENTE', $essay->finalResult->consistency_flags);
    }

    public function test_plan_limits_essays_per_month_and_no_subscription_means_no_essay(): void
    {
        $nobody = User::factory()->create();
        $e = Essay::create(['user_id' => $nobody->id, 'essay_prompt_id' => $this->prompt->id, 'draft_text' => implode("\n", array_fill(0, 10, 'linha de texto'))]);
        $this->actingAs($nobody)->post("/redacao/{$e->id}/enviar")->assertForbidden();

        $user = $this->subscriber();
        foreach ([1, 2] as $_) {
            $e = Essay::create(['user_id' => $user->id, 'essay_prompt_id' => $this->prompt->id, 'draft_text' => implode("\n", array_fill(0, 10, 'linha de texto'))]);
            $this->actingAs($user)->post("/redacao/{$e->id}/enviar")->assertRedirect();
        }
        $e = Essay::create(['user_id' => $user->id, 'essay_prompt_id' => $this->prompt->id, 'draft_text' => implode("\n", array_fill(0, 10, 'linha de texto'))]);
        $this->actingAs($user)->post("/redacao/{$e->id}/enviar")->assertForbidden();
    }
}
