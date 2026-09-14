<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EssayZeroRule extends Model
{
    protected $guarded = [];

    public function edition()
    {
        return $this->belongsTo(ExamEdition::class, 'exam_edition_id');
    }
}
