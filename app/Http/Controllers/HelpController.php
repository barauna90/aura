<?php

namespace App\Http\Controllers;

use App\Models\Essay;
use App\Models\ExamSession;
use App\Services\SettingsService;
use App\Services\TutorService;
use App\Support\Disclaimers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function index(Request $request, SettingsService $settings): View
    {
        return view('app.help', [
            'independence' => Disclaimers::INDEPENDENCE,
            'scoreNotice' => Disclaimers::SCORE_ESTIMATE,
            'supportEmail' => $settings->get('site.support_email'),
            'answer' => session('tutor_answer'),
            'sessions' => ExamSession::with('exam')->where('user_id', $request->user()->id)->whereIn('status', ['FINISHED', 'EXPIRED'])->latest()->limit(10)->get(),
        ]);
    }

    public function ask(Request $request, TutorService $tutor): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:5', 'max:1000'],
            'session_id' => ['nullable', 'integer'],
            'essay_id' => ['nullable', 'integer'],
        ]);
        $session = ! empty($data['session_id']) ? ExamSession::with(['result', 'answerSheet'])->find($data['session_id']) : null;
        $essay = ! empty($data['essay_id']) ? Essay::with(['finalResult', 'evaluations'])->find($data['essay_id']) : null;
        $out = $tutor->ask($request->user(), $data['question'], $session, $essay);

        return back()->with('tutor_answer', $out)->withInput();
    }
}
