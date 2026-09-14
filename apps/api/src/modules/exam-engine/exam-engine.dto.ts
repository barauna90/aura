import { Type } from 'class-transformer';
import { ArrayMaxSize, IsArray, IsBoolean, IsEnum, IsInt, IsOptional, IsString, MaxLength, Min, ValidateNested } from 'class-validator';
import { AnswerOption, ExamArea, ForeignLanguage, SessionMode } from '@prisma/client';

export class CreateSessionDto {
  @IsString()
  examId: string;

  @IsString()
  bookletId: string;

  @IsEnum(SessionMode)
  mode: SessionMode;

  @IsOptional() @IsEnum(ForeignLanguage)
  language?: ForeignLanguage;

  /** Somente MODO ESTUDO pode limitar áreas. */
  @IsOptional() @IsArray() @IsEnum(ExamArea, { each: true })
  selectedAreas?: ExamArea[];

  @IsOptional() @IsString() @MaxLength(40)
  device?: string;
}

export class AnswerEntryDto {
  @IsString()
  questionId: string;

  @IsOptional() @IsEnum(AnswerOption)
  option: AnswerOption | null;

  @IsOptional() @IsInt() @Min(0)
  timeSpentSec?: number;
}

export class SaveAnswersDto {
  @IsArray() @ArrayMaxSize(200) @ValidateNested({ each: true })
  @Type(() => AnswerEntryDto)
  answers: AnswerEntryDto[];
}

export class NoteDto {
  @IsOptional() @IsString()
  questionId?: string;

  @IsString() @MaxLength(2000)
  body: string;

  @IsOptional() @IsBoolean()
  flagged?: boolean;
}
