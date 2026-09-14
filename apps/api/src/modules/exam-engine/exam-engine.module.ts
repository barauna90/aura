import { Body, Controller, Get, Module, Param, Post, Query } from '@nestjs/common';
import { Cron, CronExpression } from '@nestjs/schedule';
import { ExamEngineService } from './exam-engine.service';
import { CreateSessionDto, NoteDto, SaveAnswersDto } from './exam-engine.dto';
import { CurrentUser, AuthUser } from '../../common/decorators/current-user.decorator';
import { SubscriptionsModule } from '../subscriptions/subscriptions.module';
import { StudyModule } from '../study/study.module';

@Controller('sessions')
export class ExamEngineController {
  constructor(private engine: ExamEngineService) {}

  @Post()
  create(@CurrentUser() user: AuthUser, @Body() dto: CreateSessionDto) {
    return this.engine.create(user.id, dto);
  }

  @Get('history')
  history(@CurrentUser() user: AuthUser) {
    return this.engine.history(user.id);
  }

  @Get('compare')
  compare(@CurrentUser() user: AuthUser, @Query('a') a: string, @Query('b') b: string) {
    return this.engine.compare(user.id, a, b);
  }

  @Get(':id')
  state(@CurrentUser() user: AuthUser, @Param('id') id: string) {
    return this.engine.state(user.id, id);
  }

  @Post(':id/start')
  start(@CurrentUser() user: AuthUser, @Param('id') id: string) {
    return this.engine.start(user.id, id);
  }

  @Post(':id/answers')
  answers(@CurrentUser() user: AuthUser, @Param('id') id: string, @Body() dto: SaveAnswersDto) {
    return this.engine.saveAnswers(user.id, id, dto);
  }

  @Post(':id/pause')
  pause(@CurrentUser() user: AuthUser, @Param('id') id: string) {
    return this.engine.pause(user.id, id);
  }

  @Post(':id/resume')
  resume(@CurrentUser() user: AuthUser, @Param('id') id: string) {
    return this.engine.resume(user.id, id);
  }

  @Post(':id/finish')
  finish(@CurrentUser() user: AuthUser, @Param('id') id: string) {
    return this.engine.finish(user.id, id);
  }

  @Get(':id/result')
  result(@CurrentUser() user: AuthUser, @Param('id') id: string) {
    return this.engine.result(user.id, id);
  }

  @Post(':id/notes')
  note(@CurrentUser() user: AuthUser, @Param('id') id: string, @Body() dto: NoteDto) {
    return this.engine.addNote(user.id, id, dto);
  }

  @Cron(CronExpression.EVERY_MINUTE)
  expireStale() {
    return this.engine.expireStale();
  }
}

@Module({
  imports: [SubscriptionsModule, StudyModule],
  controllers: [ExamEngineController],
  providers: [ExamEngineService],
  exports: [ExamEngineService],
})
export class ExamEngineModule {}
