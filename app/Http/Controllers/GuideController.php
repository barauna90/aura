<?php

namespace App\Http\Controllers;

use App\Models\StudyTopic;
use App\Support\Disclaimers;
use App\Support\Enem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** GUIA ENEM: tópicos verificados por eixo; recorrência derivada de classificações VERIFIED. */
class GuideController extends Controller
{
    public function __invoke(Request $request): View
    {
        $area = $request->validate(['area' => ['nullable', Rule::in(Enem::AREAS)]])['area'] ?? null;
        $topics = StudyTopic::where('review_status', 'VERIFIED')
            ->when($area, fn ($q) => $q->where('area', $area))
            ->withCount(['classifications as verified_count' => fn ($q) => $q->where('review_status', 'VERIFIED')])
            ->with(['materials' => fn ($q) => $q->where('review_status', 'VERIFIED')->select('id', 'study_topic_id', 'title')])
            ->orderBy('area')->orderByDesc('recurrence')->get()->groupBy('area');

        return view('app.guide', ['topics' => $topics, 'area' => $area, 'recurrent' => Disclaimers::RECURRENT_CONTENT, 'matrix' => Disclaimers::MATRIX_SKILL]);
    }
}
