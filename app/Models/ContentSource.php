<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentSource extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'import_date' => 'datetime',
            'last_validation' => 'datetime',
        ];
    }
}
