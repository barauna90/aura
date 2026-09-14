<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnswerSheet extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
        ];
    }

    public function session()
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }
}
