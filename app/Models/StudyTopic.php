<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyTopic extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['subtopics' => 'array'];
    }

    public function videos()
    {
        return $this->hasMany(TopicVideo::class);
    }

    public function progress()
    {
        return $this->hasMany(TopicProgress::class);
    }

    public function classifications()
    {
        return $this->hasMany(QuestionClassification::class);
    }

    public function materials()
    {
        return $this->hasMany(StudyMaterial::class);
    }
}
