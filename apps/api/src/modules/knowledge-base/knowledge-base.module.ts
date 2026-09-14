import { Controller, Get, Injectable, Module, Query } from '@nestjs/common';
import { DISCLAIMERS } from '@sip-enem/shared';
import { PrismaService } from '../../prisma/prisma.service';

/**
 * RAG / BASE OFICIAL (seção 47). Busca lexical simples sobre trechos de
 * documentos oficiais VERIFIED. Se nada for encontrado, a resposta é
 * exatamente "Nenhuma informação oficial validada foi encontrada na base."
 * Substitua por busca vetorial (pgvector) mantendo o mesmo contrato.
 */
@Injectable()
export class KnowledgeBaseService {
  constructor(private prisma: PrismaService) {}

  async search(query: string, limit = 5) {
    const terms = query
      .toLowerCase()
      .normalize('NFD')
      .replace(/[̀-ͯ]/g, '')
      .split(/\W+/)
      .filter((t) => t.length > 3);
    if (!terms.length) return { found: false, message: DISCLAIMERS.NO_OFFICIAL_INFO, chunks: [] };

    const chunks = await this.prisma.officialDocumentChunk.findMany({
      where: {
        document: { reviewStatus: 'VERIFIED' },
        OR: terms.map((t) => ({ content: { contains: t, mode: 'insensitive' as const } })),
      },
      include: { document: { select: { title: true, year: true, kind: true, version: true, source: { select: { sourceUrl: true } } } } },
      take: 50,
    });
    const scored = chunks
      .map((c) => ({ c, score: terms.filter((t) => c.content.toLowerCase().includes(t)).length }))
      .sort((a, b) => b.score - a.score)
      .slice(0, limit);
    if (!scored.length) return { found: false, message: DISCLAIMERS.NO_OFFICIAL_INFO, chunks: [] };
    return {
      found: true,
      chunks: scored.map(({ c }) => ({ content: c.content, page: c.page, document: c.document })),
    };
  }
}

@Controller('knowledge-base')
export class KnowledgeBaseController {
  constructor(private kb: KnowledgeBaseService) {}

  @Get('search')
  search(@Query('q') q: string) {
    return this.kb.search(q ?? '');
  }
}

@Module({
  controllers: [KnowledgeBaseController],
  providers: [KnowledgeBaseService],
  exports: [KnowledgeBaseService],
})
export class KnowledgeBaseModule {}
