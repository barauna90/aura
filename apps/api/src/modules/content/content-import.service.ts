import { BadRequestException, ConflictException, Injectable, NotFoundException } from '@nestjs/common';
import { Prisma } from '@prisma/client';
import { PrismaService } from '../../prisma/prisma.service';
import { AuditService } from '../audit/audit.service';
import { StorageService } from './storage.service';
import { answerKeyChecksum, sha256 } from './guardian.rules';
import { CreateBookletDto, CreateExamDto, RegisterAnswerKeyDto, RegisterEssayPromptDto, ZeroRuleDto } from './content.dto';

/**
 * Painel de importação (seção 4). Tudo entra como PENDING / IMPORTED.
 * Nada aqui publica conteúdo — publicação é exclusiva do Guardian.
 */
@Injectable()
export class ContentImportService {
  constructor(
    private prisma: PrismaService,
    private audit: AuditService,
    private storage: StorageService,
  ) {}

  async createExam(dto: CreateExamDto, sourcePdf: Buffer | null, actorId: string) {
    const edition = await this.prisma.examEdition.upsert({
      where: { year: dto.year },
      update: {},
      create: { year: dto.year, name: `ENEM ${dto.year}` },
    });
    const existing = await this.prisma.exam.findUnique({
      where: { editionId_application_day: { editionId: edition.id, application: dto.application, day: dto.day } },
    });
    if (existing) throw new ConflictException('Prova já cadastrada para esta edição/aplicação/dia');

    const source = await this.prisma.contentSource.create({
      data: {
        sourceType: 'OFFICIAL_INEP',
        sourceUrl: dto.sourceUrl,
        sourceYear: dto.year,
        documentVersion: dto.documentVersion,
        checksum: sourcePdf ? sha256(sourcePdf) : sha256(dto.sourceUrl),
        description: dto.title,
      },
    });
    const exam = await this.prisma.exam.create({
      data: {
        editionId: edition.id,
        application: dto.application,
        day: dto.day,
        title: dto.title,
        durationMinutes: dto.durationMinutes,
        areas: dto.areas,
        hasEssay: dto.hasEssay ?? dto.areas.includes('REDACAO'),
        hasForeignLanguage: dto.hasForeignLanguage ?? dto.areas.includes('LINGUAGENS'),
        structureNote: dto.structureNote,
        sourceId: source.id,
        isFreeSample: dto.isFreeSample ?? false,
      },
    });
    await this.audit.log({ actorId, action: 'content.exam.imported', entityType: 'Exam', entityId: exam.id, metadata: { sourceUrl: dto.sourceUrl } });
    return exam;
  }

  async addBooklet(examId: string, dto: CreateBookletDto, pdf: Buffer, actorId: string) {
    const exam = await this.prisma.exam.findUnique({ where: { id: examId } });
    if (!exam) throw new NotFoundException('Prova não encontrada');
    if (!pdf?.length) throw new BadRequestException('PDF oficial obrigatório');
    if (pdf.subarray(0, 5).toString() !== '%PDF-') throw new BadRequestException('Arquivo não é um PDF válido');

    const checksum = sha256(pdf);
    const key = `official/${exam.editionId}/${exam.id}/${dto.color.toLowerCase()}-${checksum.slice(0, 12)}.pdf`;
    await this.storage.put(key, pdf, 'application/pdf');

    const source = await this.prisma.contentSource.create({
      data: {
        sourceType: 'OFFICIAL_INEP',
        sourceUrl: dto.sourceUrl,
        sourceYear: new Date().getFullYear(),
        documentVersion: dto.documentVersion,
        checksum,
        description: `${exam.title} — ${dto.label}`,
      },
    });
    const booklet = await this.prisma.examBooklet.create({
      data: {
        examId,
        color: dto.color,
        label: dto.label,
        pdfKey: key,
        pdfChecksum: checksum,
        pageCount: dto.pageCount,
        sourceId: source.id,
        pages: { create: Array.from({ length: dto.pageCount }, (_, i) => ({ pageNumber: i + 1 })) },
      },
    });
    await this.resetExam(examId);
    await this.audit.log({ actorId, action: 'content.booklet.imported', entityType: 'ExamBooklet', entityId: booklet.id, metadata: { checksum } });
    return booklet;
  }

  /**
   * Registra o gabarito oficial. Cria as questões (número/área/idioma) e o
   * OfficialAnswerSet com checksum canônico — usado depois pelo Guardian para
   * detectar qualquer alteração silenciosa.
   */
  async registerAnswerKey(bookletId: string, dto: RegisterAnswerKeyDto, gabaritoPdf: Buffer | null, actorId: string) {
    const booklet = await this.prisma.examBooklet.findUnique({ where: { id: bookletId }, include: { exam: true } });
    if (!booklet) throw new NotFoundException('Caderno não encontrado');

    const numbers = new Set<number>();
    for (const a of dto.answers) {
      if (numbers.has(a.number)) throw new BadRequestException(`Questão ${a.number} duplicada`);
      numbers.add(a.number);
      if (!a.annulled && !a.correct) throw new BadRequestException(`Questão ${a.number} sem alternativa correta`);
    }

    const checksum = answerKeyChecksum(dto.answers.map((a) => ({ number: a.number, correct: a.correct ?? null, annulled: a.annulled })));
    let pdfKey: string | undefined;
    if (gabaritoPdf?.length) {
      pdfKey = `official/${booklet.exam.editionId}/${booklet.examId}/gabarito-${booklet.color.toLowerCase()}-${checksum.slice(0, 12)}.pdf`;
      await this.storage.put(pdfKey, gabaritoPdf, 'application/pdf');
    }

    return this.prisma.$transaction(async (tx) => {
      const source = await tx.contentSource.create({
        data: {
          sourceType: 'OFFICIAL_INEP',
          sourceUrl: dto.sourceUrl,
          sourceYear: new Date().getFullYear(),
          documentVersion: dto.documentVersion,
          checksum: gabaritoPdf ? sha256(gabaritoPdf) : checksum,
          description: `Gabarito — ${booklet.label}`,
        },
      });
      const set = await tx.officialAnswerSet.create({
        data: { bookletId, sourceId: source.id, pdfKey, checksum },
      });
      for (const a of dto.answers) {
        const question = await tx.question.upsert({
          where: { bookletId_originalNumber: { bookletId, originalNumber: a.number } },
          update: { area: a.area, foreignLanguage: a.foreignLanguage ?? null, pageNumber: a.page ?? null },
          create: {
            bookletId,
            originalNumber: a.number,
            area: a.area,
            foreignLanguage: a.foreignLanguage ?? null,
            pageNumber: a.page ?? null,
            options: { create: (['A', 'B', 'C', 'D', 'E'] as const).map((letter) => ({ letter })) },
          },
        });
        await tx.officialAnswer.upsert({
          where: { questionId: question.id },
          update: { answerSetId: set.id, correct: a.annulled ? null : a.correct, annulled: a.annulled ?? false, reviewStatus: 'PENDING' },
          create: { answerSetId: set.id, questionId: question.id, correct: a.annulled ? null : a.correct, annulled: a.annulled ?? false },
        });
      }
      await tx.exam.update({ where: { id: booklet.examId }, data: { reviewStatus: 'PENDING', pipelineStage: 'IMPORTED' } });
      await tx.auditLog.create({
        data: { actorId, action: 'content.answer_key.imported', entityType: 'OfficialAnswerSet', entityId: set.id, metadata: { checksum, count: dto.answers.length } as Prisma.InputJsonValue },
      });
      return set;
    });
  }

  async registerEssayPrompt(examId: string, dto: RegisterEssayPromptDto, actorId: string) {
    const exam = await this.prisma.exam.findUnique({ where: { id: examId } });
    if (!exam) throw new NotFoundException('Prova não encontrada');
    const source = await this.prisma.contentSource.create({
      data: {
        sourceType: 'OFFICIAL_INEP',
        sourceUrl: dto.sourceUrl,
        sourceYear: new Date().getFullYear(),
        documentVersion: dto.documentVersion,
        checksum: sha256(JSON.stringify({ theme: dto.theme, texts: dto.motivatingTexts })),
        description: `Proposta de redação — ${exam.title}`,
      },
    });
    const prompt = await this.prisma.essayPrompt.upsert({
      where: { examId },
      update: { theme: dto.theme, motivatingTexts: dto.motivatingTexts as Prisma.InputJsonValue, maxLines: dto.maxLines ?? 30, sourceId: source.id, reviewStatus: 'PENDING', version: { increment: 1 } },
      create: { examId, theme: dto.theme, motivatingTexts: dto.motivatingTexts as Prisma.InputJsonValue, maxLines: dto.maxLines ?? 30, sourceId: source.id },
    });
    await this.prisma.exam.update({ where: { id: examId }, data: { hasEssay: true, reviewStatus: 'PENDING', pipelineStage: 'IMPORTED' } });
    await this.audit.log({ actorId, action: 'content.essay_prompt.imported', entityType: 'EssayPrompt', entityId: prompt.id });
    return prompt;
  }

  /** Regras de nota zero versionadas por edição (seção 15). */
  async registerZeroRules(year: number, rules: ZeroRuleDto[], actorId: string) {
    const edition = await this.prisma.examEdition.upsert({ where: { year }, update: {}, create: { year, name: `ENEM ${year}` } });
    for (const r of rules) {
      await this.prisma.essayZeroRule.upsert({
        where: { editionId_code: { editionId: edition.id, code: r.code } },
        update: { description: r.description, sourceUrl: r.sourceUrl },
        create: { editionId: edition.id, code: r.code, description: r.description, sourceUrl: r.sourceUrl },
      });
    }
    await this.audit.log({ actorId, action: 'content.zero_rules.imported', entityType: 'ExamEdition', entityId: edition.id, metadata: { count: rules.length } });
    return this.prisma.essayZeroRule.findMany({ where: { editionId: edition.id } });
  }

  async verifyZeroRules(year: number, actorId: string) {
    const edition = await this.prisma.examEdition.findUnique({ where: { year } });
    if (!edition) throw new NotFoundException('Edição não encontrada');
    await this.prisma.essayZeroRule.updateMany({ where: { editionId: edition.id }, data: { reviewStatus: 'VERIFIED' } });
    await this.audit.log({ actorId, action: 'content.zero_rules.verified', entityType: 'ExamEdition', entityId: edition.id });
  }

  private resetExam(examId: string) {
    return this.prisma.exam.update({ where: { id: examId }, data: { reviewStatus: 'PENDING', pipelineStage: 'IMPORTED' } });
  }
}
