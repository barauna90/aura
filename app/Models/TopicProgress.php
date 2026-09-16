<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopicProgress extends Model
{
    public $timestamps = false;

    protected $table = 'topic_progress';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['studied_at' => 'datetime'];
    }
}
