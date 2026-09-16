<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\ReferralSetting;
use App\Models\StudyTopic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seed de desenvolvimento. NÃO cria provas, questões nem gabaritos: conteúdo
 * oficial só entra pelo painel de importação a partir dos PDFs do Inep.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'REDE_PUBLICA', 'name' => 'Rede Pública', 'description' => 'Para estudantes da rede pública: acesso completo a um preço que cabe no bolso.', 'price_cents' => 2990, 'sort_order' => 1,
                'limits' => ['fullExamsPerMonth' => -1, 'essaysPerMonth' => 4, 'studyPlan' => true, 'tutor' => true, 'errorNotebook' => true, 'intensive' => false, 'priorityEssay' => false],
                'benefits' => ['Acesso a todas as provas oficiais', 'Simulados ilimitados no Modo Prova Real', '4 correções simuladas de redação por mês', 'Plano de estudos personalizado', 'Indique e ganhe']],
            ['code' => 'ESTUDANTE', 'name' => 'Plano Estudante', 'description' => 'O plano mais escolhido: mais correções de redação e todos os recursos.', 'price_cents' => 3990, 'sort_order' => 2, 'badge' => 'PROMOÇÃO', 'is_featured' => true,
                'limits' => ['fullExamsPerMonth' => -1, 'essaysPerMonth' => 8, 'studyPlan' => true, 'tutor' => true, 'errorNotebook' => true, 'intensive' => false, 'priorityEssay' => false],
                'benefits' => ['Acesso a todas as provas oficiais', 'Simulados ilimitados no Modo Prova Real', '8 correções simuladas de redação por mês', 'Plano de estudos personalizado', 'Indique e ganhe']],
            ['code' => 'INTENSIVO', 'name' => 'Plano Intensivo', 'description' => 'Para quem está na reta final: rotina intensiva e prioridade na correção.', 'price_cents' => 4990, 'sort_order' => 3,
                'limits' => ['fullExamsPerMonth' => -1, 'essaysPerMonth' => 15, 'studyPlan' => true, 'tutor' => true, 'errorNotebook' => true, 'intensive' => true, 'priorityEssay' => true],
                'benefits' => ['Acesso a todas as provas oficiais', 'Simulados ilimitados no Modo Prova Real', '15 correções simuladas de redação por mês', 'Modo Intensivo ENEM', 'Prioridade na correção', 'Indique e ganhe']],
        ] as $p) {
            Plan::updateOrCreate(['code' => $p['code']], $p + ['interval_months' => 1, 'trial_days' => 0, 'is_active' => true, 'badge' => null, 'is_featured' => false]);
        }
        Plan::where('code', 'FREE')->delete();

        ReferralSetting::current();

        User::updateOrCreate(['email' => env('SEED_ADMIN_EMAIL', 'admin@aura.local')], [
            'name' => 'Administrador', 'password' => env('SEED_ADMIN_PASSWORD', 'Admin123!Troque'), 'role' => 'SUPER_ADMIN',
            'referral_code' => 'ADMIN000', 'onboarding_done' => true,
        ]);
        User::updateOrCreate(['email' => 'revisor@aura.local'], [
            'name' => 'Revisor de Conteúdo', 'password' => 'Revisor123!Troque', 'role' => 'REVIEWER', 'referral_code' => 'REVIS000', 'onboarding_done' => true,
        ]);

        foreach ([
            ['LINGUAGENS', 'Português', 'Interpretação de texto'], ['LINGUAGENS', 'Português', 'Funções da linguagem'],
            ['LINGUAGENS', 'Literatura', 'Movimentos literários brasileiros'], ['LINGUAGENS', 'Inglês/Espanhol', 'Leitura em língua estrangeira'],
            ['HUMANAS', 'História', 'Brasil República'], ['HUMANAS', 'Geografia', 'Urbanização e questões ambientais'],
            ['HUMANAS', 'Filosofia', 'Filosofia moderna e contemporânea'], ['HUMANAS', 'Sociologia', 'Cidadania e movimentos sociais'],
            ['NATUREZA', 'Biologia', 'Ecologia'], ['NATUREZA', 'Biologia', 'Genética'], ['NATUREZA', 'Física', 'Eletricidade'],
            ['NATUREZA', 'Física', 'Mecânica'], ['NATUREZA', 'Química', 'Estequiometria'], ['NATUREZA', 'Química', 'Química orgânica'],
            ['MATEMATICA', 'Matemática', 'Razão, proporção e porcentagem'], ['MATEMATICA', 'Matemática', 'Funções'],
            ['MATEMATICA', 'Matemática', 'Geometria plana e espacial'], ['MATEMATICA', 'Matemática', 'Estatística e probabilidade'],
            ['REDACAO', 'Redação', 'Estrutura dissertativo-argumentativa'], ['REDACAO', 'Redação', 'Proposta de intervenção'],
        ] as [$area, $discipline, $name]) {
            StudyTopic::updateOrCreate(['slug' => Str::slug($name)], ['area' => $area, 'discipline' => $discipline, 'name' => $name, 'source_type' => 'EDITORIAL', 'review_status' => 'VERIFIED']);
        }

        // Usuários de teste (um por perfil e por plano) — só fora de produção.
        if (! app()->isProduction()) {
            $this->call(DemoUsersSeeder::class);
        }
    }
}
