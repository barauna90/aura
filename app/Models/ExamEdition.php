<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamEdition extends Model
{
    protected $guarded = [];

    public function exams()
    {
        return $this->hasMany(Exam::class);
    }

    public function zeroRules()
    {
        return $this->hasMany(EssayZeroRule::class);
    }
}
