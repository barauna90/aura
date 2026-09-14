import { IsBoolean, IsEmail, IsOptional, IsString, MaxLength, MinLength } from 'class-validator';

export class RegisterDto {
  @IsEmail()
  email: string;

  @IsString()
  @MinLength(8, { message: 'A senha deve ter pelo menos 8 caracteres' })
  @MaxLength(128)
  password: string;

  @IsString()
  @MinLength(2)
  @MaxLength(120)
  fullName: string;

  /** Código de indicação (plataforma.com.br/r/CODIGO) */
  @IsOptional()
  @IsString()
  @MaxLength(32)
  referralCode?: string;

  @IsBoolean()
  acceptTerms: boolean;

  @IsBoolean()
  acceptPrivacy: boolean;
}

export class LoginDto {
  @IsEmail()
  email: string;

  @IsString()
  password: string;
}

export class RefreshDto {
  @IsString()
  refreshToken: string;
}
