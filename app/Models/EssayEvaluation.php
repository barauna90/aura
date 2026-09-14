<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EssayEvaluation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'zero_score' => 'boolean',
            'positives' => 'array',
            'improvements' => 'array',
            'raw_response' => 'array',
        ];
    }

    public function essay()
    {
        return $this->belongsTo(Essay::class);
    }

    public function competencies()
    {
        return $this->hasMany(EssayCompetencyScore::class);
    }
}
