<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $guarded = [];

    public function booklet()
    {
        return $this->belongsTo(ExamBooklet::class, 'exam_booklet_id');
    }

    public function options()
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function officialAnswer()
    {
        return $this->hasOne(OfficialAnswer::class);
    }

    public function classification()
    {
        return $this->hasOne(QuestionClassification::class);
    }

    public function resolution()
    {
        return $this->hasOne(QuestionResolution::class);
    }
}
