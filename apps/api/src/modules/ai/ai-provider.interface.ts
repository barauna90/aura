/**
 * Provider abstrato de IA (seção 46/61). Troca de modelo/fornecedor sem tocar
 * nas regras de negócio. A IA NUNCA recebe permissão de escrita em conteúdo oficial.
 */
export interface AiCompletionInput {
  purpose: 'ESSAY_EVAL' | 'STUDY_PLAN' | 'TUTOR' | 'RAG';
  system: string;
  user: string;
  /** Sugestão de tamanho máximo de saída. */
  maxTokens?: number;
  /** Semente de independência: avaliadores A/B/C recebem instruções distintas. */
  variant?: string;
}

export interface AiCompletionOutput {
  text: string;
  provider: string;
  model: string;
  inputTokens: number;
  outputTokens: number;
  latencyMs: number;
}

export interface AiProvider {
  readonly name: string;
  readonly model: string;
  complete(input: AiCompletionInput): Promise<AiCompletionOutput>;
}

export const AI_PROVIDER = Symbol('AI_PROVIDER');

/** Extrai o primeiro objeto JSON de uma resposta textual. */
export function extractJson<T>(text: string): T {
  const fenced = text.match(/```(?:json)?\s*([\s\S]*?)```/);
  const candidate = fenced ? fenced[1] : text;
  const start = candidate.indexOf('{');
  const end = candidate.lastIndexOf('}');
  if (start === -1 || end === -1) throw new Error('Resposta da IA não contém JSON');
  return JSON.parse(candidate.slice(start, end + 1)) as T;
}
