<?php

namespace Tests\Unit;

use App\Services\Content\GuardianRules;
use App\Services\Essay\ScoringRules;
use App\Services\Essay\ZeroScoreRules;
use App\Services\Exam\GradingRules;
use App\Services\Exam\TimerRules;
use App\Services\Promotion\CouponRules;
use App\Services\Referral\CommissionRules;
use App\Services\Study\ErrorNotebookService;
use App\Services\Study\StudyPlanRules;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/** Regras puras (sem banco): cronômetro, correção, guardião, redação, comissões, cupons, plano. */
class RulesTest extends TestCase
{
    // ---------------- Cronômetro ----------------

    private function timer(array $over = []): array
    {
        return $over + [
            'mode' => 'PROVA_REAL', 'status' => 'IN_PROGRESS',
            'started_at' => CarbonImmutable::parse('2026-09-14 13:30:00'), 'expected_end_at' => CarbonImmutable::parse('2026-09-14 19:00:00'),
            'paused_at' => null, 'paused_seconds' => 0,
        ];
    }

    public function test_timer_uses_edition_duration_and_never_goes_negative(): void
    {
        $this->assertSame('2026-09-14 19:00:00', TimerRules::expectedEnd(CarbonImmutable::parse('2026-09-14 13:30:00'), 330)->format('Y-m-d H:i:s'));
        $this->assertSame(30, TimerRules::remainingSeconds($this->timer(), CarbonImmutable::parse('2026-09-14 18:59:30')));
        $this->assertSame(0, TimerRules::remainingSeconds($this->timer(), CarbonImmutable::parse('2026-09-14 19:05:00')));
        $this->assertTrue(TimerRules::isExpired($this->timer(), CarbonImmutable::parse('2026-09-14 19:00:00')));
        $this->assertFalse(TimerRules::isExpired($this->timer(), CarbonImmutable::parse('2026-09-14 18:00:00')));
    }

    public function test_real_mode_cannot_pause_and_study_mode_preserves_remaining_on_resume(): void
    {
        $this->assertFalse(TimerRules::canPause($this->timer())['ok']);
        $this->assertTrue(TimerRules::canPause($this->timer(['mode' => 'ESTUDO']))['ok']);
        $paused = $this->timer(['mode' => 'ESTUDO', 'status' => 'PAUSED', 'paused_at' => CarbonImmutable::parse('2026-09-14 15:00:00')]);
        $this->assertSame(4 * 3600, TimerRules::remainingSeconds($paused, CarbonImmutable::parse('2026-09-14 16:00:00')));
        $r = TimerRules::resume($paused, CarbonImmutable::parse('2026-09-14 16:00:00'));
        $this->assertSame(3600, $r['paused_seconds']);
        $this->assertSame('2026-09-14 20:00:00', $r['expected_end_at']->format('Y-m-d H:i:s'));
        $this->assertSame(3000, TimerRules::timeUsedSeconds($this->timer(['paused_seconds' => 600]), CarbonImmutable::parse('2026-09-14 14:30:00')));
        $this->assertSame('05:30:00', TimerRules::formatHms(19800));
    }

    // ---------------- Correção objetiva ----------------

    private function questions(): array
    {
        $q = fn (int $id, int $n, string $area, ?string $correct, ?string $lang = null, bool $annulled = false, ?string $disc = null) => [
            'question_id' => $id, 'original_number' => $n, 'area' => $area, 'foreign_language' => $lang,
            'official' => ['correct' => $correct, 'annulled' => $annulled], 'discipline' => $disc,
        ];

        return [
            $q(1, 1, 'LINGUAGENS', 'A', 'INGLES'), $q(2, 2, 'LINGUAGENS', 'B', 'INGLES'),
            $q(3, 1, 'LINGUAGENS', 'C', 'ESPANHOL'), $q(4, 2, 'LINGUAGENS', 'D', 'ESPANHOL'),
            $q(5, 6, 'LINGUAGENS', 'E'), $q(6, 46, 'HUMANAS', 'A'), $q(7, 47, 'HUMANAS', null, null, true),
            $q(8, 91, 'NATUREZA', 'C', null, false, 'Física'), $q(9, 136, 'MATEMATICA', 'D'),
        ];
    }

    public function test_grading_filters_language_counts_blank_and_ignores_annulled(): void
    {
        $en = GradingRules::applicable($this->questions(), 'INGLES');
        $this->assertSame([1, 2, 5, 6, 7, 8, 9], array_column($en, 'question_id'));

        $r = GradingRules::grade($this->questions(), [['question_id' => 1, 'option' => 'A', 'change_count' => 1]], 'INGLES');
        $this->assertSame(1, $r['correct']);
        $this->assertSame(5, $r['blank']);
        $this->assertSame(0, $r['wrong']);
        $this->assertSame(1, $r['annulled']);
        $this->assertSame(6, $r['total_questions']);

        $r = GradingRules::grade($this->questions(), [
            ['question_id' => 1, 'option' => 'A', 'change_count' => 1], ['question_id' => 2, 'option' => 'C', 'change_count' => 3],
            ['question_id' => 8, 'option' => 'C', 'change_count' => 1], ['question_id' => 9, 'option' => 'A', 'change_count' => 1],
        ], 'INGLES');
        $ling = collect($r['by_area'])->firstWhere('area', 'LINGUAGENS');
        $this->assertSame(['total' => 3, 'correct' => 1, 'wrong' => 1, 'blank' => 1], array_intersect_key($ling, array_flip(['total', 'correct', 'wrong', 'blank'])));
        $this->assertSame([['discipline' => 'Física', 'total' => 1, 'correct' => 1, 'percent' => 100.0]], $r['by_discipline']);
        $this->assertSame(1, $r['changed_answers']);
        $this->assertArrayNotHasKey('score', $r); // nunca "nota ENEM"
        $this->assertSame(1, GradingRules::grade($this->questions(), [], 'INGLES', ['MATEMATICA'])['total_questions']);
    }

    // ---------------- Guardião ----------------

    private function validExam(): array
    {
        return [
            'duration_minutes' => 330,
            'source' => ['source_type' => 'OFFICIAL_INEP', 'source_url' => 'https://download.inep.gov.br/x.pdf', 'checksum' => GuardianRules::sha256('pdf')],
            'booklets' => [[
                'id' => 1, 'label' => 'Azul', 'pdf_checksum' => GuardianRules::sha256('pdf'), 'page_count' => 32,
                'answer_sets' => [['checksum' => 'x']],
                'questions' => [
                    ['original_number' => 1, 'source_type' => 'OFFICIAL_INEP', 'official_answer' => ['correct' => 'A', 'annulled' => false]],
                    ['original_number' => 2, 'source_type' => 'OFFICIAL_INEP', 'official_answer' => ['correct' => null, 'annulled' => true]],
                ],
            ]],
        ];
    }

    public function test_guardian_blocks_non_official_content_and_detects_answer_key_changes(): void
    {
        $this->assertSame([], GuardianRules::validateExamForPublication($this->validExam()));
        $e = $this->validExam();
        $e['source']['source_type'] = 'AI_GENERATED_EDUCATIONAL';
        $this->assertContains('SOURCE_NOT_OFFICIAL', array_column(GuardianRules::validateExamForPublication($e), 'code'));
        $e = $this->validExam();
        $e['booklets'][0]['questions'][0]['source_type'] = 'AI_GENERATED_EDUCATIONAL';
        $this->assertContains('QUESTION_NOT_OFFICIAL', array_column(GuardianRules::validateExamForPublication($e), 'code'));
        $e = $this->validExam();
        $e['booklets'][0]['questions'][0]['official_answer'] = null;
        $this->assertContains('ANSWER_MISSING', array_column(GuardianRules::validateExamForPublication($e), 'code'));
        $e = $this->validExam();
        $e['duration_minutes'] = 0;
        $this->assertContains('DURATION_INVALID', array_column(GuardianRules::validateExamForPublication($e), 'code'));

        $original = GuardianRules::answerKeyChecksum([['number' => 1, 'correct' => 'A'], ['number' => 2, 'correct' => 'B']]);
        $this->assertNotSame($original, GuardianRules::answerKeyChecksum([['number' => 1, 'correct' => 'A'], ['number' => 2, 'correct' => 'C']]));
        $this->assertSame($original, GuardianRules::answerKeyChecksum([['number' => 2, 'correct' => 'B'], ['number' => 1, 'correct' => 'A']]));

        $this->assertSame('AUTO_VALIDATED', GuardianRules::nextStage('IMPORTED'));
        $this->assertNull(GuardianRules::nextStage('PUBLISHED'));
        $this->assertSame('INVALID_TRANSITION', GuardianRules::canAdvance('IMPORTED', 'PUBLISHED', null, 1)['code']);
        $this->assertSame('SAME_REVIEWER', GuardianRules::canAdvance('HUMAN_REVIEW_1', 'HUMAN_REVIEW_2', 1, 1)['code']);
        $this->assertNull(GuardianRules::canAdvance('HUMAN_REVIEW_1', 'HUMAN_REVIEW_2', 1, 2));
        $this->assertTrue(GuardianRules::isPubliclyOfficial('VERIFIED', 'OFFICIAL_INEP', 'PUBLISHED'));
        $this->assertFalse(GuardianRules::isPubliclyOfficial('VERIFIED', 'EDITORIAL'));
        $this->assertFalse(GuardianRules::isPubliclyOfficial('VERIFIED', 'OFFICIAL_INEP', 'HUMAN_REVIEW_2'));
    }

    // ---------------- Redação ----------------

    private function ev(string $e, array $scores, bool $zero = false): array
    {
        return ['evaluator' => $e, 'zero_score' => $zero, 'zero_reason' => $zero ? 'FUGA_TEMA' : null, 'positives' => [], 'improvements' => [],
            'competencies' => array_map(fn ($i, $s) => ['competency' => $i + 1, 'score' => $s, 'justification' => 'ok', 'problematic_excerpts' => []], array_keys($scores), $scores)];
    }

    public function test_zero_score_rules_are_versioned_per_edition(): void
    {
        $rules = [['code' => 'EM_BRANCO', 'description' => 'x'], ['code' => 'INSUFICIENTE', 'description' => 'x'], ['code' => 'IDENTIFICACAO', 'description' => 'x'], ['code' => 'FUGA_TEMA', 'description' => 'x']];
        $long = implode("\n", array_fill(0, 20, 'Linha de um texto dissertativo que discute o tema proposto com argumentos.'));
        $this->assertSame('EM_BRANCO', ZeroScoreRules::check('   ', $rules)['code']);
        $this->assertSame('INSUFICIENTE', ZeroScoreRules::check("uma\nduas", $rules, 8)['code']);
        $this->assertFalse(ZeroScoreRules::check("uma\nduas", $rules, 2)['zero']);
        $this->assertSame('IDENTIFICACAO', ZeroScoreRules::check($long."\nAssinado: João", $rules)['code']);
        $r = ZeroScoreRules::check('   ', [['code' => 'FUGA_TEMA', 'description' => 'x']]);
        $this->assertFalse($r['zero']);
        $this->assertSame(['FUGA_TEMA'], $r['deferred']);
        $this->assertFalse(ZeroScoreRules::check($long, $rules)['zero']);
    }

    public function test_essay_aggregation_third_evaluator_and_consistency(): void
    {
        $this->assertSame(160, ScoringRules::normalizeScore(150));
        $this->assertSame(200, ScoringRules::normalizeScore(999));
        $r = ScoringRules::aggregate([$this->ev('A', [160, 160, 120, 160, 120]), $this->ev('B', [160, 120, 120, 160, 160])]);
        $this->assertSame(720, $r['total']);
        $this->assertSame(140, $r['competency_scores'][2]);
        $this->assertTrue(ScoringRules::needsThirdEvaluator($this->ev('A', [200, 200, 200, 200, 200]), $this->ev('B', [120, 120, 120, 120, 120]), 100, 80));
        $this->assertFalse(ScoringRules::needsThirdEvaluator($this->ev('A', [160, 160, 160, 160, 160]), $this->ev('B', [160, 120, 160, 160, 160]), 100, 80));
        $this->assertTrue(ScoringRules::needsThirdEvaluator($this->ev('A', [160, 160, 160, 160, 160]), $this->ev('B', [0, 0, 0, 0, 0], true), 100, 80));
        $r = ScoringRules::aggregate([$this->ev('A', [200, 200, 200, 200, 200]), $this->ev('B', [120, 120, 120, 120, 120]), $this->ev('C', [120, 160, 120, 120, 120])]);
        $this->assertSame(120, $r['competency_scores'][1]);
        $this->assertSame(140, $r['competency_scores'][2]);
        $this->assertFalse(ScoringRules::aggregate([$this->ev('A', [0, 0, 0, 0, 0], true), $this->ev('B', [160, 160, 160, 160, 160]), $this->ev('C', [160, 160, 160, 160, 160])])['zero']);
        $this->assertTrue(ScoringRules::aggregate([$this->ev('A', [0, 0, 0, 0, 0], true), $this->ev('B', [0, 0, 0, 0, 0], true), $this->ev('C', [160, 160, 160, 160, 160])])['zero']);

        $a = $this->ev('A', [200, 0, 160, 200, 200]);
        $a['competencies'][0]['problematic_excerpts'] = ['erro literal'];
        $flags = ScoringRules::auditConsistency([$a, $this->ev('B', [40, 40, 40, 40, 40])], 150);
        $this->assertContains('A:C1:NOTA_MAXIMA_COM_PROBLEMAS', $flags);
        $this->assertContains('A:C2_ZERO_C3_ALTA', $flags);
        $this->assertContains('DIVERGENCIA_EXTREMA_AB', $flags);
    }

    // ---------------- Comissões, cupons, plano ----------------

    public function test_commissions_and_fraud(): void
    {
        // Meta de indicações: bônus a cada N indicações validadas; só o 1º pagamento de cada indicado conta.
        $this->assertSame(0, CommissionRules::milestonesReady(3, 4));
        $this->assertSame(1, CommissionRules::milestonesReady(4, 4));
        $this->assertSame(2, CommissionRules::milestonesReady(9, 4));
        $this->assertSame(0, CommissionRules::milestonesReady(9, 0));
        $this->assertSame(['done' => 1, 'missing' => 3, 'per_milestone' => 4], CommissionRules::progress(5, 4));
        $this->assertTrue(CommissionRules::isConversion(1, false));
        $this->assertFalse(CommissionRules::isConversion(2, false));
        $this->assertFalse(CommissionRules::isConversion(1, true));
        $this->assertSame('2026-01-31', CommissionRules::availableAt(CarbonImmutable::parse('2026-01-01'), 30)->toDateString());
        $this->assertTrue(CommissionRules::canTransition('AVAILABLE', 'REQUESTED'));
        $this->assertFalse(CommissionRules::canTransition('AVAILABLE', 'PAID'));
        $this->assertTrue(CommissionRules::canTransitionConversion('PENDING', 'VALIDATED'));
        $this->assertFalse(CommissionRules::canTransitionConversion('CANCELED', 'VALIDATED'));
        $none = ['same_user' => false, 'same_cpf' => false, 'same_instrument' => false, 'same_ip_recent' => false, 'signups_24h' => 0, 'referred_cancellations' => 0, 'chargebacks' => 0];
        $this->assertFalse(CommissionRules::assessFraud($none)['block']);
        $this->assertTrue(CommissionRules::assessFraud(['same_user' => true] + $none)['block']);
        $this->assertTrue(CommissionRules::assessFraud(['same_instrument' => true] + $none)['block']);
        $this->assertFalse(CommissionRules::assessFraud(['same_ip_recent' => true] + $none)['block']);
        $this->assertTrue(CommissionRules::assessFraud(['same_ip_recent' => true, 'signups_24h' => 12] + $none)['block']);
        $this->assertFalse(CommissionRules::canWithdraw(4999, 5000)['ok']);
    }

    public function test_coupons(): void
    {
        $c = ['type' => 'PERCENT', 'value' => 50, 'months' => null, 'starts_at' => CarbonImmutable::parse('2026-01-01'), 'ends_at' => CarbonImmutable::parse('2026-12-31'), 'max_uses' => 100, 'max_uses_per_user' => 1, 'min_amount_cents' => null, 'is_active' => true, 'allowed_plan_codes' => ['ESTUDANTE']];
        $ctx = ['now' => CarbonImmutable::parse('2026-06-01'), 'plan_code' => 'ESTUDANTE', 'amount_cents' => 1990, 'total_uses' => 0, 'user_uses' => 0];
        $this->assertSame(995, CouponRules::apply($c, $ctx)['discount_cents']);
        $this->assertSame('Cupom expirado', CouponRules::apply($c, ['now' => CarbonImmutable::parse('2027-01-01')] + $ctx)['reason']);
        $this->assertSame('Cupom esgotado', CouponRules::apply($c, ['total_uses' => 100] + $ctx)['reason']);
        $this->assertSame('Cupom não válido para este plano', CouponRules::apply($c, ['plan_code' => 'INTENSIVO'] + $ctx)['reason']);
        $this->assertSame(14, CouponRules::apply(['type' => 'FREE_TRIAL', 'value' => 14] + $c, $ctx)['trial_days']);
    }

    public function test_study_plan_prioritizes_weak_areas_and_spaced_repetition(): void
    {
        $areas = [
            ['area' => 'LINGUAGENS', 'percent' => 70, 'blank_rate' => 0], ['area' => 'HUMANAS', 'percent' => 65, 'blank_rate' => 0.1],
            ['area' => 'NATUREZA', 'percent' => 40, 'blank_rate' => 0.3], ['area' => 'MATEMATICA', 'percent' => null, 'blank_rate' => 0],
        ];
        $this->assertSame(['MATEMATICA', 'NATUREZA', 'HUMANAS', 'LINGUAGENS'], StudyPlanRules::prioritize($areas));
        $m = StudyPlanRules::allocateWeeklyMinutes(10, ['MATEMATICA', 'NATUREZA', 'HUMANAS', 'LINGUAGENS']);
        $this->assertGreaterThan($m['LINGUAGENS'], $m['MATEMATICA']);
        $this->assertSame(90, $m['REDACAO']);
        $in = ['kind' => 'REGULAR', 'start_date' => CarbonImmutable::parse('2026-09-14'), 'target_date' => null, 'weekly_hours' => 10, 'areas' => $areas, 'essay_weak_competencies' => [5], 'error_notebook_due' => 12, 'weak_topics' => [['slug' => 'funcoes', 'name' => 'Funções', 'area' => 'MATEMATICA']]];
        $tasks = StudyPlanRules::buildWeek($in);
        $this->assertNotEmpty(array_filter($tasks, fn ($t) => $t['kind'] === 'QUESTOES' && $t['topic_slug'] === 'funcoes'));
        $this->assertNotEmpty(array_filter($tasks, fn ($t) => $t['kind'] === 'REVISAO'));
        $this->assertSame(5, collect($tasks)->firstWhere('kind', 'REDACAO')['payload']['focus_competency']);
        $plan = StudyPlanRules::buildPlan(['kind' => 'INTENSIVO', 'target_date' => CarbonImmutable::parse('2026-10-05')] + $in);
        $this->assertGreaterThanOrEqual(2, count(array_filter($plan, fn ($t) => $t['kind'] === 'PROVA')));
        $this->assertTrue(collect($plan)->every(fn ($t) => $t['scheduled_on']->lte(CarbonImmutable::parse('2026-10-05'))));

        $this->assertSame(1, ErrorNotebookService::nextInterval(6, 2.5, 1)['interval']);
        $this->assertSame(3, ErrorNotebookService::nextInterval(1, 2.5, 5)['interval']);
        $this->assertGreaterThan(3, ErrorNotebookService::nextInterval(3, 2.5, 4)['interval']);
    }
}
