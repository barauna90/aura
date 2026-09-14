import { Global, Inject, Injectable, Logger, Module } from '@nestjs/common';
import { env } from '../../config/env';
import { PrismaService } from '../../prisma/prisma.service';
import { AI_PROVIDER, AiCompletionInput, AiCompletionOutput, AiProvider } from './ai-provider.interface';
import { MockAiProvider } from './providers/mock.provider';
import { AnthropicAiProvider } from './providers/anthropic.provider';

export function buildAiProvider(): AiProvider {
  switch (env.AI_PROVIDER) {
    case 'anthropic':
      if (!env.ANTHROPIC_API_KEY) throw new Error('ANTHROPIC_API_KEY é obrigatório com AI_PROVIDER=anthropic');
      return new AnthropicAiProvider(env.ANTHROPIC_API_KEY, env.AI_ESSAY_MODEL);
    case 'mock':
      return new MockAiProvider();
    default:
      throw new Error(`AI_PROVIDER desconhecido: ${env.AI_PROVIDER}`);
  }
}

/** Fachada com registro de uso/custo (observabilidade — seção 45). */
@Injectable()
export class AiService {
  private readonly logger = new Logger('AI');

  constructor(
    @Inject(AI_PROVIDER) private provider: AiProvider,
    private prisma: PrismaService,
  ) {}

  get providerName() {
    return this.provider.name;
  }
  get model() {
    return this.provider.model;
  }

  async complete(input: AiCompletionInput): Promise<AiCompletionOutput> {
    const started = Date.now();
    try {
      const out = await this.provider.complete(input);
      await this.record(input.purpose, out, true);
      return out;
    } catch (e) {
      await this.record(input.purpose, { provider: this.provider.name, model: this.provider.model, inputTokens: 0, outputTokens: 0, latencyMs: Date.now() - started, text: '' }, false, (e as Error).message);
      throw e;
    }
  }

  private record(purpose: AiCompletionInput['purpose'], out: AiCompletionOutput, success: boolean, error?: string) {
    return this.prisma.aiUsage
      .create({
        data: {
          provider: out.provider,
          model: out.model,
          purpose,
          inputTokens: Math.round(out.inputTokens),
          outputTokens: Math.round(out.outputTokens),
          latencyMs: out.latencyMs,
          success,
          error,
        },
      })
      .catch((e) => this.logger.error(`Falha ao registrar uso de IA: ${e.message}`));
  }
}

@Global()
@Module({
  providers: [{ provide: AI_PROVIDER, useFactory: buildAiProvider }, AiService],
  exports: [AiService, AI_PROVIDER],
})
export class AiModule {}
