import { Transform, Type } from 'class-transformer';
import {
  ArrayMinSize,
  IsArray,
  IsBoolean,
  IsEnum,
  IsInt,
  IsOptional,
  IsString,
  IsUrl,
  Max,
  MaxLength,
  Min,
  ValidateNested,
} from 'class-validator';
import { AnswerOption, BookletColor, ExamApplication, ExamArea, ForeignLanguage, PipelineStage } from '@prisma/client';

/** Multipart envia booleanos como string ("true"/"false"). */
const toBool = ({ value }: { value: unknown }) => (typeof value === 'string' ? value === 'true' || value === '1' : value);

export class CreateExamDto {
  @IsInt() @Min(1998) @Max(2100)
  @Type(() => Number)
  year: number;

  @IsEnum(ExamApplication)
  application: ExamApplication;

  @IsInt() @Min(1) @Max(2)
  @Type(() => Number)
  day: number;

  @IsString() @MaxLength(160)
  title: string;

  /** Duração oficial daquela edição/dia, em minutos. Sem valor universal. */
  @IsInt() @Min(30) @Max(600)
  @Type(() => Number)
  durationMinutes: number;

  /** Aceita array JSON ou string separada por vírgula (multipart). */
  @Transform(({ value }) => (typeof value === 'string' ? value.split(',').map((v) => v.trim()) : value))
  @IsArray() @IsEnum(ExamArea, { each: true })
  areas: ExamArea[];

  @IsOptional() @IsBoolean()
  @Transform(toBool)
  hasEssay?: boolean;

  @IsOptional() @IsBoolean()
  @Transform(toBool)
  hasForeignLanguage?: boolean;

  @IsOptional() @IsString() @MaxLength(500)
  structureNote?: string;

  @IsUrl({ require_tld: false })
  sourceUrl: string;

  @IsString() @MaxLength(60)
  documentVersion: string;

  @IsOptional() @IsBoolean()
  @Transform(toBool)
  isFreeSample?: boolean;
}

export class CreateBookletDto {
  @IsEnum(BookletColor)
  color: BookletColor;

  @IsString() @MaxLength(80)
  label: string;

  @IsInt() @Min(1)
  @Type(() => Number)
  pageCount: number;

  @IsUrl({ require_tld: false })
  sourceUrl: string;

  @IsString() @MaxLength(60)
  documentVersion: string;
}

export class AnswerKeyEntryDto {
  @IsInt() @Min(1) @Max(300)
  number: number;

  @IsEnum(ExamArea)
  area: ExamArea;

  @IsOptional() @IsEnum(AnswerOption)
  correct?: AnswerOption | null;

  @IsOptional() @IsBoolean()
  annulled?: boolean;

  @IsOptional() @IsEnum(ForeignLanguage)
  foreignLanguage?: ForeignLanguage;

  @IsOptional() @IsInt() @Min(1)
  page?: number;
}

export class RegisterAnswerKeyDto {
  @IsUrl({ require_tld: false })
  sourceUrl: string;

  @IsString() @MaxLength(60)
  documentVersion: string;

  @IsArray() @ArrayMinSize(1) @ValidateNested({ each: true })
  @Type(() => AnswerKeyEntryDto)
  answers: AnswerKeyEntryDto[];
}

export class RegisterEssayPromptDto {
  @IsString() @MaxLength(300)
  theme: string;

  /** Textos motivadores transcritos do documento oficial (revisão obrigatória). */
  @IsArray()
  motivatingTexts: Array<{ title?: string; body: string; sourceNote?: string }>;

  @IsUrl({ require_tld: false })
  sourceUrl: string;

  @IsString() @MaxLength(60)
  documentVersion: string;

  @IsOptional() @IsInt() @Min(10) @Max(60)
  maxLines?: number;
}

export class AdvanceStageDto {
  @IsEnum(PipelineStage)
  target: PipelineStage;
}

export class ChangeAnswerDto {
  @IsOptional() @IsEnum(AnswerOption)
  correct: AnswerOption | null;

  @IsBoolean()
  annulled: boolean;

  @IsString() @MaxLength(500)
  reason: string;
}

export class ChangeExamDto {
  @IsOptional() @IsInt() @Min(30) @Max(600)
  durationMinutes?: number;

  @IsOptional() @IsString() @MaxLength(160)
  title?: string;

  @IsOptional() @IsString() @MaxLength(500)
  structureNote?: string;

  @IsString() @MaxLength(500)
  reason: string;
}

export class RejectDto {
  @IsString() @MaxLength(500)
  reason: string;
}

export class ZeroRuleDto {
  @IsString() @MaxLength(40)
  code: string;

  @IsString() @MaxLength(400)
  description: string;

  @IsUrl({ require_tld: false })
  sourceUrl: string;
}
