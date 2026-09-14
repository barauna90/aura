<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EssayCompetencyScore extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'problematic_excerpts' => 'array',
        ];
    }

    public function evaluation()
    {
        return $this->belongsTo(EssayEvaluation::class, 'essay_evaluation_id');
    }
}
