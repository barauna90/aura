<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorNotebookEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reviewed' => 'boolean',
            'next_review_at' => 'datetime',
            'ease' => 'float',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
