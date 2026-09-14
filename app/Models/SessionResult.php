<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionResult extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'by_area' => 'array',
            'by_discipline' => 'array',
            'percent' => 'float',
            'avg_seconds_per_question' => 'float',
        ];
    }

    public function session()
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }
}
