<?php

namespace App\Services\Essay;

use App\Models\Essay;
use App\Models\EssayEvaluation;
use App\Models\EssayFinalResult;
use App\Services\Ai\AiService;
use App\Services\AuditService;
use App\Services\SettingsService;
use App\Support\Disclaimers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * REDACTION EVALUATION ORCHESTRATOR:
 *   ZERO SCORE VALIDATOR → AVALIADOR A ∥ AVALIADOR B → (divergência?) AVALIADOR C
 *   → CONSISTENCY AUDITOR → agregação → relatório.
 */
class EssayEvaluationOrchestrator
{
    public function __construct(
        private readonly AiService $ai,
        private readonly AuditService $audit,
        private readonly SettingsService $settings,
    ) {}

    public function evaluate(Essay $essay): void
    {
        $essay->load('prompt.exam.edition.zeroRules');
        if (! $essay->final_text) {
            throw new \RuntimeException('Redação sem texto final.');
        }
        $essay->update(['status' => 'EVALUATING']);

        try {
            $rules = $essay->prompt->exam->edition->zeroRules->where('review_status', 'VERIFIED')
                ->map(fn ($r) => ['code' => $r->code, 'description' => $r->description])->values()->all();
            $text = $essay->final_text;
            $wordCount = count(preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY));

            // 1) Validador determinístico de zero (mínimo de linhas derivado da folha da edição).
            $zero = ZeroScoreRules::check($text, $rules, max(1, (int) round($essay->prompt->max_lines * 0.25)));
            if ($zero['zero']) {
                $this->storeFinal($essay, [], ['total' => 0, 'competency_scores' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0], 'zero' => true, 'zero_reason' => $zero['code']], [], false, 0, $wordCount);

                return;
            }

            // 2) Avaliadores A e B — independentes.
            $year = $essay->prompt->exam->edition->year;
            $run = fn (string $v) => $this->runEvaluator($v, $year, $rules, $zero['deferred'], $essay->prompt->theme, $essay->prompt->motivating_texts ?? [], $text);
            $a = $run('A');
            $b = $run('B');
            $evals = [$a, $b];

            // 3) Avaliador C se houver divergência acima do limite configurado.
            $usedThird = false;
            $tTotal = (int) $this->settings->get('essay.divergence_total');
            $tComp = (int) $this->settings->get('essay.divergence_competency');
            if (ScoringRules::needsThirdEvaluator($a, $b, $tTotal, $tComp)) {
                $usedThird = true;
                $evals[] = $run('C');
                $this->audit->log('essay.third_evaluator', null, 'Essay', $essay->id, ScoringRules::divergence($a, $b));
            }

            // 4) Auditor de consistência + 5) agregação.
            $flags = ScoringRules::auditConsistency($evals, $wordCount);
            $final = ScoringRules::aggregate($evals);
            $this->storeFinal($essay, $evals, $final, $flags, $usedThird, ScoringRules::divergence($a, $b)['total'], $wordCount);
        } catch (Throwable $e) {
            Log::error("Falha na avaliação da redação {$essay->id}: {$e->getMessage()}");
            $essay->update(['status' => 'FAILED']);
            $this->audit->alert('ERROR', 'essays', "Falha na correção da redação {$essay->id}: {$e->getMessage()}");
            throw $e;
        }
    }

    private function runEvaluator(string $variant, int $year, array $rules, array $deferred, string $theme, array $texts, string $essay): array
    {
        $out = $this->ai->complete('ESSAY_EVAL', EvaluatorPrompts::system($variant, $year, $rules, $deferred), EvaluatorPrompts::user($theme, $texts, $essay), $variant);
        $parsed = AiService::extractJson($out['text']);

        $competencies = [];
        foreach ([1, 2, 3, 4, 5] as $c) {
            $found = null;
            foreach ($parsed['competencies'] ?? [] as $x) {
                if ((int) ($x['competency'] ?? 0) === $c) {
                    $found = $x;
                }
            }
            $competencies[] = [
                'competency' => $c,
                'score' => ScoringRules::normalizeScore((float) ($found['score'] ?? 0)),
                'justification' => (string) ($found['justification'] ?? ''),
                'problematic_excerpts' => array_values(array_map('strval', is_array($found['problematicExcerpts'] ?? null) ? $found['problematicExcerpts'] : [])),
            ];
        }
        // Zero semântico só é aceito para códigos delegados da edição.
        $zeroScore = ! empty($parsed['zeroScore']) && ! empty($parsed['zeroReason']) && in_array($parsed['zeroReason'], $deferred, true);

        return [
            'evaluator' => $variant,
            'zero_score' => $zeroScore,
            'zero_reason' => $zeroScore ? $parsed['zeroReason'] : null,
            'competencies' => $competencies,
            'positives' => array_values(array_map('strval', $parsed['positives'] ?? [])),
            'improvements' => array_values(array_map('strval', $parsed['improvements'] ?? [])),
            'raw' => $parsed,
            'provider' => $out['provider'],
            'model' => $out['model'],
        ];
    }

    private function storeFinal(Essay $essay, array $evals, array $final, array $flags, bool $usedThird, int $divergence, int $wordCount): void
    {
        $scores = $final['competency_scores'];
        asort($scores);
        $weak = array_slice(array_keys($scores), 0, 2);
        $s = $final['competency_scores'];
        $checklist = [
            ['item' => 'Tese clara na introdução', 'ok' => $s[2] >= 120],
            ['item' => 'Dois argumentos desenvolvidos com repertório', 'ok' => $s[3] >= 120],
            ['item' => 'Conectivos entre parágrafos e períodos', 'ok' => $s[4] >= 120],
            ['item' => 'Proposta com agente, ação, meio, efeito e detalhamento', 'ok' => $s[5] >= 160],
            ['item' => 'Norma culta sem desvios recorrentes', 'ok' => $s[1] >= 160],
        ];
        $recommendation = [
            'focus_competencies' => $weak,
            'suggestions' => array_map(fn ($c) => "Treinar competência {$c}: reler os critérios publicados e refazer um parágrafo com foco nela.", $weak),
            'next_step' => 'Escreva uma nova redação com proposta oficial de outra edição em até 7 dias.',
            'notice' => Disclaimers::ESSAY_EVALUATION,
        ];

        DB::transaction(function () use ($essay, $evals, $final, $flags, $usedThird, $divergence, $checklist, $recommendation) {
            foreach ($evals as $e) {
                $ev = EssayEvaluation::create([
                    'essay_id' => $essay->id, 'evaluator' => $e['evaluator'], 'provider' => $e['provider'], 'model' => $e['model'],
                    'zero_score' => $e['zero_score'], 'zero_reason' => $e['zero_reason'],
                    'total' => ScoringRules::total($e), 'positives' => $e['positives'], 'improvements' => $e['improvements'], 'raw_response' => $e['raw'],
                ]);
                $ev->competencies()->createMany($e['competencies']);
            }
            EssayFinalResult::updateOrCreate(['essay_id' => $essay->id], [
                'total' => $final['total'],
                'competency_scores' => $final['competency_scores'],
                'used_third_evaluator' => $usedThird,
                'divergence' => $divergence,
                'consistency_flags' => [...$flags, ...($final['zero'] ? ["ZERO:{$final['zero_reason']}"] : [])],
                'checklist' => $checklist,
                'study_recommendation' => $recommendation,
            ]);
            $essay->update(['status' => 'EVALUATED']);
        });
        if ($flags) {
            $this->audit->alert('WARN', 'essays.consistency', "Inconsistências na redação {$essay->id}", ['flags' => $flags, 'word_count' => $wordCount]);
        }
    }
}
