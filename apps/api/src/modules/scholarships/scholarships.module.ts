import { BadRequestException, Body, Controller, Get, Injectable, Module, NotFoundException, Param, Post } from '@nestjs/common';
import { IsBoolean, IsEmail, IsIn, IsInt, IsOptional, IsString, MaxLength, Min } from 'class-validator';
import { PrismaService } from '../../prisma/prisma.service';
import { AuditService } from '../audit/audit.service';
import { Roles } from '../../common/decorators/roles.decorator';
import { CurrentUser, AuthUser } from '../../common/decorators/current-user.decorator';

/** Durações previstas (seção 34). null = acesso integral gratuito. */
export const SCHOLARSHIP_DURATIONS = [30, 90, 180, 365, null] as const;

class GrantDto {
  @IsEmail()
  email: string;

  @IsOptional() @IsIn([30, 90, 180, 365])
  days?: 30 | 90 | 180 | 365;

  @IsOptional() @IsBoolean()
  unlimited?: boolean;

  @IsOptional() @IsString()
  sponsorId?: string;

  @IsOptional() @IsString() @MaxLength(300)
  reason?: string;
}
class SponsorDto {
  @IsString() @MaxLength(120)
  name: string;

  @IsInt() @Min(1)
  seats: number;
}

@Injectable()
export class ScholarshipsService {
  constructor(
    private prisma: PrismaService,
    private audit: AuditService,
  ) {}

  async grant(dto: GrantDto, actorId: string) {
    const user = await this.prisma.user.findUnique({ where: { email: dto.email.toLowerCase() } });
    if (!user) throw new NotFoundException('Usuário não encontrado');
    if (!dto.unlimited && !dto.days) throw new BadRequestException('Informe a duração ou acesso integral');

    return this.prisma.$transaction(async (tx) => {
      if (dto.sponsorId) {
        const sponsor = await tx.sponsor.findUnique({ where: { id: dto.sponsorId } });
        if (!sponsor || !sponsor.active) throw new NotFoundException('Patrocinador não encontrado');
        if (sponsor.usedSeats >= sponsor.seats) throw new BadRequestException('Todas as vagas patrocinadas foram preenchidas');
        await tx.sponsor.update({ where: { id: sponsor.id }, data: { usedSeats: { increment: 1 } } });
      }
      const s = await tx.scholarship.create({
        data: {
          userId: user.id,
          sponsorId: dto.sponsorId,
          days: dto.unlimited ? null : dto.days,
          endsAt: dto.unlimited ? null : new Date(Date.now() + dto.days! * 86_400_000),
          grantedBy: actorId,
          reason: dto.reason,
        },
      });
      await tx.auditLog.create({ data: { actorId, action: 'scholarship.granted', entityType: 'Scholarship', entityId: s.id, metadata: { userId: user.id, days: dto.days ?? null } } });
      await tx.notification.create({
        data: { userId: user.id, channel: 'IN_APP', kind: 'SCHOLARSHIP', title: 'Bolsa de estudos concedida', body: dto.unlimited ? 'Você recebeu acesso integral gratuito à plataforma.' : `Você recebeu uma bolsa de ${dto.days} dias.` },
      });
      return s;
    });
  }

  async revoke(id: string, actorId: string) {
    await this.prisma.scholarship.update({ where: { id }, data: { active: false } });
    await this.audit.log({ actorId, action: 'scholarship.revoked', entityType: 'Scholarship', entityId: id });
  }

  list() {
    return this.prisma.scholarship.findMany({ include: { user: { select: { email: true, profile: { select: { fullName: true } } } }, sponsor: true }, orderBy: { startsAt: 'desc' } });
  }

  sponsors() {
    return this.prisma.sponsor.findMany({ orderBy: { createdAt: 'desc' } });
  }

  async createSponsor(dto: SponsorDto, actorId: string) {
    const s = await this.prisma.sponsor.create({ data: dto });
    await this.audit.log({ actorId, action: 'sponsor.created', entityType: 'Sponsor', entityId: s.id });
    return s;
  }
}

@Controller('admin/scholarships')
@Roles('ADMIN')
export class ScholarshipsController {
  constructor(private scholarships: ScholarshipsService) {}

  @Get()
  list() {
    return this.scholarships.list();
  }

  @Post()
  grant(@Body() dto: GrantDto, @CurrentUser() user: AuthUser) {
    return this.scholarships.grant(dto, user.id);
  }

  @Post(':id/revoke')
  revoke(@Param('id') id: string, @CurrentUser() user: AuthUser) {
    return this.scholarships.revoke(id, user.id);
  }

  @Get('sponsors')
  sponsors() {
    return this.scholarships.sponsors();
  }

  @Post('sponsors')
  createSponsor(@Body() dto: SponsorDto, @CurrentUser() user: AuthUser) {
    return this.scholarships.createSponsor(dto, user.id);
  }
}

@Module({
  controllers: [ScholarshipsController],
  providers: [ScholarshipsService],
})
export class ScholarshipsModule {}
