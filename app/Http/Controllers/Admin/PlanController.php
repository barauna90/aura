<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Preço, benefícios, limites e trial são configuráveis aqui — nunca no código. */
class PlanController extends Controller
{
    public function index(): View
    {
        return view('admin.plans', ['plans' => Plan::orderBy('sort_order')->get()]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_]+$/'],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:300'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'interval_months' => ['required', 'integer', 'min:1', 'max:12'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:90'],
            'benefits' => ['nullable', 'string'],
            'limits' => ['required', 'json'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
        $data['benefits'] = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) ($data['benefits'] ?? '')))));
        $data['limits'] = json_decode($data['limits'], true);
        $data['is_active'] = $request->boolean('is_active');
        $plan = Plan::updateOrCreate(['code' => $data['code']], $data);
        $audit->log('plan.upserted', $request->user()->id, 'Plan', $plan->id, $data);

        return back()->with('status', "Plano {$plan->code} salvo.");
    }
}
