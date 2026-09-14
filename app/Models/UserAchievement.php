<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAchievement extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'earned_at' => 'datetime',
        ];
    }

    public function achievement()
    {
        return $this->belongsTo(Achievement::class);
    }
}
