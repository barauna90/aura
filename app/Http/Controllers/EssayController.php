<?php

namespace App\Http\Controllers;

use App\Models\Essay;
use App\Models\EssayPrompt;
use App\Models\ExamSession;
use App\Services\Essay\EssayService;
use App\Support\Disclaimers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EssayController extends Controller
{
    public function __construct(private readonly EssayService $essays) {}

    public function index(Request $request): View
    {
        $prompts = EssayPrompt::with('exam.edition')->where('review_status', 'VERIFIED')
            ->whereHas('exam', fn ($q) => $q->officialVisible())->get()->sortByDesc(fn ($p) => $p->exam->edition->year);
        $mine = Essay::with(['prompt.exam.edition', 'finalResult'])->where('user_id', $request->user()->id)->latest()->get();

        return view('app.essays.index', ['prompts' => $prompts, 'essays' => $mine, 'notice' => Disclaimers::ESSAY_EVALUATION]);
    }

    public function startFromPrompt(Request $request, EssayPrompt $prompt): RedirectResponse
    {
        $essay = $this->essays->openForPrompt($request->user(), $prompt);

        return redirect()->route('essays.edit', $essay);
    }

    public function startFromSession(Request $request, ExamSession $session): RedirectResponse
    {
        abort_unless($session->user_id === $request->user()->id, 404);
        $essay = $this->essays->openForSession($request->user(), $session);

        return redirect()->route($essay->status === 'DRAFT' ? 'essays.edit' : 'essays.report', $essay);
    }

    public function edit(Request $request, Essay $essay): View|RedirectResponse
    {
        $this->own($request, $essay);
        if ($essay->status !== 'DRAFT') {
            return redirect()->route('essays.report', $essay);
        }
        $essay->load(['prompt.exam.edition', 'prompt.source', 'session']);

        return view('app.essays.edit', ['essay' => $essay, 'notice' => Disclaimers::ESSAY_EVALUATION]);
    }

    public function draft(Request $request, Essay $essay): JsonResponse
    {
        $this->own($request, $essay);
        $data = $request->validate(['text' => ['present', 'string', 'max:20000']]);
        $this->essays->saveDraft($essay, $data['text']);

        return response()->json(['saved_at' => now()->toIso8601String(), 'lines' => $essay->fresh()->line_count]);
    }

    public function submit(Request $request, Essay $essay): RedirectResponse
    {
        $this->own($request, $essay);
        if ($request->filled('text')) {
            $this->essays->saveDraft($essay, (string) $request->input('text'));
        }
        $this->essays->submit($essay->fresh());

        return redirect()->route('essays.report', $essay)->with('status', 'Redação enviada. A avaliação simulada leva alguns instantes.');
    }

    public function report(Request $request, Essay $essay): View
    {
        $this->own($request, $essay);
        $essay->load(['prompt.exam.edition', 'finalResult', 'evaluations.competencies']);

        return view('app.essays.report', ['essay' => $essay, 'report' => $this->essays->report($essay)]);
    }

    private function own(Request $request, Essay $essay): void
    {
        abort_unless($essay->user_id === $request->user()->id, 404);
    }
}
