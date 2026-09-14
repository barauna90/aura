<?php

namespace App\Services\Exam;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Cronômetro — regras puras. A fonte da verdade é o servidor:
 * started_at + duração oficial da prova → expected_end_at.
 */
final class TimerRules
{
    public static function expectedEnd(CarbonInterface $startedAt, int $durationMinutes): CarbonImmutable
    {
        return CarbonImmutable::instance($startedAt)->addMinutes($durationMinutes);
    }

    /** Segundos restantes (>= 0). Nulo se não iniciou. */
    public static function remainingSeconds(array $t, ?CarbonInterface $now = null): ?int
    {
        if (! $t['started_at'] || ! $t['expected_end_at']) {
            return null;
        }
        $now ??= CarbonImmutable::now();
        $reference = $t['status'] === 'PAUSED' && $t['paused_at'] ? $t['paused_at'] : $now;

        return max(0, (int) floor($t['expected_end_at']->getTimestamp() - $reference->getTimestamp()));
    }

    public static function isExpired(array $t, ?CarbonInterface $now = null): bool
    {
        if (in_array($t['status'], ['FINISHED', 'EXPIRED', 'PAUSED'], true)) {
            return false;
        }
        $r = self::remainingSeconds($t, $now);

        return $r !== null && $r <= 0;
    }

    /** Tempo efetivamente utilizado, descontando pausas (modo estudo). */
    public static function timeUsedSeconds(array $t, CarbonInterface $finishedAt): int
    {
        if (! $t['started_at']) {
            return 0;
        }
        $gross = $finishedAt->getTimestamp() - $t['started_at']->getTimestamp();

        return max(0, $gross - (int) ($t['paused_seconds'] ?? 0));
    }

    /** @return array{ok:bool, reason?:string} */
    public static function canPause(array $t): array
    {
        if ($t['mode'] === 'PROVA_REAL') {
            return ['ok' => false, 'reason' => 'O Modo Prova Real não permite pausar o cronômetro.'];
        }
        if ($t['status'] !== 'IN_PROGRESS') {
            return ['ok' => false, 'reason' => 'A sessão não está em andamento.'];
        }

        return ['ok' => true];
    }

    /** Retomar: desloca o encerramento previsto pelo tempo pausado. */
    public static function resume(array $t, ?CarbonInterface $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $pausedFor = $now->getTimestamp() - $t['paused_at']->getTimestamp();

        return [
            'expected_end_at' => CarbonImmutable::instance($t['expected_end_at'])->addSeconds($pausedFor),
            'paused_seconds' => (int) $t['paused_seconds'] + $pausedFor,
        ];
    }

    public static function formatHms(int $seconds): string
    {
        $s = max(0, $seconds);

        return sprintf('%02d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60);
    }
}
