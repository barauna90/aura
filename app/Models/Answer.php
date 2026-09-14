<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
            'is_correct' => 'boolean',
        ];
    }

    public function sheet()
    {
        return $this->belongsTo(AnswerSheet::class, 'answer_sheet_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
