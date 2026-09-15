<?php

namespace App\Services\Referral;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * REFERRAL AGENT — regras puras do programa de indicação (meta de indicações) e antifraude.
 *
 * Indicação (ReferralConversion): PENDING → VALIDATED | CANCELED | REVERSED
 * Bônus (Commission):             AVAILABLE → REQUESTED → PAID | CANCELED | REVERSED
 */
final class CommissionRules
{
    private const CONVERSION_TRANSITIONS = [
        'PENDING' => ['VALIDATED', 'CANCELED', 'REVERSED'],
        'VALIDATED' => ['CANCELED', 'REVERSED'],
        'CANCELED' => [],
        'REVERSED' => [],
    ];

    private const TRANSITIONS = [
        'AVAILABLE' => ['REQUESTED', 'CANCELED', 'REVERSED'],
        'REQUESTED' => ['PAID', 'AVAILABLE', 'CANCELED', 'REVERSED'],
        'PAID' => ['REVERSED'],
        'CANCELED' => [],
        'REVERSED' => [],
    ];

    /** Quantos bônus completos cabem em N indicações validadas ainda não usadas. */
    public static function milestonesReady(int $validatedUnused, int $perMilestone): int
    {
        return $perMilestone <= 0 ? 0 : intdiv(max(0, $validatedUnused), $perMilestone);
    }

    /** Progresso para o próximo bônus: [validadas nesta meta, faltam]. */
    public static function progress(int $validatedUnused, int $perMilestone): array
    {
        $done = $perMilestone <= 0 ? 0 : max(0, $validatedUnused) % $perMilestone;

        return ['done' => $done, 'missing' => max(0, $perMilestone - $done), 'per_milestone' => $perMilestone];
    }

    /** Só o PRIMEIRO pagamento confirmado de cada indicado conta como indicação efetivada. */
    public static function isConversion(int $paymentOrdinal, bool $alreadyConverted): bool
    {
        return $paymentOrdinal <= 1 && ! $alreadyConverted;
    }

    public static function availableAt(CarbonInterface $confirmedAt, int $validationDays): CarbonImmutable
    {
        return CarbonImmutable::instance($confirmedAt)->addDays($validationDays);
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function canTransitionConversion(string $from, string $to): bool
    {
        return in_array($to, self::CONVERSION_TRANSITIONS[$from] ?? [], true);
    }

    /**
     * @param  array{same_user:bool, same_cpf:bool, same_instrument:bool, same_ip_recent:bool, signups_24h:int, referred_cancellations:int, chargebacks:int}  $s
     * @return array{flags:array<int,string>, block:bool}
     */
    public static function assessFraud(array $s): array
    {
        $flags = [];
        if ($s['same_user']) {
            $flags[] = 'AUTOINDICACAO';
        }
        if ($s['same_cpf']) {
            $flags[] = 'MESMO_CPF';
        }
        if ($s['same_instrument']) {
            $flags[] = 'MESMO_MEIO_PAGAMENTO';
        }
        if ($s['same_ip_recent']) {
            $flags[] = 'MESMO_IP_RECENTE';
        }
        if ($s['signups_24h'] >= 10) {
            $flags[] = 'VOLUME_ANORMAL';
        }
        if ($s['referred_cancellations'] >= 3) {
            $flags[] = 'CANCELAMENTOS_REPETIDOS';
        }
        if ($s['chargebacks'] >= 1) {
            $flags[] = 'HISTORICO_CHARGEBACK';
        }
        $hard = ['AUTOINDICACAO', 'MESMO_CPF', 'MESMO_MEIO_PAGAMENTO', 'HISTORICO_CHARGEBACK'];
        $block = (bool) array_intersect($flags, $hard) || count($flags) >= 2;

        return ['flags' => $flags, 'block' => $block];
    }

    public static function canWithdraw(int $availableCents, int $minCents): array
    {
        return $availableCents < $minCents
            ? ['ok' => false, 'reason' => 'Valor mínimo para saque: R$ '.number_format($minCents / 100, 2, ',', '.')]
            : ['ok' => true];
    }
}
