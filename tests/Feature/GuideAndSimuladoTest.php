<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\StudyTopic;
use App\Models\Subscription;
use App\Models\TopicVideo;
use App\Models\User;
use Database\Seeders\StudyTopicsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuideAndSimuladoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StudyTopicsSeeder::class);
        Plan::create(['code' => 'ESTUDANTE', 'name' => 'Estudante', 'price_cents' => 3990, 'limits' => ['fullExamsPerMonth' => -1, 'essaysPerMonth' => 8, 'intensive' => false], 'benefits' => []]);
        Plan::create(['code' => 'INTENSIVO', 'name' => 'Intensivo', 'price_cents' => 4990, 'limits' => ['fullExamsPerMonth' => -1, 'essaysPerMonth' => 15, 'intensive' => true], 'benefits' => []]);
    }

    private function subscriber(string $plan = 'ESTUDANTE'): User
    {
        $user = User::factory()->create();
        Subscription::create(['user_id' => $user->id, 'plan_id' => Plan::where('code', $plan)->value('id'), 'status' => 'ACTIVE', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);

        return $user;
    }

    public function test_guide_lists_detailed_topics_and_student_can_open_one_mark_it_and_paste_video_links(): void
    {
        $student = $this->subscriber();
        $this->assertGreaterThan(80, StudyTopic::count());

        $this->actingAs($student)->get('/guia')->assertOk()->assertSee('Ecologia')->assertSee('Razão, proporção e porcentagem')->assertSee('0 <span class="text-sm font-normal text-muted">de', false);
        $this->actingAs($student)->get('/guia?area=MATEMATICA')->assertOk()->assertSee('Funções')->assertDontSee('Ecologia');
        $this->actingAs($student)->get('/guia?q=crase')->assertOk()->assertSee('Sintaxe e concordância');

        $topic = StudyTopic::where('slug', 'ecologia')->firstOrFail();
        $this->actingAs($student)->get("/guia/{$topic->slug}")->assertOk()
            ->assertSee('O que estudar neste tema')->assertSee('Ciclos biogeoquímicos')->assertSee('Meus vídeos de estudo')->assertSee('Nenhum vídeo salvo');

        // Cola links: YouTube (incorporado), Vimeo e um link comum.
        $this->actingAs($student)->post("/guia/{$topic->slug}/videos", ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10s', 'title' => 'Aula de ecologia'])->assertRedirect();
        $this->actingAs($student)->post("/guia/{$topic->slug}/videos", ['url' => 'https://youtu.be/abc123XYZ_0'])->assertRedirect();
        $this->actingAs($student)->post("/guia/{$topic->slug}/videos", ['url' => 'https://vimeo.com/123456789'])->assertRedirect();
        $this->actingAs($student)->post("/guia/{$topic->slug}/videos", ['url' => 'https://exemplo.edu.br/aula'])->assertRedirect();
        $this->actingAs($student)->post("/guia/{$topic->slug}/videos", ['url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $this->assertSame(['YOUTUBE', 'YOUTUBE', 'VIMEO', 'LINK'], TopicVideo::orderBy('id')->pluck('provider')->all());
        $this->assertSame('dQw4w9WgXcQ', TopicVideo::orderBy('id')->first()->video_id);
        $page = $this->actingAs($student)->get("/guia/{$topic->slug}")->assertOk();
        $page->assertSee('Aula de ecologia')->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)->assertSee('player.vimeo.com/video/123456789', false)->assertSee('exemplo.edu.br');

        // Os vídeos são pessoais: outro aluno não vê nem apaga.
        $other = $this->subscriber();
        $this->actingAs($other)->get("/guia/{$topic->slug}")->assertOk()->assertDontSee('Aula de ecologia');
        $this->actingAs($other)->delete('/guia/videos/'.TopicVideo::first()->id)->assertForbidden();
        $this->actingAs($student)->delete('/guia/videos/'.TopicVideo::first()->id)->assertRedirect();
        $this->assertSame(3, TopicVideo::count());

        // Equipe recomenda para todos.
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post("/guia/{$topic->slug}/videos", ['url' => 'https://www.youtube.com/watch?v=recomendado1', 'title' => 'Aula recomendada', 'recommended' => 1])->assertRedirect();
        $this->actingAs($other)->get("/guia/{$topic->slug}")->assertOk()->assertSee('Aula recomendada')->assertSee('recomendado');

        // Marcar como estudado atualiza o progresso.
        $this->actingAs($student)->post("/guia/{$topic->slug}/estudado")->assertRedirect();
        $this->actingAs($student)->get('/guia')->assertOk()->assertSee('1 <span class="text-sm font-normal text-muted">de', false)->assertSee('estudado');
        $this->actingAs($student)->post("/guia/{$topic->slug}/estudado")->assertRedirect();
        $this->assertDatabaseCount('topic_progress', 0);
    }

    public function test_simulados_page_shows_formats_and_intensive_mode_is_gated_by_plan(): void
    {
        // Sem assinatura nada abre; assinante do Estudante vê o Modo Intensivo bloqueado.
        $this->actingAs(User::factory()->create())->get('/simulados')->assertRedirect('/assinatura');
        $this->actingAs($this->subscriber())->get('/simulados')->assertOk()->assertSee('Simulado por área')->assertSee('Maratona ENEM')->assertSee('Disponível no Plano Intensivo');

        $intensive = $this->subscriber('INTENSIVO');
        $this->actingAs($intensive)->get('/simulados')->assertOk()->assertSee('Incluído no seu plano')->assertDontSee('Disponível no Plano Intensivo')->assertSee('Rotina semanal de simulados');

        // Sem prova publicada para a edição/área → 404 claro, nada é inventado.
        $this->actingAs($intensive)->post('/simulados/iniciar', ['area' => 'NATUREZA', 'year' => 2024])->assertNotFound();
        $this->actingAs($intensive)->post('/simulados/iniciar', ['area' => 'INVALIDA', 'year' => 2024])->assertSessionHasErrors('area');
    }
}
