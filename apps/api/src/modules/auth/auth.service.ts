import { BadRequestException, ConflictException, Injectable, UnauthorizedException } from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';
import { createHash, randomBytes } from 'crypto';
import * as bcrypt from 'bcryptjs';
import { PrismaService } from '../../prisma/prisma.service';
import { AuditService } from '../audit/audit.service';
import { env } from '../../config/env';
import { LoginDto, RegisterDto } from './auth.dto';
import { ReferralService } from '../referrals/referral.service';

const TERMS_VERSION = '2026-01';

@Injectable()
export class AuthService {
  constructor(
    private prisma: PrismaService,
    private jwt: JwtService,
    private audit: AuditService,
    private referrals: ReferralService,
  ) {}

  async register(dto: RegisterDto, meta: { ip?: string; userAgent?: string }) {
    if (!dto.acceptTerms || !dto.acceptPrivacy) {
      throw new BadRequestException('É necessário aceitar os termos de uso e a política de privacidade');
    }
    const email = dto.email.toLowerCase().trim();
    const exists = await this.prisma.user.findUnique({ where: { email } });
    if (exists) throw new ConflictException('E-mail já cadastrado');

    const referredBy = dto.referralCode
      ? await this.prisma.user.findUnique({ where: { referralCode: dto.referralCode.toUpperCase() } })
      : null;

    const passwordHash = await bcrypt.hash(dto.password, 12);
    const user = await this.prisma.user.create({
      data: {
        email,
        passwordHash,
        referralCode: await this.generateReferralCode(dto.fullName),
        referredById: referredBy?.id ?? null,
        profile: { create: { fullName: dto.fullName.trim() } },
        consents: {
          create: [
            { type: 'terms', version: TERMS_VERSION, granted: true },
            { type: 'privacy', version: TERMS_VERSION, granted: true },
          ],
        },
      },
    });

    if (referredBy) {
      await this.referrals.registerSignup(referredBy.id, user.id, meta);
    }
    await this.audit.log({ actorId: user.id, action: 'auth.register', entityType: 'User', entityId: user.id, ip: meta.ip });
    return this.issueTokens(user.id, user.email, user.role, meta);
  }

  async login(dto: LoginDto, meta: { ip?: string; userAgent?: string }) {
    const user = await this.prisma.user.findUnique({ where: { email: dto.email.toLowerCase().trim() } });
    if (!user || user.deletedAt || !user.isActive) throw new UnauthorizedException('Credenciais inválidas');
    const ok = await bcrypt.compare(dto.password, user.passwordHash);
    if (!ok) {
      await this.audit.log({ actorId: user.id, action: 'auth.login_failed', ip: meta.ip });
      throw new UnauthorizedException('Credenciais inválidas');
    }
    await this.audit.log({ actorId: user.id, action: 'auth.login', ip: meta.ip });
    return this.issueTokens(user.id, user.email, user.role, meta);
  }

  async refresh(refreshToken: string, meta: { ip?: string; userAgent?: string }) {
    let payload: { sub: string };
    try {
      payload = await this.jwt.verifyAsync(refreshToken, { secret: env.JWT_REFRESH_SECRET });
    } catch {
      throw new UnauthorizedException('Refresh token inválido');
    }
    const tokenHash = this.hash(refreshToken);
    const stored = await this.prisma.refreshToken.findUnique({ where: { tokenHash } });
    if (!stored || stored.revokedAt || stored.expiresAt < new Date() || stored.userId !== payload.sub) {
      throw new UnauthorizedException('Refresh token revogado');
    }
    // Rotação: revoga o token usado e emite um novo par.
    await this.prisma.refreshToken.update({ where: { id: stored.id }, data: { revokedAt: new Date() } });
    const user = await this.prisma.user.findUniqueOrThrow({ where: { id: stored.userId } });
    return this.issueTokens(user.id, user.email, user.role, meta);
  }

  async logout(refreshToken: string) {
    const tokenHash = this.hash(refreshToken);
    await this.prisma.refreshToken.updateMany({ where: { tokenHash, revokedAt: null }, data: { revokedAt: new Date() } });
  }

  async me(userId: string) {
    const user = await this.prisma.user.findUniqueOrThrow({
      where: { id: userId },
      include: { profile: true },
    });
    return {
      id: user.id,
      email: user.email,
      role: user.role,
      referralCode: user.referralCode,
      profile: user.profile,
    };
  }

  private async issueTokens(userId: string, email: string, role: string, meta: { ip?: string; userAgent?: string }) {
    const accessToken = await this.jwt.signAsync(
      { sub: userId, email, role },
      { secret: env.JWT_ACCESS_SECRET, expiresIn: env.JWT_ACCESS_TTL as unknown as number },
    );
    const refreshToken = await this.jwt.signAsync(
      { sub: userId, jti: randomBytes(16).toString('hex') },
      { secret: env.JWT_REFRESH_SECRET, expiresIn: `${env.JWT_REFRESH_TTL_DAYS}d` as unknown as number },
    );
    await this.prisma.refreshToken.create({
      data: {
        userId,
        tokenHash: this.hash(refreshToken),
        expiresAt: new Date(Date.now() + env.JWT_REFRESH_TTL_DAYS * 86_400_000),
        ip: meta.ip,
        userAgent: meta.userAgent?.slice(0, 255),
      },
    });
    return { accessToken, refreshToken };
  }

  private hash(value: string) {
    return createHash('sha256').update(value).digest('hex');
  }

  private async generateReferralCode(fullName: string): Promise<string> {
    const base = fullName
      .normalize('NFD')
      .replace(/[^a-zA-Z]/g, '')
      .toUpperCase()
      .slice(0, 6) || 'ALUNO';
    for (let i = 0; i < 10; i++) {
      const code = `${base}${Math.floor(100 + Math.random() * 900)}`;
      const taken = await this.prisma.user.findUnique({ where: { referralCode: code } });
      if (!taken) return code;
    }
    return `${base}${randomBytes(3).toString('hex').toUpperCase()}`;
  }
}
