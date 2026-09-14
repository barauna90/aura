import { Injectable } from '@nestjs/common';
import { Prisma } from '@prisma/client';
import { PrismaService } from '../../prisma/prisma.service';

/**
 * AUDIT SERVICE — toda ação sensível (conteúdo, financeiro, acesso) passa por aqui.
 * Registros de auditoria nunca são removidos.
 */
@Injectable()
export class AuditService {
  constructor(private prisma: PrismaService) {}

  log(params: {
    actorId?: string | null;
    action: string;
    entityType?: string;
    entityId?: string;
    metadata?: Prisma.InputJsonValue;
    ip?: string;
  }) {
    return this.prisma.auditLog.create({
      data: {
        actorId: params.actorId ?? null,
        action: params.action,
        entityType: params.entityType,
        entityId: params.entityId,
        metadata: params.metadata,
        ip: params.ip,
      },
    });
  }

  /** Alerta administrativo (observabilidade — seção 45). */
  alert(level: 'INFO' | 'WARN' | 'ERROR', source: string, message: string, metadata?: Prisma.InputJsonValue) {
    return this.prisma.systemAlert.create({ data: { level, source, message, metadata } });
  }
}
