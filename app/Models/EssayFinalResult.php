<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EssayFinalResult extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'competency_scores' => 'array',
            'used_third_evaluator' => 'boolean',
            'consistency_flags' => 'array',
            'checklist' => 'array',
            'study_recommendation' => 'array',
        ];
    }

    public function essay()
    {
        return $this->belongsTo(Essay::class);
    }
}
