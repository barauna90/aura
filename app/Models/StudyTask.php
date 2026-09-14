<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyTask extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
            'payload' => 'array',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(StudyPlan::class, 'study_plan_id');
    }

    public function topic()
    {
        return $this->belongsTo(StudyTopic::class, 'study_topic_id');
    }
}
