<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Models\StudyTask;
use App\Services\Study\StudyPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudyPlanController extends Controller
{
    public function __construct(private readonly StudyPlanService $plans) {}

    public function index(Request $request): View
    {
        $plan = $this->plans->current($request->user());

        return view('app.study.plan', [
            'plan' => $plan,
            'byDay' => $plan ? $plan->tasks->groupBy(fn ($t) => $t->scheduled_on->toDateString()) : collect(),
            'goals' => $request->user()->goals()->where('active', true)->get(),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:REGULAR,INTENSIVO'],
            'weekly_hours' => ['nullable', 'integer', 'min:1', 'max:80'],
            'days_left' => ['nullable', 'integer', 'min:1', 'max:400'],
        ]);
        $this->plans->generate($request->user(), $data['kind'], $data['weekly_hours'] ?? null, $data['days_left'] ?? null);

        return redirect()->route('study.plan')->with('status', 'Plano gerado a partir do seu desempenho.');
    }

    public function updateTask(Request $request, StudyTask $task): RedirectResponse
    {
        abort_unless($task->plan->user_id === $request->user()->id, 404);
        $data = $request->validate(['status' => ['nullable', 'in:PENDING,DONE,SKIPPED'], 'scheduled_on' => ['nullable', 'date']]);
        $task->update(array_filter($data, fn ($v) => $v !== null));

        return back();
    }

    public function storeGoal(Request $request): RedirectResponse
    {
        $data = $request->validate(['kind' => ['required', 'in:QUESTOES_DIA,HORAS_SEMANA,REDACOES_MES,PROVAS_MES,SEQUENCIA'], 'target' => ['required', 'integer', 'min:1', 'max:1000']]);
        Goal::create($data + ['user_id' => $request->user()->id]);

        return back()->with('status', 'Meta criada.');
    }

    public function destroyGoal(Request $request, Goal $goal): RedirectResponse
    {
        abort_unless($goal->user_id === $request->user()->id, 404);
        $goal->update(['active' => false]);

        return back();
    }
}
