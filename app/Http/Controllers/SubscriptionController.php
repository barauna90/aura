<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\AccessService;
use App\Services\Billing\AsaasGateway;
use App\Services\Billing\SubscriptionService;
use App\Services\Promotion\PromotionService;
use App\Services\Referral\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly AccessService $access,
        private readonly PromotionService $promotions,
        private readonly AsaasGateway $asaas,
        private readonly ReferralService $referrals,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('app.subscription.index', [
            'access' => $this->access->resolve($user),
            'subscription' => Subscription::with('plan')->where('user_id', $user->id)->latest()->first(),
            'plans' => Plan::where('is_active', true)->where('price_cents', '>', 0)->orderBy('sort_order')->get(),
            'payments' => Payment::where('user_id', $user->id)->latest()->limit(24)->get(),
            'gatewayReady' => $this->asaas->isConfigured(),
            'referralDiscount' => $this->referrals->discountFor($user, Plan::where('is_active', true)->where('price_cents', '>', 0)->orderBy('sort_order')->first() ?? new Plan(['price_cents' => 0])),
        ]);
    }

    public function checkCoupon(Request $request): JsonResponse
    {
        $data = $request->validate(['coupon' => ['required', 'string', 'max:40'], 'plan' => ['required', 'exists:plans,code']]);
        $plan = Plan::where('code', $data['plan'])->firstOrFail();

        return response()->json($this->promotions->evaluate($data['coupon'], $request->user(), $plan->code, $plan->price_cents));
    }

    public function checkout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'exists:plans,code'],
            'billing_type' => ['required', 'in:PIX,CREDIT_CARD,BOLETO'],
            'coupon' => ['nullable', 'string', 'max:40'],
            'cpf' => ['required', 'string', 'regex:/^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);
        $plan = Plan::where('code', $data['plan'])->firstOrFail();
        $out = $this->subscriptions->checkout($request->user(), $plan, $data['billing_type'], $data['coupon'] ?? null, $data['cpf'], $data['phone'] ?? null);
        session()->flash('pix_image', $out['pix_image']);

        return redirect()->route('subscription.payment', $out['payment']);
    }

    public function payment(Request $request, Payment $payment): View
    {
        abort_unless($payment->user_id === $request->user()->id, 404);
        $pixImage = session('pix_image');
        if (! $pixImage && $payment->billing_type === 'PIX' && $payment->status === 'PENDING') {
            $pixImage = $this->asaas->pixQrCode($payment->gateway_payment_id)['image'] ?? null;
        }
        $payment->load('subscription.plan');

        return view('app.subscription.payment', [
            'payment' => $payment,
            'pixImage' => $pixImage,
            // Antes de pagar o aluno pode trocar de plano; o valor é recalculado.
            'otherPlans' => $payment->status === 'PENDING' && $payment->subscription?->status === 'PENDING'
                ? Plan::where('is_active', true)->where('price_cents', '>', 0)->where('id', '!=', $payment->subscription->plan_id)->orderBy('sort_order')->get()
                : collect(),
            'referralDiscount' => $this->referrals->discountFor($request->user(), $payment->subscription?->plan ?? new Plan(['price_cents' => 0])),
        ]);
    }

    /** Troca o plano de uma assinatura ainda não paga e gera a nova cobrança. */
    public function changePlan(Request $request): RedirectResponse
    {
        $data = $request->validate(['plan' => ['required', 'exists:plans,code']]);
        $plan = Plan::where('code', $data['plan'])->firstOrFail();
        $out = $this->subscriptions->changePendingPlan($request->user(), $plan);
        session()->flash('pix_image', $out['pix_image']);

        return redirect()->route('subscription.payment', $out['payment'])->with('status', "Plano alterado para {$plan->name}. Nova cobrança gerada.");
    }

    public function cancel(Request $request): RedirectResponse
    {
        $sub = $this->subscriptions->cancel($request->user());

        return back()->with('status', 'Cancelamento agendado. Você mantém o acesso até '.$sub->current_period_end->format('d/m/Y').'.');
    }
}
