import { ArgumentsHost, Catch, ExceptionFilter, HttpException, HttpStatus, Logger } from '@nestjs/common';

@Catch()
export class AllExceptionsFilter implements ExceptionFilter {
  private readonly logger = new Logger('Exceptions');

  catch(exception: unknown, host: ArgumentsHost) {
    const ctx = host.switchToHttp();
    const res = ctx.getResponse();
    const req = ctx.getRequest();
    const status =
      exception instanceof HttpException ? exception.getStatus() : HttpStatus.INTERNAL_SERVER_ERROR;
    const body =
      exception instanceof HttpException
        ? exception.getResponse()
        : { statusCode: status, message: 'Erro interno' };

    if (status >= 500) {
      this.logger.error(`${req.method} ${req.url}`, (exception as Error)?.stack);
    }
    res.status(status).json(typeof body === 'string' ? { statusCode: status, message: body } : body);
  }
}
