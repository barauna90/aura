<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamBooklet;
use App\Services\Exam\ExamEngineService;
use App\Support\Disclaimers;
use App\Support\Enem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer'], 'day' => ['nullable', 'integer', 'in:1,2'],
            'area' => ['nullable', Rule::in(Enem::AREAS)], 'application' => ['nullable', Rule::in(array_keys(Enem::APPLICATIONS))],
        ]);
        $exams = Exam::officialVisible()->with(['edition', 'source', 'booklets' => fn ($q) => $q->where('review_status', 'VERIFIED')])
            ->when($filters['year'] ?? null, fn ($q, $y) => $q->whereHas('edition', fn ($e) => $e->where('year', $y)))
            ->when($filters['day'] ?? null, fn ($q, $d) => $q->where('day', $d))
            ->when($filters['application'] ?? null, fn ($q, $a) => $q->where('application', $a))
            ->when($filters['area'] ?? null, fn ($q, $a) => $q->whereJsonContains('areas', $a))
            ->join('exam_editions', 'exams.exam_edition_id', '=', 'exam_editions.id')
            ->orderByDesc('exam_editions.year')->orderBy('application')->orderBy('day')->select('exams.*')->get();
        $years = Exam::officialVisible()->join('exam_editions', 'exams.exam_edition_id', '=', 'exam_editions.id')
            ->distinct()->orderByDesc('exam_editions.year')->pluck('exam_editions.year');

        return view('app.exams.index', compact('exams', 'years', 'filters'));
    }

    public function show(Exam $exam): View
    {
        abort_unless(Exam::officialVisible()->whereKey($exam->id)->exists(), 404, 'Prova oficial não encontrada ou ainda não publicada.');
        $exam->load(['edition', 'source', 'booklets' => fn ($q) => $q->where('review_status', 'VERIFIED')->withCount('questions'), 'essayPrompt']);

        return view('app.exams.show', ['exam' => $exam, 'structureNote' => $exam->structure_note ?: Disclaimers::HISTORICAL_STRUCTURE]);
    }

    public function start(Request $request, Exam $exam, ExamEngineService $engine): RedirectResponse
    {
        abort_unless(Exam::officialVisible()->whereKey($exam->id)->exists(), 404);
        $data = $request->validate([
            'booklet_id' => ['required', Rule::exists('exam_booklets', 'id')->where('exam_id', $exam->id)->where('review_status', 'VERIFIED')],
            'mode' => ['required', 'in:PROVA_REAL,ESTUDO'],
            'language' => ['nullable', Rule::in(array_keys(Enem::LANGUAGES))],
            'areas' => ['nullable', 'array'], 'areas.*' => [Rule::in(Enem::OBJECTIVE_AREAS)],
            'device' => ['nullable', 'in:mobile,desktop'],
        ]);
        $session = $engine->create($request->user(), $exam, ExamBooklet::findOrFail($data['booklet_id']), $data['mode'], $data['language'] ?? null, $data['mode'] === 'ESTUDO' ? ($data['areas'] ?? []) : [], $data['device'] ?? null);

        return redirect()->route('sessions.show', $session);
    }

    /** Entrega o PDF oficial inalterado (só cadernos verificados de provas publicadas). */
    public function pdf(ExamBooklet $booklet): StreamedResponse
    {
        abort_unless($booklet->review_status === 'VERIFIED' && Exam::officialVisible()->whereKey($booklet->exam_id)->exists(), 404);
        $disk = Storage::disk('official');
        abort_unless($disk->exists($booklet->pdf_path), 404);

        return $disk->response($booklet->pdf_path, Str::slug($booklet->label).'.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, max-age=86400',
            'ETag' => $booklet->pdf_checksum,
            'Content-Disposition' => 'inline; filename="caderno.pdf"',
        ]);
    }
}
