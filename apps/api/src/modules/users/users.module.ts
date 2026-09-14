import { BadRequestException, Body, Controller, Delete, Get, Injectable, Module, Post, Put } from '@nestjs/common';
import { IsBoolean, IsIn, IsInt, IsOptional, IsString, Max, MaxLength, Min } from 'class-validator';
import { createHash } from 'crypto';
import * as bcrypt from 'bcryptjs';
import { PrismaService } from '../../prisma/prisma.service';
import { AuditService } from '../audit/audit.service';
import { CurrentUser, AuthUser } from '../../common/decorators/current-user.decorator';

class UpdateProfileDto {
  @IsOptional() @IsString() @MaxLength(120)
  fullName?: string;

  /** CPF é armazenado apenas como hash (antifraude de indicação). */
  @IsOptional() @IsString() @MaxLength(14)
  cpf?: string;

  @IsOptional() @IsInt() @Min(80) @Max(200)
  fontScale?: number;

  @IsOptional() @IsIn(['light', 'dark', 'system'])
  theme?: 'light' | 'dark' | 'system';
}
class ConsentDto {
  @IsIn(['marketing_email', 'web_push'])
  type: string;

  @IsBoolean()
  granted: boolean;
}
class DeleteDto {
  @IsString()
  password: string;
}

/** USER SERVICE + COMPLIANCE AGENT (seção 44 — LGPD). */
@Injectable()
export class UsersService {
  constructor(
    private prisma: PrismaService,
    private audit: AuditService,
  ) {}

  async updateProfile(userId: string, dto: UpdateProfileDto) {
    const { cpf, ...rest } = dto;
    return this.prisma.profile.update({
      where: { userId },
      data: { ...rest, ...(cpf ? { cpfHash: createHash('sha256').update(cpf.replace(/\D/g, '')).digest('hex') } : {}) },
    });
  }

  async consent(userId: string, dto: ConsentDto) {
    await this.prisma.consent.create({ data: { userId, type: dto.type, version: '2026-01', granted: dto.granted } });
    await this.audit.log({ actorId: userId, action: `consent.${dto.granted ? 'granted' : 'revoked'}`, metadata: { type: dto.type } });
  }

  /** Portabilidade: exporta todos os dados do titular. */
  async exportData(userId: string) {
    const [user, sessions, essays, notebook, subscriptions, payments, consents] = await Promise.all([
      this.prisma.user.findUniqueOrThrow({ where: { id: userId }, include: { profile: true } }),
      this.prisma.examSession.findMany({ where: { userId }, include: { result: true, answerSheet: { include: { answers: true } } } }),
      this.prisma.essay.findMany({ where: { userId }, include: { finalResult: true } }),
      this.prisma.errorNotebookEntry.findMany({ where: { userId } }),
      this.prisma.subscription.findMany({ where: { userId } }),
      this.prisma.payment.findMany({ where: { userId }, select: { id: true, amountCents: true, status: true, method: true, createdAt: true } }),
      this.prisma.consent.findMany({ where: { userId } }),
    ]);
    await this.audit.log({ actorId: userId, action: 'lgpd.export' });
    const { passwordHash: _p, mfaSecret: _m, ...safeUser } = user;
    return { exportedAt: new Date(), user: safeUser, sessions, essays, errorNotebook: notebook, subscriptions, payments, consents };
  }

  /** Exclusão (anonimização) — mantém registros financeiros exigidos por lei. */
  async deleteAccount(userId: string, password: string) {
    const user = await this.prisma.user.findUniqueOrThrow({ where: { id: userId } });
    if (!(await bcrypt.compare(password, user.passwordHash))) throw new BadRequestException('Senha incorreta');
    const active = await this.prisma.subscription.count({ where: { userId, status: { in: ['ACTIVE', 'TRIALING'] } } });
    if (active) throw new BadRequestException('Cancele sua assinatura antes de excluir a conta');

    await this.prisma.$transaction([
      this.prisma.user.update({
        where: { id: userId },
        data: { email: `deleted-${userId}@anon.invalid`, passwordHash: 'DELETED', isActive: false, deletedAt: new Date(), mfaSecret: null },
      }),
      this.prisma.profile.update({ where: { userId }, data: { fullName: 'Usuário removido', cpfHash: null, goal: null, mainDifficulty: null } }),
      this.prisma.refreshToken.updateMany({ where: { userId }, data: { revokedAt: new Date() } }),
      this.prisma.essay.updateMany({ where: { userId }, data: { draftText: null, finalText: null } }),
      this.prisma.auditLog.create({ data: { actorId: userId, action: 'lgpd.delete' } }),
    ]);
  }
}

@Controller('users')
export class UsersController {
  constructor(private users: UsersService) {}

  @Put('me/profile')
  profile(@CurrentUser() user: AuthUser, @Body() dto: UpdateProfileDto) {
    return this.users.updateProfile(user.id, dto);
  }

  @Post('me/consents')
  consent(@CurrentUser() user: AuthUser, @Body() dto: ConsentDto) {
    return this.users.consent(user.id, dto);
  }

  @Get('me/export')
  export(@CurrentUser() user: AuthUser) {
    return this.users.exportData(user.id);
  }

  @Delete('me')
  remove(@CurrentUser() user: AuthUser, @Body() dto: DeleteDto) {
    return this.users.deleteAccount(user.id, dto.password);
  }
}

@Module({ controllers: [UsersController], providers: [UsersService] })
export class UsersModule {}
