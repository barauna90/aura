import {
  Body,
  Controller,
  ForbiddenException,
  Get,
  Param,
  ParseIntPipe,
  Post,
  Put,
  UploadedFile,
  UseInterceptors,
} from '@nestjs/common';
import { FileInterceptor } from '@nestjs/platform-express';
import { PrismaService } from '../../prisma/prisma.service';
import { Roles } from '../../common/decorators/roles.decorator';
import { CurrentUser, AuthUser } from '../../common/decorators/current-user.decorator';
import { ContentImportService } from './content-import.service';
import { OfficialContentGuardianService } from './guardian.service';
import {
  AdvanceStageDto,
  ChangeAnswerDto,
  ChangeExamDto,
  CreateBookletDto,
  CreateExamDto,
  RegisterAnswerKeyDto,
  RegisterEssayPromptDto,
  RejectDto,
  ZeroRuleDto,
} from './content.dto';

interface UploadedPdf {
  buffer: Buffer;
  mimetype: string;
  size: number;
}

const PDF_LIMIT = { limits: { fileSize: 60 * 1024 * 1024 } };

/**
 * CMS de conteúdo oficial (seções 4, 40, 41). Acesso: REVIEWER+ para leitura,
 * ADMIN+ para importação, e revisão humana em duas etapas por pessoas distintas.
 */
@Controller('admin/content')
@Roles('REVIEWER')
export class ContentAdminController {
  constructor(
    private prisma: PrismaService,
    private importer: ContentImportService,
    private guardian: OfficialContentGuardianService,
  ) {}

  @Get('exams')
  list() {
    return this.prisma.exam.findMany({
      include: {
        edition: true,
        source: true,
        booklets: { include: { _count: { select: { questions: true } }, answerSets: true } },
        essayPrompt: { select: { id: true, theme: true, reviewStatus: true } },
      },
      orderBy: [{ edition: { year: 'desc' } }, { day: 'asc' }],
    });
  }

  @Get('exams/:id/validate')
  async validate(@Param('id') id: string) {
    const structural = await this.guardian.autoValidate(id);
    const integrity = await this.guardian.verifyIntegrity(id);
    return { ok: structural.length === 0 && integrity.length === 0, structural, integrity };
  }

  @Get('exams/:id/versions')
  versions(@Param('id') id: string) {
    return this.prisma.contentVersion.findMany({ where: { entityType: 'Exam', entityId: id }, orderBy: { createdAt: 'desc' } });
  }

  @Roles('ADMIN')
  @Post('exams')
  @UseInterceptors(FileInterceptor('sourcePdf', PDF_LIMIT))
  createExam(@Body() dto: CreateExamDto, @UploadedFile() file: UploadedPdf | undefined, @CurrentUser() user: AuthUser) {
    return this.importer.createExam(dto, file?.buffer ?? null, user.id);
  }

  @Roles('ADMIN')
  @Post('exams/:id/booklets')
  @UseInterceptors(FileInterceptor('pdf', PDF_LIMIT))
  addBooklet(
    @Param('id') id: string,
    @Body() dto: CreateBookletDto,
    @UploadedFile() file: UploadedPdf,
    @CurrentUser() user: AuthUser,
  ) {
    return this.importer.addBooklet(id, dto, file?.buffer, user.id);
  }

  @Roles('ADMIN')
  @Post('booklets/:id/answer-key')
  @UseInterceptors(FileInterceptor('gabaritoPdf', PDF_LIMIT))
  answerKey(
    @Param('id') id: string,
    @Body() dto: RegisterAnswerKeyDto,
    @UploadedFile() file: UploadedPdf | undefined,
    @CurrentUser() user: AuthUser,
  ) {
    return this.importer.registerAnswerKey(id, dto, file?.buffer ?? null, user.id);
  }

  @Roles('ADMIN')
  @Post('exams/:id/essay-prompt')
  essayPrompt(@Param('id') id: string, @Body() dto: RegisterEssayPromptDto, @CurrentUser() user: AuthUser) {
    return this.importer.registerEssayPrompt(id, dto, user.id);
  }

  @Roles('ADMIN')
  @Post('editions/:year/zero-rules')
  zeroRules(@Param('year', ParseIntPipe) year: number, @Body() rules: ZeroRuleDto[], @CurrentUser() user: AuthUser) {
    return this.importer.registerZeroRules(year, rules, user.id);
  }

  @Roles('ADMIN')
  @Post('editions/:year/zero-rules/verify')
  verifyZeroRules(@Param('year', ParseIntPipe) year: number, @CurrentUser() user: AuthUser) {
    return this.importer.verifyZeroRules(year, user.id);
  }

  /** Avança uma etapa do fluxo de auditoria (REVIEWER pode revisar; publicar exige ADMIN). */
  @Post('exams/:id/stage')
  async advance(@Param('id') id: string, @Body() dto: AdvanceStageDto, @CurrentUser() user: AuthUser) {
    if (dto.target === 'PUBLISHED' && user.role === 'REVIEWER') {
      throw new ForbiddenException('Publicação exige perfil ADMIN');
    }
    return this.guardian.advanceExam(id, dto.target, user.id);
  }

  @Post('exams/:id/reject')
  reject(@Param('id') id: string, @Body() dto: RejectDto, @CurrentUser() user: AuthUser) {
    return this.guardian.rejectExam(id, dto.reason, user.id);
  }

  @Roles('ADMIN')
  @Put('exams/:id')
  changeExam(@Param('id') id: string, @Body() dto: ChangeExamDto, @CurrentUser() user: AuthUser) {
    const { reason, ...data } = dto;
    return this.guardian.changeExamMetadata(id, data, reason, user.id);
  }

  @Roles('ADMIN')
  @Put('questions/:id/official-answer')
  changeAnswer(@Param('id') id: string, @Body() dto: ChangeAnswerDto, @CurrentUser() user: AuthUser) {
    return this.guardian.changeOfficialAnswer(id, { correct: dto.correct, annulled: dto.annulled }, dto.reason, user.id);
  }
}
