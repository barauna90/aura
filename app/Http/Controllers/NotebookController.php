<?php

namespace App\Http\Controllers;

use App\Models\ErrorNotebookEntry;
use App\Services\Study\ErrorNotebookService;
use App\Support\Enem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotebookController extends Controller
{
    public function __construct(private readonly ErrorNotebookService $notebook) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['area' => ['nullable', Rule::in(Enem::OBJECTIVE_AREAS)], 'year' => ['nullable', 'integer'], 'due' => ['nullable', 'boolean']]);
        $entries = $this->notebook->query($request->user(), $filters)->paginate(20)->withQueryString();

        return view('app.notebook.index', compact('entries', 'filters'));
    }

    public function note(Request $request, ErrorNotebookEntry $entry): RedirectResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 404);
        $entry->update($request->validate(['note' => ['nullable', 'string', 'max:2000']]));

        return back()->with('status', 'Anotação salva.');
    }

    public function review(Request $request, ErrorNotebookEntry $entry): RedirectResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 404);
        $data = $request->validate(['quality' => ['required', 'integer', 'min:0', 'max:5']]);
        $this->notebook->review($entry, (int) $data['quality']);

        return back();
    }
}
