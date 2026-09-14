<?php

namespace App\Services\Billing;

use App\Models\Essay;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Plan;
use App\Models\Scholarship;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Direitos de acesso. Ordem: bolsa ativa → assinatura TRIALING/ACTIVE
 * (confirmada por webhook) → plano gratuito. Nunca confia no navegador.
 */
class AccessService
{
    private const FREE_FALLBACK = ['fullExamsPerMonth' => 1, 'essaysPerMonth' => 1, 'studyPlan' => false, 'tutor' => false, 'errorNotebook' => true];

    /** @return array{tier:string, source:string, plan_code:string, plan_name:string, limits:array, valid_until:?CarbonInterface} */
    public function resolve(User $user): array
    {
        $scholarship = Scholarship::where('user_id', $user->id)->where('active', true)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->first();
        if ($scholarship) {
            $plan = Plan::where('code', 'INTENSIVO')->first();

            return [
                'tier' => 'PREMIUM', 'source' => 'SCHOLARSHIP', 'plan_code' => $plan?->code ?? 'BOLSA', 'plan_name' => 'Bolsa de estudos',
                'limits' => array_merge(['fullExamsPerMonth' => -1, 'essaysPerMonth' => 8, 'studyPlan' => true, 'tutor' => true, 'errorNotebook' => true], $plan?->limits ?? []),
                'valid_until' => $scholarship->ends_at,
            ];
        }

        $sub = Subscription::with('plan')->where('user_id', $user->id)->whereIn('status', ['TRIALING', 'ACTIVE'])
            ->where('current_period_end', '>', now())->latest('current_period_end')->first();
        if ($sub) {
            return [
                'tier' => 'PREMIUM', 'source' => 'SUBSCRIPTION', 'plan_code' => $sub->plan->code, 'plan_name' => $sub->plan->name,
                'limits' => array_merge(self::FREE_FALLBACK, $sub->plan->limits ?? []), 'valid_until' => $sub->current_period_end,
            ];
        }

        $free = Plan::where('code', 'FREE')->first();

        return [
            'tier' => 'FREE', 'source' => 'FREE_PLAN', 'plan_code' => 'FREE', 'plan_name' => $free?->name ?? 'Plano gratuito',
            'limits' => array_merge(self::FREE_FALLBACK, $free?->limits ?? []), 'valid_until' => null,
        ];
    }

    public function isPremium(User $user): bool
    {
        return $this->resolve($user)['tier'] === 'PREMIUM';
    }

    public function assertCanStartFullExam(User $user, Exam $exam): void
    {
        if ($exam->is_free_sample) {
            return;
        }
        $limit = $this->resolve($user)['limits']['fullExamsPerMonth'];
        if ($limit === -1) {
            return;
        }
        $used = ExamSession::where('user_id', $user->id)->where('mode', 'PROVA_REAL')
            ->where('created_at', '>=', now()->startOfMonth())
            ->whereHas('exam', fn ($q) => $q->where('is_free_sample', false))->count();
        if ($used >= $limit) {
            throw new AuthorizationException("Seu plano permite {$limit} prova(s) completa(s) por mês. Assine para liberar acesso ilimitado.");
        }
    }

    public function assertCanSubmitEssay(User $user): void
    {
        $limit = $this->resolve($user)['limits']['essaysPerMonth'];
        if ($limit === -1) {
            return;
        }
        $used = Essay::where('user_id', $user->id)->where('submitted_at', '>=', now()->startOfMonth())->count();
        if ($used >= $limit) {
            throw new AuthorizationException("Seu plano permite {$limit} correção(ões) de redação por mês.");
        }
    }

    public function assertFeature(User $user, string $feature): void
    {
        if (empty($this->resolve($user)['limits'][$feature])) {
            throw new AuthorizationException('Este recurso está disponível nos planos pagos e para bolsistas.');
        }
    }
}
