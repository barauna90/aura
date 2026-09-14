<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficialAnswer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'annulled' => 'boolean',
        ];
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function answerSet()
    {
        return $this->belongsTo(OfficialAnswerSet::class, 'official_answer_set_id');
    }
}
