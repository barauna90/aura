<?php

namespace App\Services\Ai;

use App\Models\AiUsage;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Fachada com registro de uso/custo de IA (observabilidade). */
class AiService
{
    private ?AiProvider $provider = null;

    public function __construct(private readonly SettingsService $settings) {}

    public function provider(): AiProvider
    {
        if ($this->provider) {
            return $this->provider;
        }
        $name = $this->settings->get('ai.provider') ?: 'mock';
        if ($name === 'anthropic' && $this->settings->isSet('ai.anthropic_api_key')) {
            return $this->provider = new AnthropicAiProvider($this->settings->get('ai.anthropic_api_key'), $this->settings->get('ai.model') ?: 'claude-opus-5');
        }

        return $this->provider = new MockAiProvider;
    }

    /** Permite injetar um provider (testes). */
    public function using(AiProvider $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function complete(string $purpose, string $system, string $user, ?string $variant = null, int $maxTokens = 8000): array
    {
        $p = $this->provider();
        $started = hrtime(true);
        try {
            $out = $p->complete($purpose, $system, $user, $variant, $maxTokens);
            $this->record($purpose, $out, true);

            return $out;
        } catch (Throwable $e) {
            $this->record($purpose, ['provider' => $p->name(), 'model' => $p->model(), 'input_tokens' => 0, 'output_tokens' => 0, 'latency_ms' => (int) ((hrtime(true) - $started) / 1e6)], false, $e->getMessage());
            throw $e;
        }
    }

    /** Extrai o primeiro objeto JSON de uma resposta textual. */
    public static function extractJson(string $text): array
    {
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $m)) {
            $text = $m[1];
        }
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false) {
            throw new \RuntimeException('Resposta da IA não contém JSON.');
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        if (! is_array($data)) {
            throw new \RuntimeException('JSON inválido na resposta da IA.');
        }

        return $data;
    }

    private function record(string $purpose, array $out, bool $success, ?string $error = null): void
    {
        try {
            AiUsage::create([
                'provider' => $out['provider'], 'model' => $out['model'], 'purpose' => $purpose,
                'input_tokens' => (int) $out['input_tokens'], 'output_tokens' => (int) $out['output_tokens'],
                'latency_ms' => (int) $out['latency_ms'], 'success' => $success, 'error' => $error,
            ]);
        } catch (Throwable $e) {
            Log::warning('Falha ao registrar uso de IA: '.$e->getMessage());
        }
    }
}
