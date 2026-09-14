import { Body, Controller, Delete, Get, Module, Param, Post, Put, Query } from '@nestjs/common';
import { IsBoolean, IsDateString, IsEnum, IsIn, IsInt, IsOptional, IsString, Max, MaxLength, Min } from 'class-validator';
import { Type } from 'class-transformer';
import { ExamArea } from '@prisma/client';
import { DISCLAIMERS } from '@sip-enem/shared';
import { PrismaService } from '../../prisma/prisma.service';
import { ErrorNotebookService } from './error-notebook.service';
import { StudyPlanService } from './study-plan.service';
import { CurrentUser, AuthUser } from '../../common/decorators/current-user.decorator';
import { SubscriptionsModule } from '../subscriptions/subscriptions.module';
import { AnalyticsModule } from '../analytics/analytics.module';

class NoteDto {
  @IsString() @MaxLength(2000)
  note: string;
}
class ReviewDto {
  @IsInt() @Min(0) @Max(5)
  quality: 0 | 1 | 2 | 3 | 4 | 5;
}
class GeneratePlanDto {
  @IsIn(['REGULAR', 'INTENSIVO'])
  kind: 'REGULAR' | 'INTENSIVO';

  @IsOptional() @IsInt() @Min(1) @Max(80)
  weeklyHours?: number;

  @IsOptional() @IsInt() @Min(1) @Max(400)
  daysLeft?: number;
}
class UpdateTaskDto {
  @IsOptional() @IsIn(['PENDING', 'DONE', 'SKIPPED'])
  status?: 'PENDING' | 'DONE' | 'SKIPPED';

  @IsOptional() @IsDateString()
  scheduledOn?: string;
}
class GoalDto {
  @IsIn(['QUESTOES_DIA', 'HORAS_SEMANA', 'REDACOES_MES', 'PROVAS_MES', 'SEQUENCIA'])
  kind: string;

  @IsInt() @Min(1) @Max(1000)
  target: number;
}
class OnboardingDto {
  @IsOptional() @IsString() @MaxLength(60)
  goal?: string;

  @IsOptional() @IsDateString()
  targetExamDate?: string;

  @IsOptional() @IsInt() @Min(1) @Max(80)
  weeklyHours?: number;

  @IsOptional() @IsString() @MaxLength(200)
  mainDifficulty?: string;
}

@Controller('error-notebook')
export class ErrorNotebookController {
  constructor(private notebook: ErrorNotebookService) {}

  @Get()
  list(
    @CurrentUser() user: AuthUser,
    @Query('area') area?: ExamArea,
    @Query('year') year?: string,
    @Query('topic') topic?: string,
    @Query('reviewed') reviewed?: string,
    @Query('due') due?: string,
  ) {
    return this.notebook.list(user.id, {
      area,
      year: year ? Number(year) : undefined,
      topic,
      reviewed: reviewed === undefined ? undefined : reviewed === 'true',
      dueOnly: due === 'true',
    });
  }

  @Put(':id/note')
  note(@CurrentUser() user: AuthUser, @Param('id') id: string, @Body() dto: NoteDto) {
    return this.notebook.annotate(user.id, id, dto.note);
  }

  @Post(':id/review')
  review(@CurrentUser() user: AuthUser, @Param('id') id: string, @Body() dto: ReviewDto) {
    return this.notebook.review(user.id, id, dto.quality);
  }

  @Delete(':id')
  remove(@CurrentUser() user: AuthUser, @Param('id') id: string) {
    return this.notebook.remove(user.id, id);
  }
}

@Controller('study')
export class StudyController {
  constructor(
    private plans: StudyPlanService,
    private prisma: PrismaService,
  ) {}

  @Get('plan')
  plan(@CurrentUser() user: AuthUser) {
    return this.plans.current(user.id);
  }

  @Get('plan/today')
  today(@CurrentUser() user: AuthUser) {
    return this.plans.today(user.id);
  }

  @Post('plan/generate')
  generate(@CurrentUser() user: AuthUser, @Body() dto: GeneratePlanDto) {
    return this.plans.generate(user.id, dto);
  }

  @Put('tasks/:id')
  task(@CurrentUser() user: AuthUser, @Param('id') id: string, @Body() dto: UpdateTaskDto) {
    return this.plans.updateTask(user.id, id, { status: dto.status, scheduledOn: dto.scheduledOn ? new Date(dto.scheduledOn) : undefined });
  }

  @Get('calendar')
  calendar(@CurrentUser() user: AuthUser, @Query('from') from: string, @Query('to') to: string) {
    return this.plans.calendar(user.id, new Date(from), new Date(to));
  }

  @Get('goals')
  goals(@CurrentUser() user: AuthUser) {
    return this.prisma.goal.findMany({ where: { userId: user.id, active: true } });
  }

  @Post('goals')
  createGoal(@CurrentUser() user: AuthUser, @Body() dto: GoalDto) {
    return this.prisma.goal.create({ data: { userId: user.id, kind: dto.kind, target: dto.target } });
  }

  @Delete('goals/:id')
  async removeGoal(@CurrentUser() user: AuthUser, @Param('id') id: string) {
    await this.prisma.goal.updateMany({ where: { id, userId: user.id }, data: { active: false } });
  }

  @Post('onboarding')
  onboarding(@CurrentUser() user: AuthUser, @Body() dto: OnboardingDto) {
    return this.prisma.profile.update({
      where: { userId: user.id },
      data: {
        goal: dto.goal,
        targetExamDate: dto.targetExamDate ? new Date(dto.targetExamDate) : undefined,
        weeklyHours: dto.weeklyHours,
        mainDifficulty: dto.mainDifficulty,
        onboardingDone: true,
      },
    });
  }

  /** GUIA ENEM (seção 19): tópicos verificados por eixo, com recorrência derivada das provas. */
  @Get('guide')
  async guide(@Query('area') area?: ExamArea) {
    const topics = await this.prisma.studyTopic.findMany({
      where: { reviewStatus: 'VERIFIED', ...(area ? { area } : {}) },
      include: { materials: { where: { reviewStatus: 'VERIFIED' }, select: { id: true, title: true } }, _count: { select: { classifications: true } } },
      orderBy: [{ area: 'asc' }, { recurrence: 'desc' }],
    });
    return {
      notice: `Recorrência calculada a partir de classificações validadas de questões oficiais — ${DISCLAIMERS.RECURRENT_CONTENT}`,
      topics: topics.map((t) => ({ ...t, recurrenceLabel: t.recurrence > 0 ? DISCLAIMERS.RECURRENT_CONTENT : null, matrixLabel: t.matrixSkill ? DISCLAIMERS.MATRIX_SKILL : null })),
    };
  }

  @Get('guide/materials/:id')
  async material(@Param('id') id: string) {
    const m = await this.prisma.studyMaterial.findFirst({ where: { id, reviewStatus: 'VERIFIED' }, include: { topic: true } });
    return m ?? { message: DISCLAIMERS.NO_OFFICIAL_INFO };
  }
}

@Module({
  imports: [SubscriptionsModule, AnalyticsModule],
  controllers: [ErrorNotebookController, StudyController],
  providers: [ErrorNotebookService, StudyPlanService],
  exports: [ErrorNotebookService, StudyPlanService],
})
export class StudyModule {}
