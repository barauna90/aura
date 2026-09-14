import { Injectable, Logger } from '@nestjs/common';
import { Prisma } from '@prisma/client';
import { DISCLAIMERS } from '@sip-enem/shared';
import { PrismaService } from '../../prisma/prisma.service';
import { AuditService } from '../audit/audit.service';
import { AiService } from '../ai/ai.module';
import { extractJson } from '../ai/ai-provider.interface';
import { deterministicZeroCheck } from './zero-score.rules';
import { aggregate, auditConsistency, EvaluatorOutput, needsThirdEvaluator, normalizeScore, divergence, DivergenceThresholds } from './essay-scoring.rules';
import { evaluatorSystemPrompt, evaluatorUserPrompt } from './evaluator.prompts';

/**
 * REDACTION EVALUATION ORCHESTRATOR (seções 14–18).
 *
 *  ZERO SCORE VALIDATOR → AVALIADOR A ∥ AVALIADOR B → (divergência?) AVALIADOR C
 *  → CONSISTENCY AUDITOR → agregação → relatório.
 *
 * Os limites de divergência são configuráveis (env) e devem ser ajustados pelo
 * administrador conforme a regra oficial usada como referência na edição.
 */
@Injectable()
export class EssayEvaluationOrchestrator {
  private readonly logger = new Logger('EssayEvaluation');
  private thresholds: DivergenceThresholds = {
    total: Number(process.env.ESSAY_DIVERGENCE_TOTAL ?? 100),
    perCompetency: Number(process.env.ESSAY_DIVERGENCE_COMPETENCY ?? 80),
  };

  constructor(
    private prisma: PrismaService,
    private audit: AuditService,
    private ai: AiService,
  ) {}

  async evaluate(essayId: string) {
    const essay = await this.prisma.essay.findUniqueOrThrow({
      where: { id: essayId },
      include: { prompt: { include: { exam: { include: { edition: { include: { essayZeroRules: { where: { reviewStatus: 'VERIFIED' } } } } } } } } },
    });
    if (!essay.finalText) throw new Error('Redação sem texto final');
    await this.prisma.essay.update({ where: { id: essayId }, data: { status: 'EVALUATING' } });

    try {
      const editionRules = essay.prompt.exam.edition.essayZeroRules.map((r) => ({ code: r.code, description: r.description }));
      const text = essay.finalText;
      const wordCount = text.trim().split(/\s+/).filter(Boolean).length;

      // 1) ZERO SCORE VALIDATOR (determinístico)
      const zero = deterministicZeroCheck(text, editionRules, { minLines: Math.max(1, Math.round(essay.prompt.maxLines * 0.25)) });
      if (zero.zero) {
        await this.storeFinal(essayId, [], { total: 0, competencyScores: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 }, zero: true, zeroReason: zero.code }, [], false, 0, wordCount);
        return;
      }

      // 2) AVALIADORES A e B — independentes e em paralelo.
      const motivating = essay.prompt.motivatingTexts as Array<{ title?: string; body: string }>;
      const run = (variant: 'A' | 'B' | 'C') =>
        this.runEvaluator(variant, essay.prompt.exam.edition.year, editionRules, zero.deferredToEvaluators, essay.prompt.theme, motivating, text);
      const [a, b] = await Promise.all([run('A'), run('B')]);
      const evals: EvaluatorOutput[] = [a, b];

      // 3) AVALIADOR C se houver divergência acima do limite.
      let usedThird = false;
      if (needsThirdEvaluator(a, b, this.thresholds)) {
        usedThird = true;
        evals.push(await run('C'));
        await this.audit.log({ action: 'essay.third_evaluator', entityType: 'Essay', entityId: essayId, metadata: divergence(a, b) });
      }

      // 4) CONSISTENCY AUDITOR + 5) agregação
      const flags = auditConsistency(evals, wordCount);
      const final = aggregate(evals);
      await this.storeFinal(essayId, evals, final, flags, usedThird, divergence(a, b).total, wordCount);
    } catch (e) {
      this.logger.error(`Falha na avaliação da redação ${essayId}: ${(e as Error).message}`);
      await this.prisma.essay.update({ where: { id: essayId }, data: { status: 'FAILED' } });
      await this.audit.alert('ERROR', 'essays', `Falha na correção da redação ${essayId}: ${(e as Error).message}`);
      throw e;
    }
  }

  private async runEvaluator(
    variant: 'A' | 'B' | 'C',
    year: number,
    rules: Array<{ code: string; description: string }>,
    deferred: string[],
    theme: string,
    motivating: Array<{ title?: string; body: string }>,
    text: string,
  ): Promise<EvaluatorOutput & { raw: unknown; provider: string; model: string }> {
    const out = await this.ai.complete({
      purpose: 'ESSAY_EVAL',
      variant,
      system: evaluatorSystemPrompt(variant, year, rules, deferred),
      user: evaluatorUserPrompt(theme, motivating, text),
    });
    const parsed = extractJson<{
      zeroScore?: boolean;
      zeroReason?: string | null;
      competencies: Array<{ competency: number; score: number; justification?: string; problematicExcerpts?: string[] }>;
      positives?: string[];
      improvements?: string[];
    }>(out.text);

    const competencies = [1, 2, 3, 4, 5].map((c) => {
      const found = parsed.competencies?.find((x) => Number(x.competency) === c);
      return {
        competency: c,
        score: normalizeScore(Number(found?.score ?? 0)),
        justification: String(found?.justification ?? ''),
        problematicExcerpts: Array.isArray(found?.problematicExcerpts) ? found!.problematicExcerpts.map(String) : [],
      };
    });
    // Zero semântico só é aceito para códigos delegados da edição.
    const zeroScore = !!parsed.zeroScore && !!parsed.zeroReason && deferred.includes(parsed.zeroReason);
    return {
      evaluator: variant,
      zeroScore,
      zeroReason: zeroScore ? parsed.zeroReason! : undefined,
      competencies,
      positives: parsed.positives ?? [],
      improvements: parsed.improvements ?? [],
      raw: parsed,
      provider: out.provider,
      model: out.model,
    };
  }

  private async storeFinal(
    essayId: string,
    evals: Array<EvaluatorOutput & { raw?: unknown; provider?: string; model?: string }>,
    final: ReturnType<typeof aggregate>,
    flags: string[],
    usedThird: boolean,
    divergenceTotal: number,
    wordCount: number,
  ) {
    const weak = Object.entries(final.competencyScores).sort((x, y) => x[1] - y[1]).slice(0, 2).map(([c]) => Number(c));
    const checklist = [
      { item: 'Tese clara na introdução', ok: final.competencyScores[2] >= 120 },
      { item: 'Dois argumentos desenvolvidos com repertório', ok: final.competencyScores[3] >= 120 },
      { item: 'Conectivos entre parágrafos e períodos', ok: final.competencyScores[4] >= 120 },
      { item: 'Proposta com agente, ação, meio, efeito e detalhamento', ok: final.competencyScores[5] >= 160 },
      { item: 'Norma culta sem desvios recorrentes', ok: final.competencyScores[1] >= 160 },
    ];
    const recommendation = {
      focusCompetencies: weak,
      suggestions: weak.map((c) => `Treinar competência ${c}: reler os critérios publicados e refazer um parágrafo com foco nela.`),
      nextStep: 'Escreva uma nova redação com proposta oficial de outra edição em até 7 dias.',
      notice: DISCLAIMERS.ESSAY_EVALUATION,
    };

    await this.prisma.$transaction(async (tx) => {
      for (const e of evals) {
        await tx.essayEvaluation.create({
          data: {
            essayId,
            evaluator: e.evaluator,
            provider: e.provider ?? this.ai.providerName,
            model: e.model ?? this.ai.model,
            zeroScore: e.zeroScore,
            zeroReason: e.zeroReason,
            total: e.zeroScore ? 0 : e.competencies.reduce((a, c) => a + c.score, 0),
            positives: e.positives,
            improvements: e.improvements,
            rawResponse: (e.raw ?? null) as Prisma.InputJsonValue,
            competencies: {
              create: e.competencies.map((c) => ({ competency: c.competency, score: c.score, justification: c.justification, problematicExcerpts: c.problematicExcerpts })),
            },
          },
        });
      }
      await tx.essayFinalResult.upsert({
        where: { essayId },
        update: {},
        create: {
          essayId,
          total: final.total,
          competencyScores: final.competencyScores,
          usedThirdEvaluator: usedThird,
          divergence: divergenceTotal,
          consistencyFlags: [...flags, ...(final.zero ? [`ZERO:${final.zeroReason}`] : [])],
          checklist,
          studyRecommendation: recommendation,
        },
      });
      await tx.essay.update({ where: { id: essayId }, data: { status: 'EVALUATED' } });
    });
    if (flags.length) await this.audit.alert('WARN', 'essays.consistency', `Inconsistências na redação ${essayId}`, { flags, wordCount });
  }
}
