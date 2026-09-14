<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficialDocumentChunk extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public function document()
    {
        return $this->belongsTo(OfficialDocument::class, 'official_document_id');
    }
}
