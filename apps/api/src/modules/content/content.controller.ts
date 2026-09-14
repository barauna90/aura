import { Controller, Get, Param, Query, Res } from '@nestjs/common';
import type { Response } from 'express';
import { ExamApplication, ExamArea } from '@prisma/client';
import { ExamsService } from './exams.service';
import { StorageService } from './storage.service';
import { Public } from '../../common/decorators/public.decorator';

@Controller('exams')
export class ContentController {
  constructor(
    private exams: ExamsService,
    private storage: StorageService,
  ) {}

  @Public()
  @Get()
  catalog(
    @Query('year') year?: string,
    @Query('day') day?: string,
    @Query('area') area?: ExamArea,
    @Query('application') application?: ExamApplication,
  ) {
    return this.exams.catalog({
      year: year ? Number(year) : undefined,
      day: day ? Number(day) : undefined,
      area,
      application,
    });
  }

  @Public()
  @Get('years')
  years() {
    return this.exams.years();
  }

  @Get(':id')
  detail(@Param('id') id: string) {
    return this.exams.detail(id);
  }

  /** Entrega o PDF oficial, inalterado, com cache forte (o conteúdo é imutável por checksum). */
  @Get('booklets/:bookletId/pdf')
  async pdf(@Param('bookletId') bookletId: string, @Res() res: Response) {
    const booklet = await this.exams.bookletForStream(bookletId);
    res.setHeader('Content-Type', 'application/pdf');
    res.setHeader('Content-Disposition', `inline; filename="${booklet.label.replace(/[^\w\-]+/g, '_')}.pdf"`);
    res.setHeader('Cache-Control', 'private, max-age=86400, immutable');
    res.setHeader('ETag', booklet.pdfChecksum);
    this.storage.stream(booklet.pdfKey).pipe(res);
  }
}
