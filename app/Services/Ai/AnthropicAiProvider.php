<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Provider Anthropic (Claude) via Messages API. Usa adaptive thinking, prompt caching
 * no system prompt e fallback server-side (o modelo pode recusar por política;
 * o fallback roteia automaticamente na mesma chamada).
 */
class AnthropicAiProvider implements AiProvider
{
    public function __construct(private readonly string $apiKey, private readonly string $model) {}

    public function name(): string
    {
        return 'anthropic';
    }

    public function model(): string
    {
        return $this->model;
    }

    public function complete(string $purpose, string $system, string $user, ?string $variant = null, int $maxTokens = 8000): array
    {
        $started = hrtime(true);
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'anthropic-beta' => 'server-side-fallback-2026-07-01',
        ])->timeout(180)->retry(2, 2000, throw: false)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'fallbacks' => 'default',
            'thinking' => ['type' => 'adaptive'],
            'output_config' => ['effort' => 'high'],
            'system' => [['type' => 'text', 'text' => $system, 'cache_control' => ['type' => 'ephemeral']]],
            'messages' => [['role' => 'user', 'content' => $user]],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic API: '.$response->status().' '.$response->body());
        }
        $data = $response->json();
        if (($data['stop_reason'] ?? null) === 'refusal') {
            throw new RuntimeException('O modelo recusou a solicitação.');
        }
        $text = implode("\n", array_map(fn ($b) => $b['text'], array_filter($data['content'] ?? [], fn ($b) => ($b['type'] ?? '') === 'text')));

        return [
            'text' => $text,
            'provider' => 'anthropic',
            'model' => $data['model'] ?? $this->model,
            'input_tokens' => (int) ($data['usage']['input_tokens'] ?? 0),
            'output_tokens' => (int) ($data['usage']['output_tokens'] ?? 0),
            'latency_ms' => (int) ((hrtime(true) - $started) / 1e6),
        ];
    }
}
