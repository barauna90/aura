import { CanActivate, ExecutionContext, ForbiddenException, Injectable } from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import { UserRole } from '@prisma/client';
import { ROLES_KEY } from '../decorators/roles.decorator';

/** RBAC hierárquico: um papel superior herda as permissões dos inferiores. */
export const ROLE_HIERARCHY: Record<UserRole, number> = {
  STUDENT: 0,
  REVIEWER: 1,
  ADMIN: 2,
  SUPER_ADMIN: 3,
};

@Injectable()
export class RolesGuard implements CanActivate {
  constructor(private reflector: Reflector) {}

  canActivate(ctx: ExecutionContext): boolean {
    const required = this.reflector.getAllAndOverride<UserRole[]>(ROLES_KEY, [
      ctx.getHandler(),
      ctx.getClass(),
    ]);
    if (!required || required.length === 0) return true;
    const user = ctx.switchToHttp().getRequest().user;
    if (!user) throw new ForbiddenException();
    const minimum = Math.min(...required.map((r) => ROLE_HIERARCHY[r]));
    if (ROLE_HIERARCHY[user.role as UserRole] < minimum) {
      throw new ForbiddenException('Permissão insuficiente');
    }
    return true;
  }
}
