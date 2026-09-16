<?php

namespace App\Http\Controllers;

use App\Models\ErrorNotebookEntry;
use App\Models\StudyTopic;
use App\Models\TopicProgress;
use App\Models\TopicVideo;
use App\Support\Disclaimers;
use App\Support\Enem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * GUIA ENEM: temas verificados por eixo e disciplina, com o que estudar em cada um,
 * recorrência derivada de classificações VERIFIED, progresso do aluno e vídeos de
 * estudo colados pelo próprio aluno (ou recomendados pela equipe).
 */
class GuideController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $area = $request->validate(['area' => ['nullable', Rule::in(Enem::AREAS)]])['area'] ?? null;
        $q = trim((string) $request->query('q', ''));
        $topics = StudyTopic::where('review_status', 'VERIFIED')
            ->when($area, fn ($b) => $b->where('area', $area))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('discipline', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%")))
            ->withCount([
                'classifications as verified_count' => fn ($b) => $b->where('review_status', 'VERIFIED'),
                'videos as my_videos_count' => fn ($b) => $b->where('user_id', $user->id)->orWhere('is_recommended', true),
            ])
            ->orderBy('area')->orderBy('discipline')->orderByDesc('recurrence')->orderBy('name')->get();
        $studied = TopicProgress::where('user_id', $user->id)->pluck('study_topic_id')->flip();
        $total = StudyTopic::where('review_status', 'VERIFIED')->count();

        return view('app.guide', [
            'topics' => $topics->groupBy('area')->map(fn ($list) => $list->groupBy('discipline')),
            'area' => $area, 'q' => $q, 'studied' => $studied,
            'progress' => ['done' => $studied->count(), 'total' => $total, 'percent' => $total ? round($studied->count() / $total * 100) : 0],
            'recurrent' => Disclaimers::RECURRENT_CONTENT, 'matrix' => Disclaimers::MATRIX_SKILL,
        ]);
    }

    public function show(Request $request, StudyTopic $topic): View
    {
        abort_unless($topic->review_status === 'VERIFIED', 404);
        $user = $request->user();
        $topic->loadCount(['classifications as verified_count' => fn ($b) => $b->where('review_status', 'VERIFIED')])
            ->load(['materials' => fn ($b) => $b->where('review_status', 'VERIFIED')]);
        $videos = TopicVideo::with('user')->where('study_topic_id', $topic->id)
            ->where(fn ($b) => $b->where('user_id', $user->id)->orWhere('is_recommended', true))
            ->orderByDesc('is_recommended')->latest()->get();
        $siblings = StudyTopic::where('review_status', 'VERIFIED')->where('discipline', $topic->discipline)->where('id', '!=', $topic->id)->orderBy('name')->get(['id', 'name', 'slug']);
        $errors = ErrorNotebookEntry::where('user_id', $user->id)
            ->whereHas('question.classification', fn ($b) => $b->where('study_topic_id', $topic->id))->count();

        return view('app.guide-topic', [
            'topic' => $topic, 'videos' => $videos, 'siblings' => $siblings, 'myErrors' => $errors,
            'studied' => TopicProgress::where('user_id', $user->id)->where('study_topic_id', $topic->id)->exists(),
            'isStaff' => $user->isStaff(),
            'recurrent' => Disclaimers::RECURRENT_CONTENT, 'matrix' => Disclaimers::MATRIX_SKILL,
        ]);
    }

    /** Aluno cola um link de vídeo (YouTube, Vimeo ou qualquer URL) no tema. */
    public function storeVideo(Request $request, StudyTopic $topic): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:500', 'regex:#^https?://#i'],
            'title' => ['nullable', 'string', 'max:160'],
            'recommended' => ['nullable', 'boolean'],
        ]);
        $parsed = TopicVideo::parse($data['url']);
        $title = trim((string) ($data['title'] ?? '')) ?: ($parsed['provider'] === 'LINK' ? parse_url($data['url'], PHP_URL_HOST) : 'Vídeo de estudo — '.$topic->name);
        TopicVideo::create([
            'study_topic_id' => $topic->id, 'user_id' => $request->user()->id, 'title' => $title, 'url' => $data['url'],
            'provider' => $parsed['provider'], 'video_id' => $parsed['video_id'],
            'is_recommended' => $request->user()->isStaff() && $request->boolean('recommended'),
        ]);

        return back()->with('status', 'Vídeo adicionado ao tema.');
    }

    public function destroyVideo(Request $request, TopicVideo $video): RedirectResponse
    {
        abort_unless($video->user_id === $request->user()->id || $request->user()->isStaff(), 403);
        $video->delete();

        return back()->with('status', 'Vídeo removido.');
    }

    public function toggleStudied(Request $request, StudyTopic $topic): RedirectResponse
    {
        $existing = TopicProgress::where('user_id', $request->user()->id)->where('study_topic_id', $topic->id)->first();
        $existing ? $existing->delete() : TopicProgress::create(['user_id' => $request->user()->id, 'study_topic_id' => $topic->id, 'studied_at' => now()]);

        return back()->with('status', $existing ? 'Tema desmarcado.' : 'Tema marcado como estudado.');
    }
}
