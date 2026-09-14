<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyTopic extends Model
{
    protected $guarded = [];

    public function classifications()
    {
        return $this->hasMany(QuestionClassification::class);
    }

    public function materials()
    {
        return $this->hasMany(StudyMaterial::class);
    }
}
