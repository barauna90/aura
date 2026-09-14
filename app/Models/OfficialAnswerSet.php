<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficialAnswerSet extends Model
{
    protected $guarded = [];

    public function booklet()
    {
        return $this->belongsTo(ExamBooklet::class, 'exam_booklet_id');
    }

    public function source()
    {
        return $this->belongsTo(ContentSource::class, 'content_source_id');
    }

    public function answers()
    {
        return $this->hasMany(OfficialAnswer::class);
    }
}
