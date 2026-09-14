<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionNote extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'flagged' => 'boolean',
        ];
    }

    public function session()
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }
}
