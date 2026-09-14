<?php

namespace App\Http\Controllers;

use App\Models\ExamSession;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function index(Request $request): View
    {
        $period = in_array($request->query('periodo'), ['7D', '30D', '90D', 'ALL'], true) ? $request->query('periodo') : '30D';
        $user = $request->user();
        $history = ExamSession::with(['exam.edition', 'result', 'essay.finalResult'])->where('user_id', $user->id)
            ->whereIn('status', ['FINISHED', 'EXPIRED'])->latest('finished_at')->paginate(15);

        return view('app.performance', [
            'period' => $period,
            'overview' => $this->analytics->overview($user, $period),
            'weakTopics' => $this->analytics->weakTopics($user, 8),
            'history' => $history,
        ]);
    }

    public function compare(Request $request): View
    {
        $data = $request->validate(['a' => ['required', 'integer'], 'b' => ['required', 'integer']]);
        $user = $request->user();
        $find = fn (int $id) => ExamSession::with(['exam', 'result', 'essay.finalResult'])->where('user_id', $user->id)->whereIn('status', ['FINISHED', 'EXPIRED'])->findOrFail($id);
        $a = $find($data['a']);
        $b = $find($data['b']);
        $rows = [];
        $areas = collect($a->result->by_area)->pluck('area')->merge(collect($b->result->by_area)->pluck('area'))->unique();
        foreach ($areas as $area) {
            $pa = collect($a->result->by_area)->firstWhere('area', $area);
            $pb = collect($b->result->by_area)->firstWhere('area', $area);
            $rows[] = ['area' => $area, 'before' => $pa['correct'] ?? null, 'after' => $pb['correct'] ?? null, 'delta' => $pa && $pb ? $pb['correct'] - $pa['correct'] : null];
        }
        $ea = $a->essay?->finalResult?->total;
        $eb = $b->essay?->finalResult?->total;
        $rows[] = ['area' => 'REDACAO', 'before' => $ea, 'after' => $eb, 'delta' => $ea !== null && $eb !== null ? $eb - $ea : null];

        return view('app.compare', compact('a', 'b', 'rows'));
    }
}
