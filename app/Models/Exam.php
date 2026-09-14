<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'areas' => 'array',
            'has_essay' => 'boolean',
            'has_foreign_language' => 'boolean',
            'is_free_sample' => 'boolean',
        ];
    }

    public function edition()
    {
        return $this->belongsTo(ExamEdition::class, 'exam_edition_id');
    }

    public function source()
    {
        return $this->belongsTo(ContentSource::class, 'content_source_id');
    }

    public function booklets()
    {
        return $this->hasMany(ExamBooklet::class);
    }

    public function essayPrompt()
    {
        return $this->hasOne(EssayPrompt::class);
    }

    public function sessions()
    {
        return $this->hasMany(ExamSession::class);
    }

    /** Só VERIFIED + PUBLISHED com fonte OFFICIAL_INEP verificada aparece como prova oficial. */
    public function scopeOfficialVisible(Builder $q): Builder
    {
        return $q->where('review_status', 'VERIFIED')
            ->where('pipeline_stage', 'PUBLISHED')
            ->whereHas('source', fn (Builder $s) => $s->where('source_type', 'OFFICIAL_INEP')->where('review_status', 'VERIFIED'));
    }
}
