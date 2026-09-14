<?php

use App\Services\Billing\SubscriptionService;
use App\Services\Exam\ExamEngineService;
use App\Services\Referral\ReferralService;
use Illuminate\Support\Facades\Schedule;

// Encerra sessões cujo tempo terminou mesmo sem o cliente chamar o estado.
Schedule::call(fn (ExamEngineService $engine) => $engine->expireStale())->everyMinute()->name('exam:expire-stale')->withoutOverlapping();

// Expira assinaturas com período encerrado (o webhook cuida das renovações).
Schedule::call(fn (SubscriptionService $subs) => $subs->expireEnded())->hourly()->name('subscriptions:expire')->withoutOverlapping();

// Libera comissões após o período de validação.
Schedule::call(fn (ReferralService $ref) => $ref->releaseMatured())->dailyAt('03:00')->name('referrals:release')->withoutOverlapping();
