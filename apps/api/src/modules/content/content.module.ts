import { Module } from '@nestjs/common';
import { ContentController } from './content.controller';
import { ContentAdminController } from './content-admin.controller';
import { ContentImportService } from './content-import.service';
import { ExamsService } from './exams.service';
import { OfficialContentGuardianService } from './guardian.service';
import { StorageService } from './storage.service';

@Module({
  controllers: [ContentController, ContentAdminController],
  providers: [ContentImportService, ExamsService, OfficialContentGuardianService, StorageService],
  exports: [ExamsService, StorageService, OfficialContentGuardianService],
})
export class ContentModule {}
