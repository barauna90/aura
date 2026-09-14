<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EssayPrompt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'motivating_texts' => 'array',
        ];
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function source()
    {
        return $this->belongsTo(ContentSource::class, 'content_source_id');
    }

    public function essays()
    {
        return $this->hasMany(Essay::class);
    }
}
