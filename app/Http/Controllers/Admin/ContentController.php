<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentVersion;
use App\Models\Exam;
use App\Models\ExamBooklet;
use App\Models\Question;
use App\Services\Content\ContentImportService;
use App\Services\Content\GuardianRules;
use App\Services\Content\GuardianService;
use App\Support\Enem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** CMS de conteúdo oficial: importação + fluxo de auditoria em cinco etapas. */
class ContentController extends Controller
{
    public function __construct(private readonly ContentImportService $importer, private readonly GuardianService $guardian) {}

    public function index(): View
    {
        $exams = Exam::with(['edition', 'source'])->withCount('booklets')
            ->join('exam_editions', 'exams.exam_edition_id', '=', 'exam_editions.id')
            ->orderByDesc('exam_editions.year')->orderBy('day')->select('exams.*')->get();

        return view('admin.content.index', compact('exams'));
    }

    public function show(Exam $exam): View
    {
        $exam->load(['edition.zeroRules', 'source', 'booklets.answerSets', 'booklets' => fn ($q) => $q->withCount('questions'), 'essayPrompt']);

        return view('admin.content.show', [
            'exam' => $exam,
            'versions' => ContentVersion::with('author')->where('entity_type', 'Exam')->where('entity_id', $exam->id)->latest()->get(),
            'nextStage' => GuardianRules::nextStage($exam->pipeline_stage),
        ]);
    }

    public function validate(Exam $exam): JsonResponse
    {
        $structural = $this->guardian->autoValidate($exam);
        $integrity = $this->guardian->verifyIntegrity($exam);

        return response()->json(['ok' => ! $structural && ! $integrity, 'structural' => $structural, 'integrity' => $integrity]);
    }

    public function advance(Request $request, Exam $exam): RedirectResponse
    {
        $data = $request->validate(['target' => ['required', Rule::in(Enem::PIPELINE_STAGES)]]);
        if ($data['target'] === 'PUBLISHED' && ! $request->user()->hasRole('ADMIN')) {
            abort(403, 'Publicação exige perfil ADMIN.');
        }
        $this->guardian->advanceExam($exam, $data['target'], $request->user()->id);

        return back()->with('status', 'Etapa avançada para '.Enem::STAGE_LABEL[$data['target']].'.');
    }

    public function reject(Request $request, Exam $exam): RedirectResponse
    {
        $this->guardian->rejectExam($exam, $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'], $request->user()->id);

        return back()->with('status', 'Prova rejeitada.');
    }

    public function storeExam(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:1998', 'max:2100'],
            'application' => ['required', Rule::in(array_keys(Enem::APPLICATIONS))],
            'day' => ['required', 'integer', 'in:1,2'],
            'title' => ['required', 'string', 'max:160'],
            'duration_minutes' => ['required', 'integer', 'min:30', 'max:600'],
            'areas' => ['required', 'array', 'min:1'], 'areas.*' => [Rule::in(Enem::AREAS)],
            'structure_note' => ['nullable', 'string', 'max:500'],
            'source_url' => ['required', 'url', 'max:500'],
            'document_version' => ['required', 'string', 'max:60'],
            'is_free_sample' => ['nullable', 'boolean'],
            'source_pdf' => ['nullable', 'file', 'mimetypes:application/pdf', 'max:61440'],
        ]);
        $exam = $this->importer->createExam($data, $request->file('source_pdf'), $request->user()->id);

        return redirect()->route('admin.content.show', $exam)->with('status', 'Prova importada como PENDING. Adicione cadernos e gabaritos.');
    }

    public function storeBooklet(Request $request, Exam $exam): RedirectResponse
    {
        $data = $request->validate([
            'color' => ['required', Rule::in(Enem::BOOKLET_COLORS)],
            'label' => ['required', 'string', 'max:80'],
            'page_count' => ['required', 'integer', 'min:1', 'max:400'],
            'source_url' => ['required', 'url', 'max:500'],
            'document_version' => ['required', 'string', 'max:60'],
            'pdf' => ['required', 'file', 'mimetypes:application/pdf', 'max:61440'],
        ]);
        $this->importer->addBooklet($exam, $data, $request->file('pdf'), $request->user()->id);

        return back()->with('status', 'Caderno importado com checksum.');
    }

    /** Linhas: numero;area;letra (X = anulada);idioma (opcional);pagina (opcional) */
    public function storeAnswerKey(Request $request, ExamBooklet $booklet): RedirectResponse
    {
        $data = $request->validate([
            'source_url' => ['required', 'url', 'max:500'],
            'document_version' => ['required', 'string', 'max:60'],
            'answers_csv' => ['required', 'string'],
            'gabarito_pdf' => ['nullable', 'file', 'mimetypes:application/pdf', 'max:61440'],
        ]);
        $answers = [];
        foreach (preg_split('/\r?\n/', trim($data['answers_csv'])) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = array_map('trim', explode(';', $line));
            [$number, $area, $letter] = [$parts[0] ?? null, strtoupper($parts[1] ?? ''), strtoupper($parts[2] ?? '')];
            if (! is_numeric($number) || ! in_array($area, Enem::AREAS, true)) {
                return back()->withErrors(['answers_csv' => "Linha inválida: {$line}"])->withInput();
            }
            $answers[] = [
                'number' => (int) $number, 'area' => $area, 'correct' => $letter === 'X' ? null : $letter, 'annulled' => $letter === 'X',
                'foreign_language' => ($parts[3] ?? '') !== '' ? strtoupper($parts[3]) : null, 'page' => ($parts[4] ?? '') !== '' ? (int) $parts[4] : null,
            ];
        }
        $set = $this->importer->registerAnswerKey($booklet, $data, $answers, $request->file('gabarito_pdf'), $request->user()->id);

        return back()->with('status', count($answers).' questões registradas. Checksum do gabarito: '.substr($set->checksum, 0, 12).'…');
    }

    public function storeEssayPrompt(Request $request, Exam $exam): RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['required', 'string', 'max:300'],
            'texts' => ['required', 'string'],
            'source_url' => ['required', 'url', 'max:500'],
            'document_version' => ['required', 'string', 'max:60'],
            'max_lines' => ['nullable', 'integer', 'min:10', 'max:60'],
        ]);
        $texts = array_values(array_filter(array_map('trim', preg_split('/\n---\n/', $data['texts']))));
        $data['motivating_texts'] = array_map(fn ($i, $body) => ['title' => 'Texto '.($i + 1), 'body' => $body], array_keys($texts), $texts);
        $this->importer->registerEssayPrompt($exam, $data, $request->user()->id);

        return back()->with('status', 'Proposta de redação registrada (PENDING).');
    }

    public function storeZeroRules(Request $request, int $year): RedirectResponse
    {
        $data = $request->validate(['rules' => ['required', 'array'], 'rules.*.code' => ['required', 'string', 'max:40'], 'rules.*.description' => ['required', 'string', 'max:400'], 'rules.*.source_url' => ['required', 'url'], 'verified' => ['nullable', 'boolean']]);
        $this->importer->registerZeroRules($year, $data['rules'], $request->user()->id, $request->boolean('verified'));

        return back()->with('status', 'Regras de nota zero registradas para '.$year.'.');
    }

    public function updateExam(Request $request, Exam $exam): RedirectResponse
    {
        $data = $request->validate(['duration_minutes' => ['nullable', 'integer', 'min:30', 'max:600'], 'title' => ['nullable', 'string', 'max:160'], 'structure_note' => ['nullable', 'string', 'max:500'], 'is_free_sample' => ['nullable', 'boolean'], 'reason' => ['required', 'string', 'max:500']]);
        $reason = $data['reason'];
        unset($data['reason']);
        $this->guardian->changeExamMetadata($exam, array_filter($data, fn ($v) => $v !== null), $reason, $request->user()->id);

        return back()->with('status', 'Prova alterada e devolvida para auditoria (nova versão registrada).');
    }

    public function updateAnswer(Request $request, Question $question): RedirectResponse
    {
        $data = $request->validate(['correct' => ['nullable', Rule::in(Enem::OPTIONS)], 'annulled' => ['nullable', 'boolean'], 'reason' => ['required', 'string', 'max:500']]);
        $this->guardian->changeOfficialAnswer($question, $data['correct'] ?? null, $request->boolean('annulled'), $data['reason'], $request->user()->id);

        return back()->with('status', 'Gabarito alterado com versão registrada; a prova voltou para auditoria.');
    }
}
