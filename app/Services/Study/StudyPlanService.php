<?php

namespace App\Services\Study;

use App\Models\ErrorNotebookEntry;
use App\Models\StudyPlan;
use App\Models\StudyTopic;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\Billing\AccessService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class StudyPlanService
{
    public function __construct(private readonly AccessService $access, private readonly AnalyticsService $analytics) {}

    public function current(User $user): ?StudyPlan
    {
        return StudyPlan::with(['tasks' => fn ($q) => $q->orderBy('scheduled_on'), 'tasks.topic'])->where('user_id', $user->id)->where('active', true)->latest()->first();
    }

    public function generate(User $user, string $kind, ?int $weeklyHours, ?int $daysLeft): StudyPlan
    {
        $this->access->assertFeature($user, 'studyPlan');
        $weeklyHours = max(1, min(80, $weeklyHours ?? $user->weekly_hours ?? 10));
        $targetDate = $daysLeft ? CarbonImmutable::now()->addDays($daysLeft) : ($user->target_exam_date ? CarbonImmutable::instance($user->target_exam_date) : null);

        $perf = $this->analytics->overview($user, 'ALL');
        $due = ErrorNotebookEntry::where('user_id', $user->id)->where('next_review_at', '<=', now())->count();
        $weakTopics = $this->analytics->weakTopics($user, 6);

        $input = [
            'kind' => $kind, 'start_date' => CarbonImmutable::now(), 'target_date' => $targetDate, 'weekly_hours' => $weeklyHours,
            'areas' => $perf['by_area'], 'essay_weak_competencies' => $perf['essay']['weak_competencies'],
            'error_notebook_due' => $due, 'weak_topics' => $weakTopics,
        ];
        $tasks = StudyPlanRules::buildPlan($input);
        $topicIds = StudyTopic::whereIn('slug', array_filter(array_column($tasks, 'topic_slug')))->pluck('id', 'slug');

        return DB::transaction(function () use ($user, $kind, $targetDate, $weeklyHours, $daysLeft, $input, $tasks, $topicIds, $due, $weakTopics) {
            StudyPlan::where('user_id', $user->id)->where('active', true)->update(['active' => false]);
            $plan = StudyPlan::create([
                'user_id' => $user->id, 'kind' => $kind, 'target_date' => $targetDate, 'weekly_hours' => $weeklyHours,
                'days_left' => $daysLeft ?? ($targetDate ? (int) now()->diffInDays($targetDate) : null),
                'rationale' => ['prioritized_areas' => $input['areas'], 'weak_topics' => $weakTopics, 'essay_weak_competencies' => $input['essay_weak_competencies'], 'error_notebook_due' => $due],
            ]);
            $plan->tasks()->createMany(array_map(fn ($t) => [
                'scheduled_on' => $t['scheduled_on']->toDateString(), 'kind' => $t['kind'], 'title' => $t['title'], 'minutes' => $t['minutes'],
                'study_topic_id' => $t['topic_slug'] ? ($topicIds[$t['topic_slug']] ?? null) : null, 'payload' => $t['payload'],
            ], $tasks));

            return $plan;
        });
    }
}
