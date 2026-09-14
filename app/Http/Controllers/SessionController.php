<?php

namespace App\Http\Controllers;

use App\Models\ExamSession;
use App\Services\Exam\ExamEngineService;
use App\Support\Disclaimers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function __construct(private readonly ExamEngineService $engine) {}

    public function show(Request $request, ExamSession $session): View|RedirectResponse
    {
        $this->own($request, $session);
        $session->load(['exam.edition', 'booklet']);
        $state = $this->engine->state($session);
        if ($state['finished']) {
            return redirect()->route('sessions.result', $session);
        }

        return view('app.sessions.runner', ['session' => $session->fresh(['exam', 'booklet']), 'state' => $state]);
    }

    public function start(Request $request, ExamSession $session): RedirectResponse
    {
        $this->own($request, $session);
        $this->engine->start($session);

        return redirect()->route('sessions.show', $session);
    }

    public function state(Request $request, ExamSession $session): JsonResponse
    {
        $this->own($request, $session);

        return response()->json($this->engine->state($session));
    }

    public function answers(Request $request, ExamSession $session): JsonResponse
    {
        $this->own($request, $session);
        $data = $request->validate([
            'answers' => ['required', 'array', 'max:200'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.option' => ['nullable', Rule::in(['A', 'B', 'C', 'D', 'E'])],
            'answers.*.time_spent_sec' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json($this->engine->saveAnswers($session, $data['answers']));
    }

    public function pause(Request $request, ExamSession $session): JsonResponse
    {
        $this->own($request, $session);
        $this->engine->pause($session);

        return response()->json($this->engine->state($session->fresh()));
    }

    public function resume(Request $request, ExamSession $session): JsonResponse
    {
        $this->own($request, $session);
        $this->engine->resume($session);

        return response()->json($this->engine->state($session->fresh()));
    }

    public function finish(Request $request, ExamSession $session): JsonResponse|RedirectResponse
    {
        $this->own($request, $session);
        $this->engine->finish($session);
        $url = route('sessions.result', $session);

        return $request->expectsJson() ? response()->json(['redirect' => $url]) : redirect($url);
    }

    public function result(Request $request, ExamSession $session): View
    {
        $this->own($request, $session);
        abort_unless($session->isFinished(), 403, 'O resultado só é exibido após o encerramento da prova.');
        $session->load(['exam.edition', 'exam.essayPrompt', 'result', 'essay.finalResult']);

        return view('app.sessions.result', [
            'session' => $session,
            'result' => $session->result,
            'questions' => $this->engine->questionReport($session),
            'scoreNotice' => Disclaimers::SCORE_ESTIMATE,
            'filter' => $request->query('filtro', 'todas'),
        ]);
    }

    private function own(Request $request, ExamSession $session): void
    {
        abort_unless($session->user_id === $request->user()->id, 404);
    }
}
