<?php

namespace App\Jobs;

use App\Models\Essay;
use App\Services\Essay\EssayEvaluationOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Fila de correção de redação (QUEUE_CONNECTION=database ou redis). */
class EvaluateEssay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 600;

    public function __construct(public readonly Essay $essay) {}

    public function handle(EssayEvaluationOrchestrator $orchestrator): void
    {
        $orchestrator->evaluate($this->essay);
    }
}
