import { Controller, Get, Global, Injectable, Module, Param, Post } from '@nestjs/common';
import { NotificationChannel } from '@prisma/client';
import { PrismaService } from '../../prisma/prisma.service';
import { CurrentUser, AuthUser } from '../../common/decorators/current-user.decorator';

/**
 * NOTIFICATION SERVICE (seção 51). Canais: IN_APP (implementado), EMAIL e
 * WEB_PUSH (pontos de extensão — respeitam consentimento e nunca fazem spam:
 * no máximo uma notificação do mesmo tipo por dia por usuário).
 */
@Injectable()
export class NotificationsService {
  constructor(private prisma: PrismaService) {}

  async notify(userId: string, kind: string, title: string, body: string, channel: NotificationChannel = 'IN_APP') {
    const since = new Date(Date.now() - 86_400_000);
    const recent = await this.prisma.notification.count({ where: { userId, kind, createdAt: { gte: since } } });
    if (recent > 0) return null; // anti-spam
    if (channel !== 'IN_APP') {
      const consent = await this.prisma.consent.findFirst({ where: { userId, type: channel === 'EMAIL' ? 'marketing_email' : 'web_push', granted: true }, orderBy: { createdAt: 'desc' } });
      if (!consent) channel = 'IN_APP';
    }
    return this.prisma.notification.create({ data: { userId, kind, title, body, channel, sentAt: new Date() } });
  }

  list(userId: string) {
    return this.prisma.notification.findMany({ where: { userId }, orderBy: { createdAt: 'desc' }, take: 50 });
  }

  async markRead(userId: string, id: string) {
    await this.prisma.notification.updateMany({ where: { id, userId }, data: { readAt: new Date() } });
  }
}

@Controller('notifications')
export class NotificationsController {
  constructor(private notifications: NotificationsService) {}

  @Get()
  list(@CurrentUser() user: AuthUser) {
    return this.notifications.list(user.id);
  }

  @Post(':id/read')
  read(@CurrentUser() user: AuthUser, @Param('id') id: string) {
    return this.notifications.markRead(user.id, id);
  }
}

@Global()
@Module({
  controllers: [NotificationsController],
  providers: [NotificationsService],
  exports: [NotificationsService],
})
export class NotificationsModule {}
