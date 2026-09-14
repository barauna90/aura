<?php

namespace App\Services\Ai;

/**
 * Provider abstrato de IA. A IA NUNCA recebe permissão de escrita em conteúdo oficial.
 *
 * @phpstan-type Completion array{text:string, provider:string, model:string, input_tokens:int, output_tokens:int, latency_ms:int}
 */
interface AiProvider
{
    public function name(): string;

    public function model(): string;

    /**
     * @param  'ESSAY_EVAL'|'STUDY_PLAN'|'TUTOR'|'RAG'  $purpose
     * @return array{text:string, provider:string, model:string, input_tokens:int, output_tokens:int, latency_ms:int}
     */
    public function complete(string $purpose, string $system, string $user, ?string $variant = null, int $maxTokens = 8000): array;
}
