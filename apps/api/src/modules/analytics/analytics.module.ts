import { Controller, Get, Module, Query } from '@nestjs/common';
import { AnalyticsService, Period } from './analytics.service';
import { CurrentUser, AuthUser } from '../../common/decorators/current-user.decorator';

@Controller('analytics')
export class AnalyticsController {
  constructor(private analytics: AnalyticsService) {}

  @Get('dashboard')
  dashboard(@CurrentUser() user: AuthUser) {
    return this.analytics.dashboard(user.id);
  }

  @Get('overview')
  overview(@CurrentUser() user: AuthUser, @Query('period') period: Period = '30D') {
    return this.analytics.overview(user.id, period);
  }

  @Get('weak-topics')
  weakTopics(@CurrentUser() user: AuthUser) {
    return this.analytics.weakTopics(user.id, 10);
  }

  @Get('slowest')
  slowest(@CurrentUser() user: AuthUser) {
    return this.analytics.slowestQuestions(user.id);
  }
}

@Module({
  controllers: [AnalyticsController],
  providers: [AnalyticsService],
  exports: [AnalyticsService],
})
export class AnalyticsModule {}
