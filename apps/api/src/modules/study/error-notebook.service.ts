import { Injectable, NotFoundException } from '@nestjs/common';
import { ExamArea } from '@prisma/client';
import { DISCLAIMERS } from '@sip-enem/shared';
import { PrismaService } from '../../prisma/prisma.service';

/**
 * MEU CADERNO DE ERROS (seção 21) com repetição espaçada (variação simplificada do SM-2).
 */
export function nextInterval(interval: number, ease: number, quality: 0 | 1 | 2 | 3 | 4 | 5) {
  const newEase = Math.max(1.3, ease + (0.1 - (5 - quality) * (0.08 + (5 - quality) * 0.02)));
  const newInterval = quality < 3 ? 1 : interval <= 1 ? 3 : Math.round(interval * newEase);
  return { interval: newInterval, ease: Math.round(newEase * 100) / 100 };
}

@Injectable()
export class ErrorNotebookService {
  constructor(private prisma: PrismaService) {}

  async addMany(userId: string, questionIds: string[]) {
    if (!questionIds.length) return;
    const tomorrow = new Date(Date.now() + 86_400_000);
    await this.prisma.errorNotebookEntry.createMany({
      data: questionIds.map((questionId) => ({ userId, questionId, nextReviewAt: tomorrow })),
      skipDuplicates: true,
    });
  }

  async list(userId: string, filters: { area?: ExamArea; year?: number; topic?: string; reviewed?: boolean; dueOnly?: boolean }) {
    const entries = await this.prisma.errorNotebookEntry.findMany({
      where: {
        userId,
        ...(filters.reviewed !== undefined ? { reviewed: filters.reviewed } : {}),
        ...(filters.dueOnly ? { nextReviewAt: { lte: new Date() } } : {}),
        question: {
          ...(filters.area ? { area: filters.area } : {}),
          ...(filters.year ? { booklet: { exam: { edition: { year: filters.year } } } } : {}),
          ...(filters.topic ? { classification: { topic: { slug: filters.topic } } } : {}),
        },
      },
      include: {
        question: {
          include: {
            booklet: { include: { exam: { include: { edition: true } } } },
            officialAnswer: true,
            classification: { include: { topic: true } },
            resolution: true,
          },
        },
      },
      orderBy: [{ nextReviewAt: 'asc' }, { createdAt: 'desc' }],
    });
    return entries.map((e) => ({
      id: e.id,
      note: e.note,
      reviewed: e.reviewed,
      nextReviewAt: e.nextReviewAt,
      interval: e.interval,
      question: {
        id: e.question.id,
        number: e.question.originalNumber,
        area: e.question.area,
        page: e.question.pageNumber,
        year: e.question.booklet.exam.edition.year,
        examTitle: e.question.booklet.exam.title,
        bookletId: e.question.bookletId,
        official: e.question.officialAnswer?.reviewStatus === 'VERIFIED' ? e.question.officialAnswer.correct : null,
        topic: e.question.classification?.reviewStatus === 'VERIFIED' ? e.question.classification.topic?.name : null,
        resolution: e.question.resolution?.reviewStatus === 'VERIFIED' ? e.question.resolution.body : null,
        resolutionNotice: e.question.resolution?.reviewStatus === 'VERIFIED' ? null : DISCLAIMERS.NO_RESOLUTION,
      },
    }));
  }

  async annotate(userId: string, entryId: string, note: string) {
    await this.ownEntry(userId, entryId);
    return this.prisma.errorNotebookEntry.update({ where: { id: entryId }, data: { note } });
  }

  /** Marca revisada e agenda a próxima revisão conforme a autoavaliação (0–5). */
  async review(userId: string, entryId: string, quality: 0 | 1 | 2 | 3 | 4 | 5) {
    const entry = await this.ownEntry(userId, entryId);
    const { interval, ease } = nextInterval(entry.interval, entry.ease, quality);
    return this.prisma.errorNotebookEntry.update({
      where: { id: entryId },
      data: { reviewed: true, interval, ease, nextReviewAt: new Date(Date.now() + interval * 86_400_000) },
    });
  }

  async remove(userId: string, entryId: string) {
    await this.ownEntry(userId, entryId);
    await this.prisma.errorNotebookEntry.delete({ where: { id: entryId } });
  }

  private async ownEntry(userId: string, entryId: string) {
    const e = await this.prisma.errorNotebookEntry.findUnique({ where: { id: entryId } });
    if (!e || e.userId !== userId) throw new NotFoundException('Entrada não encontrada');
    return e;
  }
}
