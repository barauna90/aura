<?php

namespace App\Services\Referral;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** REFERRAL AGENT — regras puras de comissionamento e antifraude. */
final class CommissionRules
{
    private const TRANSITIONS = [
        'PENDING' => ['APPROVED', 'CANCELED', 'REVERSED'],
        'APPROVED' => ['AVAILABLE', 'CANCELED', 'REVERSED'],
        'AVAILABLE' => ['REQUESTED', 'REVERSED', 'CANCELED'],
        'REQUESTED' => ['PAID', 'AVAILABLE', 'CANCELED'],
        'PAID' => ['REVERSED'],
        'CANCELED' => [],
        'REVERSED' => [],
    ];

    /** @param array{model:string, value:int} $s */
    public static function computeCents(int $amountPaidCents, array $s): int
    {
        if ($amountPaidCents <= 0) {
            return 0;
        }

        return $s['model'] === 'FIXED' ? min($s['value'], $amountPaidCents) : (int) floor($amountPaidCents * $s['value'] / 100);
    }

    /** @param array{recurring:bool, first_payment_only:bool} $s */
    public static function isCommissionable(int $paymentOrdinal, array $s): bool
    {
        return $paymentOrdinal <= 1 || ($s['recurring'] && ! $s['first_payment_only']);
    }

    public static function availableAt(CarbonInterface $confirmedAt, int $validationDays): CarbonImmutable
    {
        return CarbonImmutable::instance($confirmedAt)->addDays($validationDays);
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
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
