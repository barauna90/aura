import { Body, Controller, ForbiddenException, Injectable, Module, Post } from '@nestjs/common';
import { IsOptional, IsString, MaxLength } from 'class-validator';
import { DISCLAIMERS } from '@sip-enem/shared';
import { PrismaService } from '../../prisma/prisma.service';
import { AiService } from '../ai/ai.module';
import { AccessService } from '../subscriptions/access.service';
import { KnowledgeBaseModule, KnowledgeBaseService } from '../knowledge-base/knowledge-base.module';
import { SubscriptionsModule } from '../subscriptions/subscriptions.module';
import { CurrentUser, AuthUser } from '../../common/decorators/current-user.decorator';

class AskDto {
  @IsString() @MaxLength(1000)
  question: string;

  @IsOptional() @IsString()
  sessionId?: string;

  @IsOptional() @IsString()
  essayId?: string;
}

/**
 * PROFESSOR ENEM IA (seção 48). Contexto = resultado do aluno + base oficial +
 * resoluções VERIFIED. Bloqueado durante qualquer sessão PROVA_REAL em andamento.
 */
@Injectable()
export class TutorService {
  constructor(
    private prisma: PrismaService,
    private ai: AiService,
    private access: AccessService,
    private kb: KnowledgeBaseService,
  ) {}

  async ask(userId: string, dto: AskDto) {
    await this.access.assertFeature(userId, 'tutor');
    const live = await this.prisma.examSession.count({ where: { userId, mode: 'PROVA_REAL', status: 'IN_PROGRESS' } });
    if (live > 0) throw new ForbiddenException('O Professor IA não está disponível durante o Modo Prova Real.');

    const context: string[] = [];
    if (dto.sessionId) {
      const s = await this.prisma.examSession.findFirst({
        where: { id: dto.sessionId, userId, status: { in: ['FINISHED', 'EXPIRED'] } },
        include: {
          result: true,
          answerSheet: { include: { answers: { where: { isCorrect: false }, include: { question: { include: { officialAnswer: true, classification: { include: { topic: true } }, resolution: true } } } } } },
        },
      });
      if (s?.result) {
        context.push(`RESULTADO DA PROVA: ${s.result.correct}/${s.result.totalQuestions} acertos. Por área: ${JSON.stringify(s.result.byArea)}`);
        const wrong = s.answerSheet?.answers.slice(0, 15).map((a) => {
          const q = a.question;
          const res = q.resolution?.reviewStatus === 'VERIFIED' ? q.resolution.body : DISCLAIMERS.NO_RESOLUTION;
          const topic = q.classification?.reviewStatus === 'VERIFIED' ? q.classification.topic?.name : 'assunto não classificado';
          return `Q${q.originalNumber} (${q.area}, ${topic}): marcou ${a.option}, gabarito ${q.officialAnswer?.correct}. Resolução: ${res}`;
        });
        if (wrong?.length) context.push(`QUESTÕES ERRADAS:\n${wrong.join('\n')}`);
      }
    }
    if (dto.essayId) {
      const e = await this.prisma.essay.findFirst({ where: { id: dto.essayId, userId }, include: { finalResult: true, evaluations: { include: { competencies: true } } } });
      if (e?.finalResult) {
        context.push(`REDAÇÃO (nota simulada ${e.finalResult.total}): ${JSON.stringify(e.finalResult.competencyScores)}. Melhorias apontadas: ${e.evaluations.flatMap((x) => x.improvements as string[]).join('; ')}`);
      }
    }
    const kb = await this.kb.search(dto.question, 4);
    if (kb.found) context.push(`BASE OFICIAL:\n${kb.chunks.map((c) => `[${c.document.title} ${c.document.year}] ${c.content}`).join('\n')}`);

    const out = await this.ai.complete({
      purpose: 'TUTOR',
      system: `Você é o Professor ENEM IA, um tutor educacional. Regras:
- Use somente o CONTEXTO fornecido (resultados do aluno, resoluções validadas, base oficial).
- Se a pergunta for sobre regras do ENEM e a base oficial não trouxer a resposta, diga exatamente: "${DISCLAIMERS.NO_OFFICIAL_INFO}"
- Nunca invente questões, gabaritos, notas oficiais ou regras. Nunca afirme que algo "vai cair".
- Não converta acertos em nota do ENEM. Se perguntarem, explique que o Inep usa a TRI.
- Seja direto, em português do Brasil, com passos práticos de estudo.`,
      user: `CONTEXTO:\n${context.join('\n\n') || '(sem contexto adicional)'}\n\nPERGUNTA DO ALUNO: ${dto.question}`,
      maxTokens: 4000,
    });
    return { answer: out.text, usedOfficialBase: kb.found, notice: DISCLAIMERS.INDEPENDENCE };
  }
}

@Controller('tutor')
export class TutorController {
  constructor(private tutor: TutorService) {}

  @Post('ask')
  ask(@CurrentUser() user: AuthUser, @Body() dto: AskDto) {
    return this.tutor.ask(user.id, dto);
  }
}

@Module({
  imports: [SubscriptionsModule, KnowledgeBaseModule],
  controllers: [TutorController],
  providers: [TutorService],
})
export class TutorModule {}
