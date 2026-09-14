<?php

namespace App\Services\Ai;

/**
 * Provider simulado para desenvolvimento e testes: avaliação determinística por
 * heurísticas superficiais (tamanho, parágrafos, presença de proposta). Não usar em produção.
 */
class MockAiProvider implements AiProvider
{
    public function name(): string
    {
        return 'mock';
    }

    public function model(): string
    {
        return 'mock-evaluator';
    }

    public function complete(string $purpose, string $system, string $user, ?string $variant = null, int $maxTokens = 8000): array
    {
        $started = hrtime(true);
        $text = match ($purpose) {
            'ESSAY_EVAL' => json_encode($this->mockEssay($user, $variant ?? ''), JSON_UNESCAPED_UNICODE),
            'TUTOR' => 'Resposta simulada do Professor IA (provider mock). Configure AI_PROVIDER=anthropic e a chave no painel para respostas reais.',
            default => json_encode(['summary' => 'Conteúdo simulado (provider mock).']),
        };

        return [
            'text' => $text, 'provider' => 'mock', 'model' => 'mock-evaluator',
            'input_tokens' => (int) (strlen($user) / 4), 'output_tokens' => (int) (strlen($text) / 4),
            'latency_ms' => (int) ((hrtime(true) - $started) / 1e6),
        ];
    }

    private function mockEssay(string $user, string $variant): array
    {
        $parts = explode('=== TEXTO DO ALUNO ===', $user);
        $essay = trim($parts[1] ?? $user);
        $words = count(preg_split('/\s+/', $essay, -1, PREG_SPLIT_NO_EMPTY));
        $paragraphs = count(array_filter(preg_split('/\n\s*\n/', $essay), fn ($p) => trim($p) !== ''));
        $hasProposal = (bool) preg_match('/propost|deve[m]? |cabe ao|é necessário|medida/iu', $essay);
        $jitter = ((hexdec(substr(md5($essay.$variant), 0, 2)) % 3) * 40) - 40;
        $base = $words < 120 ? 80 : ($words < 250 ? 120 : 160);
        $clamp = fn (int $n) => max(0, min(200, (int) (round($n / 40) * 40)));
        $scores = [
            1 => $clamp($base),
            2 => $clamp($paragraphs >= 4 ? $base : $base - 40),
            3 => $clamp($base + $jitter),
            4 => $clamp($paragraphs >= 3 ? $base : $base - 40),
            5 => $clamp($hasProposal ? $base : 40),
        ];
        $competencies = [];
        foreach ($scores as $c => $score) {
            $competencies[] = ['competency' => $c, 'score' => $score, 'justification' => "Avaliação simulada (mock) da competência {$c}.", 'problematicExcerpts' => []];
        }

        return [
            'zeroScore' => false,
            'zeroReason' => null,
            'competencies' => $competencies,
            'positives' => ['Estrutura em parágrafos identificada.'],
            'improvements' => [$hasProposal ? 'Detalhar agente, ação e meio na proposta de intervenção.' : 'Incluir proposta de intervenção completa.'],
        ];
    }
}
