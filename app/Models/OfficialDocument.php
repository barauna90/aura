<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficialDocument extends Model
{
    protected $guarded = [];

    public function source()
    {
        return $this->belongsTo(ContentSource::class, 'content_source_id');
    }

    public function chunks()
    {
        return $this->hasMany(OfficialDocumentChunk::class);
    }
}
