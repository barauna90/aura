<?php

namespace App\Services\Essay;

use App\Jobs\EvaluateEssay;
use App\Models\Essay;
use App\Models\EssayPrompt;
use App\Models\ExamSession;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Billing\AccessService;
use App\Support\Disclaimers;
use App\Support\Enem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * ESSAY AGENT: em sessão PROVA_REAL o rascunho é salvo sem IA/corretor e só pode
 * ser enviado após encerrar a prova; treino avulso usa proposta oficial VERIFIED.
 */
class EssayService
{
    public function __construct(private readonly AuditService $audit, private readonly AccessService $access) {}

    public function openForSession(User $user, ExamSession $session): Essay
    {
        $prompt = $session->exam->essayPrompt;
        if (! $prompt || $prompt->review_status !== 'VERIFIED') {
            throw ValidationException::withMessages(['essay' => 'Esta prova não possui proposta de redação verificada.']);
        }

        return Essay::firstOrCreate(['exam_session_id' => $session->id], ['user_id' => $user->id, 'essay_prompt_id' => $prompt->id]);
    }

    public function openForPrompt(User $user, EssayPrompt $prompt): Essay
    {
        if ($prompt->review_status !== 'VERIFIED') {
            throw ValidationException::withMessages(['essay' => 'Proposta não disponível.']);
        }

        return Essay::create(['user_id' => $user->id, 'essay_prompt_id' => $prompt->id]);
    }

    public function saveDraft(Essay $essay, string $text): void
    {
        if ($essay->status !== 'DRAFT') {
            throw new AuthorizationException('Redação já enviada.');
        }
        $session = $essay->session;
        if ($session && $session->mode === 'PROVA_REAL' && $session->isFinished()) {
            throw new AuthorizationException('O tempo da prova terminou; o rascunho não pode mais ser alterado.');
        }
        $lines = ZeroScoreRules::countLines($text);
        if ($lines > $essay->prompt->max_lines) {
            throw ValidationException::withMessages(['text' => "A redação deve ter no máximo {$essay->prompt->max_lines} linhas, como na folha oficial."]);
        }
        $essay->update(['draft_text' => $text, 'line_count' => $lines]);
    }

    public function submit(Essay $essay): void
    {
        if ($essay->status !== 'DRAFT') {
            throw ValidationException::withMessages(['essay' => 'Redação já enviada.']);
        }
        $session = $essay->session;
        if ($session && $session->mode === 'PROVA_REAL' && ! $session->isFinished()) {
            throw new AuthorizationException('No Modo Prova Real a redação só pode ser enviada após encerrar a prova.');
        }
        $this->access->assertCanSubmitEssay($essay->user);
        $text = (string) $essay->draft_text;
        $essay->update(['final_text' => $text, 'status' => 'SUBMITTED', 'submitted_at' => now(), 'line_count' => ZeroScoreRules::countLines($text)]);
        $this->audit->log('essay.submitted', $essay->user_id, 'Essay', $essay->id);
        EvaluateEssay::dispatch($essay);
    }

    public function report(Essay $essay): array
    {
        $essay->load(['prompt.exam.edition', 'finalResult', 'evaluations.competencies']);
        $final = $essay->finalResult;
        $scores = $final?->competency_scores ?? [];
        $competencies = [];
        foreach ([1, 2, 3, 4, 5] as $c) {
            $competencies[] = [
                'competency' => $c,
                'label' => Enem::ESSAY_COMPETENCIES[$c],
                'score' => $scores[$c] ?? $scores[(string) $c] ?? null,
                'analyses' => $essay->evaluations->sortBy('evaluator')->map(function ($e) use ($c) {
                    $item = $e->competencies->firstWhere('competency', $c);

                    return ['evaluator' => $e->evaluator, 'score' => $item?->score ?? 0, 'justification' => $item?->justification ?? '', 'excerpts' => $item?->problematic_excerpts ?? []];
                })->values()->all(),
            ];
        }

        return [
            'competencies' => $competencies,
            'positives' => $essay->evaluations->flatMap->positives->unique()->values()->all(),
            'improvements' => $essay->evaluations->flatMap->improvements->unique()->values()->all(),
            'checklist' => $final?->checklist ?? [],
            'recommendation' => $final?->study_recommendation,
            'total' => $final?->total,
            'used_third' => (bool) $final?->used_third_evaluator,
            'flags' => $final?->consistency_flags ?? [],
            'score_label' => Disclaimers::ESSAY_SCORE_LABEL,
            'notice' => Disclaimers::ESSAY_EVALUATION,
        ];
    }
}
