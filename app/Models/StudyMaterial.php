<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyMaterial extends Model
{
    protected $guarded = [];

    public function topic()
    {
        return $this->belongsTo(StudyTopic::class, 'study_topic_id');
    }
}
