<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionClassification extends Model
{
    protected $guarded = [];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function topic()
    {
        return $this->belongsTo(StudyTopic::class, 'study_topic_id');
    }
}
