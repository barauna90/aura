<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'selected_areas' => 'array',
            'started_at' => 'datetime',
            'expected_end_at' => 'datetime',
            'paused_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function booklet()
    {
        return $this->belongsTo(ExamBooklet::class, 'exam_booklet_id');
    }

    public function answerSheet()
    {
        return $this->hasOne(AnswerSheet::class);
    }

    public function result()
    {
        return $this->hasOne(SessionResult::class);
    }

    public function essay()
    {
        return $this->hasOne(Essay::class);
    }

    public function notes()
    {
        return $this->hasMany(SessionNote::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['FINISHED', 'EXPIRED'], true);
    }
}
