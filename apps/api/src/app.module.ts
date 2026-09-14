import { Module } from '@nestjs/common';
import { APP_GUARD } from '@nestjs/core';
import { ScheduleModule } from '@nestjs/schedule';
import { ThrottlerGuard, ThrottlerModule } from '@nestjs/throttler';
import { JwtModule } from '@nestjs/jwt';
import { PrismaModule } from './prisma/prisma.module';
import { JwtAuthGuard } from './common/guards/jwt-auth.guard';
import { RolesGuard } from './common/guards/roles.guard';
import { AuditModule } from './modules/audit/audit.module';
import { AuthModule } from './modules/auth/auth.module';
import { UsersModule } from './modules/users/users.module';
import { ContentModule } from './modules/content/content.module';
import { ExamEngineModule } from './modules/exam-engine/exam-engine.module';
import { EssaysModule } from './modules/essays/essays.module';
import { StudyModule } from './modules/study/study.module';
import { AnalyticsModule } from './modules/analytics/analytics.module';
import { SubscriptionsModule } from './modules/subscriptions/subscriptions.module';
import { PaymentsModule } from './modules/payments/payments.module';
import { ReferralsModule } from './modules/referrals/referrals.module';
import { PromotionsModule } from './modules/promotions/promotions.module';
import { ScholarshipsModule } from './modules/scholarships/scholarships.module';
import { NotificationsModule } from './modules/notifications/notifications.module';
import { AiModule } from './modules/ai/ai.module';
import { KnowledgeBaseModule } from './modules/knowledge-base/knowledge-base.module';
import { TutorModule } from './modules/tutor/tutor.module';
import { AdminModule } from './modules/admin/admin.module';

/**
 * ENEM ORCHESTRATOR — composição raiz.
 * Cada módulo corresponde a um serviço/agente descrito em docs/AGENTS.md.
 * Guards globais: JWT (autenticação) → Roles (RBAC) → Throttler (rate limit).
 */
@Module({
  imports: [
    ScheduleModule.forRoot(),
    ThrottlerModule.forRoot([{ ttl: 60_000, limit: 120 }]),
    JwtModule.register({}),
    PrismaModule,
    AuditModule,
    AiModule,
    NotificationsModule,
    AuthModule,
    UsersModule,
    ContentModule,
    SubscriptionsModule,
    PaymentsModule,
    ReferralsModule,
    PromotionsModule,
    ScholarshipsModule,
    ExamEngineModule,
    EssaysModule,
    StudyModule,
    AnalyticsModule,
    KnowledgeBaseModule,
    TutorModule,
    AdminModule,
  ],
  providers: [
    { provide: APP_GUARD, useClass: JwtAuthGuard },
    { provide: APP_GUARD, useClass: RolesGuard },
    { provide: APP_GUARD, useClass: ThrottlerGuard },
  ],
})
export class AppModule {}
