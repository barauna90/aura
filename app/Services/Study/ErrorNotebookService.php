<?php

namespace App\Services\Study;

use App\Models\ErrorNotebookEntry;
use App\Models\User;
use App\Support\Disclaimers;
use Illuminate\Database\Eloquent\Builder;

/** MEU CADERNO DE ERROS com repetição espaçada (variação simplificada do SM-2). */
class ErrorNotebookService
{
    /** @return array{interval:int, ease:float} */
    public static function nextInterval(int $interval, float $ease, int $quality): array
    {
        $newEase = max(1.3, $ease + (0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02)));
        $newInterval = $quality < 3 ? 1 : ($interval <= 1 ? 3 : (int) round($interval * $newEase));

        return ['interval' => $newInterval, 'ease' => round($newEase, 2)];
    }

    public function addMany(int $userId, array $questionIds): void
    {
        if (! $questionIds) {
            return;
        }
        $rows = array_map(fn ($id) => [
            'user_id' => $userId, 'question_id' => $id, 'next_review_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
        ], $questionIds);
        ErrorNotebookEntry::insertOrIgnore($rows);
    }

    public function query(User $user, array $filters): Builder
    {
        return ErrorNotebookEntry::with(['question.booklet.exam.edition', 'question.officialAnswer', 'question.classification.topic', 'question.resolution'])
            ->where('user_id', $user->id)
            ->when(isset($filters['reviewed']), fn ($q) => $q->where('reviewed', (bool) $filters['reviewed']))
            ->when(! empty($filters['due']), fn ($q) => $q->where('next_review_at', '<=', now()))
            ->when(! empty($filters['area']), fn ($q) => $q->whereHas('question', fn ($x) => $x->where('area', $filters['area'])))
            ->when(! empty($filters['year']), fn ($q) => $q->whereHas('question.booklet.exam.edition', fn ($x) => $x->where('year', $filters['year'])))
            ->orderBy('next_review_at')->latest('id');
    }

    public function review(ErrorNotebookEntry $entry, int $quality): void
    {
        $next = self::nextInterval($entry->interval_days, (float) $entry->ease, $quality);
        $entry->update([
            'reviewed' => true,
            'interval_days' => $next['interval'],
            'ease' => $next['ease'],
            'next_review_at' => now()->addDays($next['interval']),
        ]);
    }

    public static function resolutionOf(ErrorNotebookEntry $entry): array
    {
        $r = $entry->question->resolution;

        return $r && $r->review_status === 'VERIFIED'
            ? ['body' => $r->body, 'notice' => null]
            : ['body' => null, 'notice' => Disclaimers::NO_RESOLUTION];
    }
}
