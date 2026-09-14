<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Essay extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function session()
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function prompt()
    {
        return $this->belongsTo(EssayPrompt::class, 'essay_prompt_id');
    }

    public function evaluations()
    {
        return $this->hasMany(EssayEvaluation::class);
    }

    public function finalResult()
    {
        return $this->hasOne(EssayFinalResult::class);
    }
}
