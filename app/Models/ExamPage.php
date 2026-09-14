<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamPage extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public function booklet()
    {
        return $this->belongsTo(ExamBooklet::class, 'exam_booklet_id');
    }
}
