import { BadRequestException, ForbiddenException, Injectable, NotFoundException } from '@nestjs/common';
import { DISCLAIMERS, ESSAY_COMPETENCY_LABEL } from '@sip-enem/shared';
import { PrismaService } from '../../prisma/prisma.service';
import { AuditService } from '../audit/audit.service';
import { AccessService } from '../subscriptions/access.service';
import { EssayQueueService } from './essay-queue.service';
import { countLines } from './zero-score.rules';

/**
 * ESSAY AGENT (seções 13, 17, 18).
 * - Em sessão PROVA_REAL: rascunho salvo sem IA/corretor; envio só após encerrar a prova.
 * - Treino avulso (fora de sessão): proposta oficial VERIFIED, envio direto.
 */
@Injectable()
export class EssaysService {
  constructor(
    private prisma: PrismaService,
    private audit: AuditService,
    private access: AccessService,
    private queue: EssayQueueService,
  ) {}

  /** Propostas oficiais disponíveis para treino. */
  prompts() {
    return this.prisma.essayPrompt.findMany({
      where: { reviewStatus: 'VERIFIED', exam: { reviewStatus: 'VERIFIED', pipelineStage: 'PUBLISHED' } },
      include: { exam: { include: { edition: true } }, source: { select: { sourceUrl: true } } },
      orderBy: { exam: { edition: { year: 'desc' } } },
    });
  }

  async prompt(promptId: string) {
    const p = await this.prisma.essayPrompt.findFirst({ where: { id: promptId, reviewStatus: 'VERIFIED' }, include: { exam: { include: { edition: true } }, source: true } });
    if (!p) throw new NotFoundException('Proposta não disponível');
    return p;
  }

  /** Cria/obtém a redação vinculada a uma sessão de prova ou a um treino avulso. */
  async openDraft(userId: string, opts: { sessionId?: string; promptId?: string }) {
    let promptId = opts.promptId;
    if (opts.sessionId) {
      const session = await this.prisma.examSession.findUnique({ where: { id: opts.sessionId }, include: { exam: { include: { essayPrompt: true } } } });
      if (!session || session.userId !== userId) throw new NotFoundException('Sessão não encontrada');
      if (!session.exam.essayPrompt || session.exam.essayPrompt.reviewStatus !== 'VERIFIED') throw new BadRequestException('Esta prova não possui proposta de redação verificada');
      promptId = session.exam.essayPrompt.id;
      const existing = await this.prisma.essay.findUnique({ where: { sessionId: opts.sessionId } });
      if (existing) return existing;
      return this.prisma.essay.create({ data: { userId, sessionId: opts.sessionId, promptId } });
    }
    if (!promptId) throw new BadRequestException('Informe sessionId ou promptId');
    await this.prompt(promptId);
    return this.prisma.essay.create({ data: { userId, promptId } });
  }

  /** Autosave do rascunho. Sem corretor, sem IA, sem sugestões. */
  async saveDraft(userId: string, essayId: string, draftText: string) {
    const essay = await this.own(userId, essayId);
    if (essay.status !== 'DRAFT') throw new ForbiddenException('Redação já enviada');
    if (essay.session && ['FINISHED', 'EXPIRED'].includes(essay.session.status) && essay.session.mode === 'PROVA_REAL') {
      throw new ForbiddenException('O tempo da prova terminou; o rascunho não pode mais ser alterado.');
    }
    const maxLines = essay.prompt.maxLines;
    if (countLines(draftText) > maxLines) throw new BadRequestException(`A redação deve ter no máximo ${maxLines} linhas, como na folha oficial.`);
    return this.prisma.essay.update({ where: { id: essayId }, data: { draftText, lineCount: countLines(draftText) } });
  }

  /** Envia para avaliação. Em sessão PROVA_REAL só após o encerramento da prova. */
  async submit(userId: string, essayId: string) {
    const essay = await this.own(userId, essayId);
    if (essay.status !== 'DRAFT') throw new BadRequestException('Redação já enviada');
    if (essay.session && essay.session.mode === 'PROVA_REAL' && !['FINISHED', 'EXPIRED'].includes(essay.session.status)) {
      throw new ForbiddenException('No Modo Prova Real a redação só pode ser enviada após encerrar a prova.');
    }
    await this.access.assertCanSubmitEssay(userId);
    const text = essay.draftText ?? '';
    const updated = await this.prisma.essay.update({
      where: { id: essayId },
      data: { finalText: text, status: 'SUBMITTED', submittedAt: new Date(), lineCount: countLines(text) },
    });
    await this.audit.log({ actorId: userId, action: 'essay.submitted', entityType: 'Essay', entityId: essayId });
    await this.queue.enqueue(essayId);
    return { id: updated.id, status: updated.status, notice: DISCLAIMERS.ESSAY_EVALUATION };
  }

  async report(userId: string, essayId: string) {
    const essay = await this.own(userId, essayId);
    const final = await this.prisma.essayFinalResult.findUnique({ where: { essayId } });
    const evaluations = await this.prisma.essayEvaluation.findMany({ where: { essayId }, include: { competencies: { orderBy: { competency: 'asc' } } }, orderBy: { evaluator: 'asc' } });

    const competencies = [1, 2, 3, 4, 5].map((c) => {
      const scores = (final?.competencyScores ?? {}) as Record<string, number>;
      const perEvaluator = evaluations.map((e) => {
        const item = e.competencies.find((x) => x.competency === c);
        return { evaluator: e.evaluator, score: item?.score ?? 0, justification: item?.justification ?? '', problematicExcerpts: (item?.problematicExcerpts as string[]) ?? [] };
      });
      return { competency: c, label: ESSAY_COMPETENCY_LABEL[c as 1 | 2 | 3 | 4 | 5], score: scores[c] ?? null, analyses: perEvaluator };
    });

    return {
      id: essay.id,
      status: essay.status,
      promptId: essay.promptId,
      prompt: { theme: essay.prompt.theme, year: essay.prompt.exam.edition.year, examTitle: essay.prompt.exam.title },
      text: essay.finalText ?? essay.draftText,
      lineCount: essay.lineCount,
      scoreLabel: DISCLAIMERS.ESSAY_SCORE_LABEL,
      simulatedTotal: final?.total ?? null,
      competencies,
      positives: [...new Set(evaluations.flatMap((e) => e.positives as string[]))],
      improvements: [...new Set(evaluations.flatMap((e) => e.improvements as string[]))],
      checklist: final?.checklist ?? [],
      studyRecommendation: final?.studyRecommendation ?? null,
      usedThirdEvaluator: final?.usedThirdEvaluator ?? false,
      consistencyFlags: final?.consistencyFlags ?? [],
      notice: DISCLAIMERS.ESSAY_EVALUATION,
      improveParagraphOffer: final ? 'Veja como melhorar este parágrafo. (Material pedagógico — não é texto oficial.)' : null,
    };
  }

  list(userId: string) {
    return this.prisma.essay.findMany({
      where: { userId },
      include: { prompt: { include: { exam: { include: { edition: true } } } }, finalResult: { select: { total: true } } },
      orderBy: { createdAt: 'desc' },
    });
  }

  private async own(userId: string, essayId: string) {
    const e = await this.prisma.essay.findUnique({ where: { id: essayId }, include: { session: true, prompt: { include: { exam: { include: { edition: true } } } } } });
    if (!e || e.userId !== userId) throw new NotFoundException('Redação não encontrada');
    return e;
  }
}
