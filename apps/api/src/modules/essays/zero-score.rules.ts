/**
 * ZERO SCORE VALIDATOR — regras puras (seção 15).
 *
 * Só aplica verificações cujo código exista no conjunto de regras VERIFIED da
 * edição. Regras que exigem leitura semântica (fuga ao tema, não atendimento
 * ao tipo textual) são delegadas aos avaliadores e só valem com concordância.
 */

export type ZeroRuleCode =
  | 'EM_BRANCO'
  | 'INSUFICIENTE'
  | 'IDENTIFICACAO'
  | 'LINGUA_ESTRANGEIRA'
  | 'ANULACAO_DELIBERADA'
  | 'FUGA_TEMA'
  | 'NAO_DISSERTATIVO'
  | 'DESCONECTADO'
  | 'ILEGIVEL';

export interface EditionZeroRule {
  code: string;
  description: string;
}

export interface ZeroCheckResult {
  zero: boolean;
  code?: string;
  description?: string;
  /** Códigos que dependem da avaliação semântica dos avaliadores. */
  deferredToEvaluators: string[];
}

const PT_STOPWORDS = ['de', 'que', 'não', 'para', 'com', 'uma', 'os', 'as', 'do', 'da', 'em', 'um', 'é', 'se', 'por', 'mais', 'como', 'mas', 'ao', 'dos', 'das', 'sua', 'seu', 'são', 'também'];
const EN_ES_STOPWORDS = ['the', 'and', 'with', 'that', 'this', 'for', 'are', 'los', 'las', 'con', 'una', 'para', 'pero', 'sobre', 'también', 'está'];

/** Linhas efetivas (ignora linhas vazias). */
export function countLines(text: string): number {
  return text.split(/\r?\n/).filter((l) => l.trim().length > 0).length;
}

export function deterministicZeroCheck(
  text: string,
  editionRules: EditionZeroRule[],
  opts: { minLines: number } = { minLines: 8 },
): ZeroCheckResult {
  const enabled = new Map(editionRules.map((r) => [r.code, r.description]));
  const deferred = ['FUGA_TEMA', 'NAO_DISSERTATIVO', 'DESCONECTADO'].filter((c) => enabled.has(c));
  const hit = (code: string): ZeroCheckResult => ({ zero: true, code, description: enabled.get(code), deferredToEvaluators: deferred });

  const trimmed = text.trim();
  if (enabled.has('EM_BRANCO') && trimmed.length === 0) return hit('EM_BRANCO');

  // "Texto insuficiente": o mínimo de linhas é parâmetro da edição (opts.minLines), nunca fixo em código.
  if (enabled.has('INSUFICIENTE') && countLines(trimmed) < opts.minLines) return hit('INSUFICIENTE');

  if (enabled.has('ANULACAO_DELIBERADA')) {
    const deliberate = /^(?:\s*[\W_]{5,}\s*)$/m.test(trimmed) || /(.)\1{30,}/.test(trimmed) || /(hino nacional|receita de bolo)/i.test(trimmed);
    if (deliberate) return hit('ANULACAO_DELIBERADA');
  }

  if (enabled.has('IDENTIFICACAO')) {
    const identified = /(?:^|\n)\s*(?:assinado|ass\.|nome:|inscri[cç][aã]o|cpf|matr[ií]cula)\b/i.test(trimmed) || /[\w.+-]+@[\w-]+\.[\w.]+/.test(trimmed);
    if (identified) return hit('IDENTIFICACAO');
  }

  if (enabled.has('LINGUA_ESTRANGEIRA')) {
    const words = trimmed.toLowerCase().split(/[^a-záéíóúâêôãõç]+/).filter(Boolean);
    if (words.length > 30) {
      const pt = words.filter((w) => PT_STOPWORDS.includes(w)).length;
      const foreign = words.filter((w) => EN_ES_STOPWORDS.includes(w) && !PT_STOPWORDS.includes(w)).length;
      if (foreign > pt * 1.5 && foreign > 5) return hit('LINGUA_ESTRANGEIRA');
    }
  }

  return { zero: false, deferredToEvaluators: deferred };
}
