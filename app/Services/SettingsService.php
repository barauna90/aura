<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Configurações administráveis pelo painel (chaves de API ficam criptografadas
 * no banco). Ordem de resolução: banco → .env → padrão.
 */
class SettingsService
{
    public const KEYS = [
        'asaas.environment' => ['label' => 'Ambiente Asaas', 'secret' => false, 'env' => 'ASAAS_ENVIRONMENT', 'default' => 'sandbox'],
        'asaas.api_key' => ['label' => 'Chave de API Asaas', 'secret' => true, 'env' => 'ASAAS_API_KEY', 'default' => null],
        'asaas.webhook_token' => ['label' => 'Token do webhook Asaas', 'secret' => true, 'env' => 'ASAAS_WEBHOOK_TOKEN', 'default' => null],
        'ai.provider' => ['label' => 'Provedor de IA', 'secret' => false, 'env' => 'AI_PROVIDER', 'default' => 'mock'],
        'ai.anthropic_api_key' => ['label' => 'Chave de API Anthropic', 'secret' => true, 'env' => 'ANTHROPIC_API_KEY', 'default' => null],
        'ai.model' => ['label' => 'Modelo de IA', 'secret' => false, 'env' => 'AI_MODEL', 'default' => 'claude-opus-5'],
        'essay.divergence_total' => ['label' => 'Divergência máx. total (redação)', 'secret' => false, 'env' => 'ESSAY_DIVERGENCE_TOTAL', 'default' => '100'],
        'essay.divergence_competency' => ['label' => 'Divergência máx. por competência', 'secret' => false, 'env' => 'ESSAY_DIVERGENCE_COMPETENCY', 'default' => '80'],
        'site.support_email' => ['label' => 'E-mail de suporte', 'secret' => false, 'env' => 'SUPPORT_EMAIL', 'default' => null],
    ];

    public function get(string $key): ?string
    {
        $meta = self::KEYS[$key] ?? ['env' => null, 'default' => null];

        return Cache::remember("settings.{$key}", 300, function () use ($key, $meta) {
            $row = Setting::find($key);
            if ($row && $row->value !== null && $row->value !== '') {
                return $row->encrypted ? Crypt::decryptString($row->value) : $row->value;
            }
            $fromEnv = $meta['env'] ? env($meta['env']) : null;

            return $fromEnv !== null && $fromEnv !== '' ? (string) $fromEnv : $meta['default'];
        });
    }

    public function set(string $key, ?string $value): void
    {
        $secret = self::KEYS[$key]['secret'] ?? false;
        Setting::updateOrCreate(['key' => $key], [
            'value' => $value === null || $value === '' ? null : ($secret ? Crypt::encryptString($value) : $value),
            'encrypted' => $secret,
        ]);
        Cache::forget("settings.{$key}");
    }

    public function isSet(string $key): bool
    {
        $v = $this->get($key);

        return $v !== null && $v !== '';
    }

    /** Mostra só o final de um segredo (para o painel). */
    public function masked(string $key): ?string
    {
        $v = $this->get($key);

        return $v ? str_repeat('•', 8).substr($v, -4) : null;
    }
}
