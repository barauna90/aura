<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Link de vídeo de estudo colado por um aluno em um tema do Guia ENEM. */
class TopicVideo extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_recommended' => 'boolean'];
    }

    public function topic()
    {
        return $this->belongsTo(StudyTopic::class, 'study_topic_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** URL de incorporação (YouTube/Vimeo) ou null para links comuns. */
    public function embedUrl(): ?string
    {
        return match ($this->provider) {
            'YOUTUBE' => "https://www.youtube-nocookie.com/embed/{$this->video_id}",
            'VIMEO' => "https://player.vimeo.com/video/{$this->video_id}",
            default => null,
        };
    }

    /** Identifica o provedor e o id do vídeo a partir da URL colada. */
    public static function parse(string $url): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        if (str_contains($host, 'youtu.be')) {
            return ['provider' => 'YOUTUBE', 'video_id' => trim($path, '/')];
        }
        if (str_contains($host, 'youtube.com')) {
            if (! empty($q['v'])) {
                return ['provider' => 'YOUTUBE', 'video_id' => $q['v']];
            }
            if (preg_match('#/(?:shorts|embed|live)/([\w-]{6,})#', $path, $m)) {
                return ['provider' => 'YOUTUBE', 'video_id' => $m[1]];
            }
        }
        if (str_contains($host, 'vimeo.com') && preg_match('#/(\d{5,})#', $path, $m)) {
            return ['provider' => 'VIMEO', 'video_id' => $m[1]];
        }

        return ['provider' => 'LINK', 'video_id' => null];
    }
}
