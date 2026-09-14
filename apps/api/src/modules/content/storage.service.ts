import { Injectable } from '@nestjs/common';
import { promises as fs, createReadStream } from 'fs';
import { join, dirname } from 'path';
import { env } from '../../config/env';

/**
 * Armazenamento de documentos oficiais. Interface compatível com S3;
 * a implementação padrão grava em disco (LOCAL_STORAGE_DIR) para desenvolvimento.
 * Para produção implemente `ObjectStorage` com o SDK S3 e troque o provider no módulo.
 */
export interface ObjectStorage {
  put(key: string, data: Buffer, contentType: string): Promise<void>;
  get(key: string): Promise<Buffer>;
  stream(key: string): NodeJS.ReadableStream;
  exists(key: string): Promise<boolean>;
}

@Injectable()
export class StorageService implements ObjectStorage {
  private root = env.LOCAL_STORAGE_DIR;

  private path(key: string) {
    if (key.includes('..')) throw new Error('Chave inválida');
    return join(this.root, key);
  }

  async put(key: string, data: Buffer, _contentType?: string): Promise<void> {
    const p = this.path(key);
    await fs.mkdir(dirname(p), { recursive: true });
    await fs.writeFile(p, data);
  }

  async get(key: string): Promise<Buffer> {
    return fs.readFile(this.path(key));
  }

  stream(key: string): NodeJS.ReadableStream {
    return createReadStream(this.path(key));
  }

  async exists(key: string): Promise<boolean> {
    try {
      await fs.access(this.path(key));
      return true;
    } catch {
      return false;
    }
  }
}
