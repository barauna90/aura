import { Body, Controller, Get, Module, Param, Post, Put } from '@nestjs/common';
import { IsOptional, IsString, MaxLength } from 'class-validator';
import { EssaysService } from './essays.service';
import { EssayEvaluationOrchestrator } from './essay-evaluation.orchestrator';
import { EssayQueueService } from './essay-queue.service';
import { CurrentUser, AuthUser } from '../../common/decorators/current-user.decorator';
import { SubscriptionsModule } from '../subscriptions/subscriptions.module';

class OpenDraftDto {
  @IsOptional() @IsString()
  sessionId?: string;

  @IsOptional() @IsString()
  promptId?: string;
}
class DraftDto {
  @IsString() @MaxLength(20000)
  draftText: string;
}

@Controller('essays')
export class EssaysController {
  constructor(private essays: EssaysService) {}

  @Get('prompts')
  prompts() {
    return this.essays.prompts();
  }

  @Get('prompts/:id')
  prompt(@Param('id') id: string) {
    return this.essays.prompt(id);
  }

  @Get()
  list(@CurrentUser() user: AuthUser) {
    return this.essays.list(user.id);
  }

  @Post()
  open(@CurrentUser() user: AuthUser, @Body() dto: OpenDraftDto) {
    return this.essays.openDraft(user.id, dto);
  }

  @Put(':id/draft')
  draft(@CurrentUser() user: AuthUser, @Param('id') id: string, @Body() dto: DraftDto) {
    return this.essays.saveDraft(user.id, id, dto.draftText);
  }

  @Post(':id/submit')
  submit(@CurrentUser() user: AuthUser, @Param('id') id: string) {
    return this.essays.submit(user.id, id);
  }

  @Get(':id/report')
  report(@CurrentUser() user: AuthUser, @Param('id') id: string) {
    return this.essays.report(user.id, id);
  }
}

@Module({
  imports: [SubscriptionsModule],
  controllers: [EssaysController],
  providers: [EssaysService, EssayEvaluationOrchestrator, EssayQueueService],
  exports: [EssaysService],
})
export class EssaysModule {}
