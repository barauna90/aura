import { Injectable, Logger, OnModuleDestroy, OnModuleInit } from '@nestjs/common';
import { Queue, Worker } from 'bullmq';
import { env } from '../../config/env';
import { EssayEvaluationOrchestrator } from './essay-evaluation.orchestrator';

/**
 * Fila de correção (seção 59: "filas para IA").
 * Com REDIS_URL usa BullMQ; sem Redis, processa em background no próprio processo.
 */
@Injectable()
export class EssayQueueService implements OnModuleInit, OnModuleDestroy {
  private readonly logger = new Logger('EssayQueue');
  private queue?: Queue;
  private worker?: Worker;

  constructor(private orchestrator: EssayEvaluationOrchestrator) {}

  async onModuleInit() {
    if (!env.REDIS_URL) {
      this.logger.warn('REDIS_URL não definido — correções de redação rodarão in-process.');
      return;
    }
    const connection = { url: env.REDIS_URL } as unknown as ConstructorParameters<typeof Queue>[1] extends { connection: infer C } ? C : never;
    this.queue = new Queue('essay-evaluation', { connection });
    this.worker = new Worker(
      'essay-evaluation',
      async (job) => this.orchestrator.evaluate(job.data.essayId),
      { connection, concurrency: 2 },
    );
    this.worker.on('failed', (job, err) => this.logger.error(`Job ${job?.id} falhou: ${err.message}`));
  }

  async enqueue(essayId: string) {
    if (this.queue) {
      await this.queue.add('evaluate', { essayId }, { attempts: 3, backoff: { type: 'exponential', delay: 5000 }, removeOnComplete: 100 });
      return;
    }
    setImmediate(() => this.orchestrator.evaluate(essayId).catch((e) => this.logger.error(e.message)));
  }

  async onModuleDestroy() {
    await this.worker?.close();
    await this.queue?.close();
  }
}
