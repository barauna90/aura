import Anthropic from '@anthropic-ai/sdk';
import { AiCompletionInput, AiCompletionOutput, AiProvider } from '../ai-provider.interface';

/**
 * Provider Anthropic (Claude). Usa o SDK oficial com adaptive thinking.
 * Inclui fallback server-side por padrão (o modelo pode recusar por política;
 * o fallback roteia automaticamente para outro modelo dentro da mesma chamada).
 */
export class AnthropicAiProvider implements AiProvider {
  readonly name = 'anthropic';
  private client: Anthropic;

  constructor(
    apiKey: string,
    readonly model: string,
  ) {
    this.client = new Anthropic({ apiKey });
  }

  async complete(input: AiCompletionInput): Promise<AiCompletionOutput> {
    const started = Date.now();
    const response = await this.client.beta.messages.create({
      model: this.model,
      max_tokens: input.maxTokens ?? 16000,
      betas: ['server-side-fallback-2026-07-01'],
      fallbacks: 'default',
      thinking: { type: 'adaptive' },
      output_config: { effort: 'high' },
      system: [{ type: 'text', text: input.system, cache_control: { type: 'ephemeral' } }],
      messages: [{ role: 'user', content: input.user }],
    } as unknown as Anthropic.Beta.Messages.MessageCreateParamsNonStreaming);

    if (response.stop_reason === 'refusal') {
      throw new Error('O modelo recusou a solicitação');
    }
    const text = response.content
      .filter((b): b is Anthropic.Beta.BetaTextBlock => b.type === 'text')
      .map((b) => b.text)
      .join('\n');

    return {
      text,
      provider: this.name,
      model: response.model,
      inputTokens: response.usage.input_tokens,
      outputTokens: response.usage.output_tokens,
      latencyMs: Date.now() - started,
    };
  }
}
