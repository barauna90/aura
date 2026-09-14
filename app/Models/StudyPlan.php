<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyPlan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'rationale' => 'array',
            'active' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tasks()
    {
        return $this->hasMany(StudyTask::class);
    }
}
