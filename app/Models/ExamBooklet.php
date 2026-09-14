<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamBooklet extends Model
{
    protected $guarded = [];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function source()
    {
        return $this->belongsTo(ContentSource::class, 'content_source_id');
    }

    public function pages()
    {
        return $this->hasMany(ExamPage::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function answerSets()
    {
        return $this->hasMany(OfficialAnswerSet::class);
    }
}
