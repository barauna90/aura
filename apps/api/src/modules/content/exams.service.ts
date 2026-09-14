import { Injectable, NotFoundException } from '@nestjs/common';
import { ExamApplication, ExamArea, Prisma } from '@prisma/client';
import { DISCLAIMERS } from '@sip-enem/shared';
import { PrismaService } from '../../prisma/prisma.service';

export interface CatalogFilters {
  year?: number;
  day?: number;
  area?: ExamArea;
  application?: ExamApplication;
}

/**
 * Catálogo "Provas anteriores". Só retorna provas VERIFIED + PUBLISHED de fonte OFFICIAL_INEP.
 * Este filtro é aplicado no banco — não confie em filtragem no frontend.
 */
export const OFFICIAL_VISIBLE_WHERE: Prisma.ExamWhereInput = {
  reviewStatus: 'VERIFIED',
  pipelineStage: 'PUBLISHED',
  source: { sourceType: 'OFFICIAL_INEP', reviewStatus: 'VERIFIED' },
};

@Injectable()
export class ExamsService {
  constructor(private prisma: PrismaService) {}

  async catalog(filters: CatalogFilters) {
    const exams = await this.prisma.exam.findMany({
      where: {
        ...OFFICIAL_VISIBLE_WHERE,
        ...(filters.year ? { edition: { year: filters.year } } : {}),
        ...(filters.day ? { day: filters.day } : {}),
        ...(filters.application ? { application: filters.application } : {}),
        ...(filters.area ? { areas: { has: filters.area } } : {}),
      },
      include: {
        edition: true,
        source: { select: { sourceUrl: true, sourceYear: true, documentVersion: true, lastValidation: true } },
        booklets: { where: { reviewStatus: 'VERIFIED' }, select: { id: true, color: true, label: true, pageCount: true } },
        _count: { select: { sessions: true } },
      },
      orderBy: [{ edition: { year: 'desc' } }, { application: 'asc' }, { day: 'asc' }],
    });
    return exams.map((e) => ({
      id: e.id,
      year: e.edition.year,
      editionName: e.edition.name,
      application: e.application,
      day: e.day,
      title: e.title,
      durationMinutes: e.durationMinutes,
      areas: e.areas,
      hasEssay: e.hasEssay,
      hasForeignLanguage: e.hasForeignLanguage,
      structureNote: e.structureNote ?? DISCLAIMERS.HISTORICAL_STRUCTURE,
      isFreeSample: e.isFreeSample,
      source: e.source,
      booklets: e.booklets,
      version: e.version,
    }));
  }

  async years() {
    const rows = await this.prisma.exam.findMany({
      where: OFFICIAL_VISIBLE_WHERE,
      select: { edition: { select: { year: true } } },
      distinct: ['editionId'],
    });
    return rows.map((r) => r.edition.year).sort((a, b) => b - a);
  }

  async detail(examId: string) {
    const exam = await this.prisma.exam.findFirst({
      where: { id: examId, ...OFFICIAL_VISIBLE_WHERE },
      include: {
        edition: true,
        source: true,
        booklets: {
          where: { reviewStatus: 'VERIFIED' },
          include: {
            _count: { select: { questions: true } },
            questions: {
              where: { reviewStatus: 'VERIFIED' },
              select: { id: true, originalNumber: true, area: true, foreignLanguage: true, pageNumber: true },
              orderBy: { originalNumber: 'asc' },
            },
          },
        },
        essayPrompt: {
          where: { reviewStatus: 'VERIFIED' },
          select: { id: true, theme: true, maxLines: true, version: true },
        },
      },
    });
    if (!exam) throw new NotFoundException('Prova oficial não encontrada ou ainda não publicada');
    return {
      ...exam,
      structureNote: exam.structureNote ?? DISCLAIMERS.HISTORICAL_STRUCTURE,
      notices: {
        answerSheetOnly: DISCLAIMERS.ANSWER_SHEET_ONLY,
        mobile: DISCLAIMERS.MOBILE_RECOMMENDATION,
        independence: DISCLAIMERS.INDEPENDENCE,
      },
    };
  }

  /** Só entrega o PDF de cadernos verificados. */
  async bookletForStream(bookletId: string) {
    const booklet = await this.prisma.examBooklet.findFirst({
      where: { id: bookletId, reviewStatus: 'VERIFIED', exam: OFFICIAL_VISIBLE_WHERE },
    });
    if (!booklet) throw new NotFoundException('Caderno não disponível');
    return booklet;
  }
}
