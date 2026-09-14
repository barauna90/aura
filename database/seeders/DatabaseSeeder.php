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
            ['code' => 'FREE', 'name' => 'Plano gratuito', 'description' => 'Conheça a plataforma com uma prova oficial completa por mês e uma correção de redação.', 'price_cents' => 0, 'trial_days' => 0, 'sort_order' => 0,
                'limits' => ['fullExamsPerMonth' => 1, 'essaysPerMonth' => 1, 'studyPlan' => false, 'tutor' => false, 'errorNotebook' => true],
                'benefits' => ['1 prova oficial completa por mês', 'Modo Estudo nas provas de amostra', '1 correção simulada de redação por mês', 'Caderno de erros']],
            ['code' => 'ESTUDANTE', 'name' => 'Plano Estudante', 'description' => 'Assinatura mensal acessível com todas as provas oficiais e plano de estudos.', 'price_cents' => 2990, 'trial_days' => 7, 'sort_order' => 1,
                'limits' => ['fullExamsPerMonth' => -1, 'essaysPerMonth' => 4, 'studyPlan' => true, 'tutor' => true, 'errorNotebook' => true],
                'benefits' => ['Acesso completo a todas as provas oficiais', 'Simulados ilimitados no Modo Prova Real', '4 correções simuladas de redação por mês', 'Plano de estudos personalizado', 'Professor IA', 'Indique e ganhe comissões']],
            ['code' => 'INTENSIVO', 'name' => 'Plano Intensivo', 'description' => 'Para quem está na reta final: mais correções de redação e rotina intensiva.', 'price_cents' => 4990, 'trial_days' => 7, 'sort_order' => 2,
                'limits' => ['fullExamsPerMonth' => -1, 'essaysPerMonth' => 12, 'studyPlan' => true, 'tutor' => true, 'errorNotebook' => true],
                'benefits' => ['Tudo do Estudante', '12 correções simuladas de redação por mês', 'Modo Intensivo ENEM', 'Prioridade na fila de correção']],
        ] as $p) {
            Plan::updateOrCreate(['code' => $p['code']], $p + ['interval_months' => 1, 'is_active' => true]);
        }

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
    }
}
