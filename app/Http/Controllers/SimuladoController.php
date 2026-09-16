<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Services\Billing\AccessService;
use App\Services\Exam\ExamEngineService;
use App\Support\Disclaimers;
use App\Support\Enem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * SIMULADOS: montados exclusivamente com as provas oficiais publicadas
 * (por área no Modo Estudo, prova completa no Modo Prova Real, maratona
 * 1º + 2º dia e a rotina do Modo Intensivo). Nada aqui é questão inventada.
 */
class SimuladoController extends Controller
{
    /** Rotina semanal do Modo Intensivo ENEM: [dia, área|null (=prova completa), modo]. */
    public const INTENSIVE_WEEK = [
        ['Segunda', 'LINGUAGENS', 'ESTUDO'],
        ['Terça', 'HUMANAS', 'ESTUDO'],
        ['Quarta', 'NATUREZA', 'ESTUDO'],
        ['Quinta', 'MATEMATICA', 'ESTUDO'],
        ['Sexta', 'REDACAO', 'ESTUDO'],
        ['Sábado', null, 'PROVA_REAL'],
    ];

    public function __construct(private readonly AccessService $access, private readonly ExamEngineService $engine) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $exams = Exam::officialVisible()->with(['edition', 'booklets' => fn ($q) => $q->where('review_status', 'VERIFIED')])
            ->join('exam_editions', 'exams.exam_edition_id', '=', 'exam_editions.id')
            ->orderByDesc('exam_editions.year')->orderBy('day')->select('exams.*')->get();
        $sessions = ExamSession::with(['exam.edition', 'result'])->where('user_id', $user->id)->latest()->get();
        $finished = $sessions->filter(fn ($s) => $s->isFinished() && $s->result);

        // Por área: quantas vezes treinou e média de acertos.
        $byArea = [];
        foreach (Enem::OBJECTIVE_AREAS as $area) {
            $list = $finished->filter(fn ($s) => collect($s->result->by_area)->firstWhere('area', $area));
            $stats = $list->map(fn ($s) => collect($s->result->by_area)->firstWhere('area', $area));
            $byArea[$area] = ['sessions' => $list->count(), 'percent' => $stats->isNotEmpty() ? round($stats->avg('percent'), 1) : null];
        }

        // Maratona: para cada edição, 1º e 2º dia feitos no Modo Prova Real.
        $marathon = $exams->groupBy(fn ($e) => $e->edition->year)->map(function ($group) use ($finished) {
            return ['exams' => $group->keyBy('day'), 'done' => $group->mapWithKeys(fn ($e) => [$e->day => $finished->first(fn ($s) => $s->exam_id === $e->id && $s->mode === 'PROVA_REAL')])];
        });

        // Modo Intensivo: rotina desta semana marcada com o que já foi feito.
        $limits = $this->access->resolve($user)['limits'];
        $weekStart = now()->startOfWeek();
        $week = collect(self::INTENSIVE_WEEK)->map(function ($slot) use ($sessions, $weekStart, $user) {
            [$day, $area, $mode] = $slot;
            if ($area === 'REDACAO') {
                $done = $user->essays()->whereNotNull('submitted_at')->where('submitted_at', '>=', $weekStart)->exists();
            } else {
                $done = $sessions->contains(fn ($s) => $s->created_at >= $weekStart && $s->mode === $mode && ($area === null || in_array($area, $s->selected_areas ?? [], true)));
            }

            return ['day' => $day, 'area' => $area, 'mode' => $mode, 'done' => $done];
        });

        return view('app.simulados', [
            'exams' => $exams, 'years' => $exams->pluck('edition.year')->unique()->values(),
            'sessions' => $sessions->take(12), 'byArea' => $byArea, 'marathon' => $marathon,
            'intensive' => ! empty($limits['intensive']), 'week' => $week,
            'canFullExam' => $limits['fullExamsPerMonth'] !== 0,
            'studyNotice' => Disclaimers::STUDY_MODE_NOTICE,
        ]);
    }

    /** Simulado por área: prova oficial da edição escolhida, só as questões da área, no Modo Estudo. */
    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'area' => ['required', Rule::in(Enem::OBJECTIVE_AREAS)],
            'year' => ['required', 'integer'],
            'language' => ['nullable', Rule::in(array_keys(Enem::LANGUAGES))],
        ]);
        $exam = Exam::officialVisible()->whereHas('edition', fn ($q) => $q->where('year', $data['year']))
            ->whereJsonContains('areas', $data['area'])->with(['booklets' => fn ($q) => $q->where('review_status', 'VERIFIED')])->first();
        abort_unless($exam && $exam->booklets->isNotEmpty(), 404, 'Nenhuma prova oficial publicada para essa edição e área.');
        $language = $exam->has_foreign_language ? ($data['language'] ?? 'INGLES') : null;
        $session = $this->engine->create($request->user(), $exam, $exam->booklets->first(), 'ESTUDO', $language, [$data['area']], null);

        return redirect()->route('sessions.show', $session);
    }
}
