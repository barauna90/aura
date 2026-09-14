<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsage;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\Essay;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Payment;
use App\Models\Question;
use App\Models\Subscription;
use App\Models\SystemAlert;
use App\Models\User;
use App\Services\Billing\AsaasGateway;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(AsaasGateway $asaas): View
    {
        $monthStart = now()->startOfMonth();
        $active = Subscription::with('plan')->where('status', 'ACTIVE')->get();
        $canceledMonth = Subscription::where('status', 'CANCELED')->where('canceled_at', '>=', $monthStart)->count();
        $activeAtStart = Subscription::where('created_at', '<', $monthStart)->where(fn ($q) => $q->whereNull('canceled_at')->orWhere('canceled_at', '>=', $monthStart))->count();
        $revenue = Payment::where('status', 'CONFIRMED')->where('confirmed_at', '>=', $monthStart)->selectRaw('COALESCE(SUM(amount_cents - discount_cents),0) as total')->value('total');

        return view('admin.dashboard', [
            'users' => User::count(),
            'subscribers' => $active->count(),
            'trials' => Subscription::where('status', 'TRIALING')->count(),
            'pending' => Subscription::where('status', 'PENDING')->count(),
            'mrr_cents' => $active->sum(fn ($s) => (int) round($s->plan->price_cents / max(1, $s->plan->interval_months))),
            'revenue_month_cents' => (int) $revenue,
            'cancellations_month' => $canceledMonth,
            'churn' => $activeAtStart ? round($canceledMonth / $activeAtStart * 100, 1) : 0,
            'coupons' => Coupon::where('is_active', true)->count(),
            'affiliates' => User::has('referrals')->count(),
            'commissions' => Commission::selectRaw('status, count(*) as n, COALESCE(sum(amount_cents),0) as total')->groupBy('status')->get(),
            'exams' => Exam::selectRaw('review_status, count(*) as n')->groupBy('review_status')->pluck('n', 'review_status'),
            'questions' => Question::count(),
            'essays' => Essay::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'sessions_30d' => ExamSession::where('created_at', '>=', now()->subDays(30))->count(),
            'ai' => AiUsage::where('created_at', '>=', $monthStart)->selectRaw('count(*) as calls, COALESCE(sum(input_tokens+output_tokens),0) as tokens, COALESCE(sum(cost_cents),0) as cost')->first(),
            'alerts' => SystemAlert::whereNull('resolved_at')->latest()->limit(20)->get(),
            'asaasReady' => $asaas->isConfigured(),
        ]);
    }
}
