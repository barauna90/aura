import { createHash } from 'crypto';
import { AiCompletionInput, AiCompletionOutput, AiProvider } from '../ai-provider.interface';

/**
 * Provider simulado — para desenvolvimento e testes automatizados.
 * Produz uma avaliação determinística baseada em heurísticas superficiais do
 * texto (tamanho, parágrafos, presença de proposta). NÃO usar em produção.
 */
export class MockAiProvider implements AiProvider {
  readonly name = 'mock';
  readonly model = 'mock-evaluator';

  async complete(input: AiCompletionInput): Promise<AiCompletionOutput> {
    const started = Date.now();
    let text: string;
    switch (input.purpose) {
      case 'ESSAY_EVAL':
        text = JSON.stringify(this.mockEssay(input));
        break;
      case 'TUTOR':
        text = 'Resposta simulada do Professor ENEM IA (provider mock). Configure AI_PROVIDER=anthropic para respostas reais.';
        break;
      default:
        text = JSON.stringify({ summary: 'Conteúdo simulado (provider mock).' });
    }
    return { text, provider: this.name, model: this.model, inputTokens: input.user.length / 4, outputTokens: text.length / 4, latencyMs: Date.now() - started };
  }

  private mockEssay(input: AiCompletionInput) {
    const essay = input.user.split('=== TEXTO DO ALUNO ===')[1] ?? input.user;
    const words = essay.trim().split(/\s+/).filter(Boolean).length;
    const paragraphs = essay.split(/\n\s*\n/).filter((p) => p.trim().length > 0).length;
    const hasProposal = /propost|dev[e|em] |cabe ao|é necessário|medida/i.test(essay);
    const jitter = (parseInt(createHash('md5').update(essay + (input.variant ?? '')).digest('hex').slice(0, 2), 16) % 3) * 40 - 40;

    const base = words < 120 ? 80 : words < 250 ? 120 : 160;
    const clamp = (n: number) => Math.max(0, Math.min(200, Math.round(n / 40) * 40));
    const scores = {
      1: clamp(base),
      2: clamp(paragraphs >= 4 ? base : base - 40),
      3: clamp(base + jitter),
      4: clamp(paragraphs >= 3 ? base : base - 40),
      5: clamp(hasProposal ? base : 40),
    };
    return {
      competencies: Object.entries(scores).map(([c, score]) => ({
        competency: Number(c),
        score,
        justification: `Avaliação simulada (mock) da competência ${c}.`,
        problematicExcerpts: [],
      })),
      positives: ['Estrutura em parágrafos identificada.'],
      improvements: hasProposal ? ['Detalhar agente, ação e meio na proposta de intervenção.'] : ['Incluir proposta de intervenção completa.'],
    };
  }
}
