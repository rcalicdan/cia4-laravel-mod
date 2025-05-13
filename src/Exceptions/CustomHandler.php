<?php

namespace Rcalicdan\Ci4Larabridge\Exceptions;

use CodeIgniter\Debug\ExceptionHandler as DefaultHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Exceptions as ExceptionsConfig;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

class CustomHandler implements ExceptionHandlerInterface
{
    /**
     * The Exceptions configuration instance.
     */
    protected ExceptionsConfig $config;

    /**
     * Constructor that requires the Exceptions config.
     */
    public function __construct(ExceptionsConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Handles the exception. Will instantiate the default handler
     * and delegate the actual processing to it, potentially with a
     * transformed exception type.
     *
     * @param  Throwable  $exception  The exception instance.
     * @param  RequestInterface  $request  The current request.
     * @param  ResponseInterface  $response  The current response.
     * @param  int  $statusCode  The HTTP status code suggested.
     * @param  int  $exitCode  The exit status code.
     */
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode
    ): void {
        $defaultHandler = new DefaultHandler($this->config);

        if ($exception instanceof ModelNotFoundException) {
            $modelName = class_basename($exception->getModel());
            $ci4Exception = PageNotFoundException::forPageNotFound(
                "The requested {$modelName} not be found."
            );

            $defaultHandler->handle($ci4Exception, $request, $response, 404, $exitCode);
        } else {
            $defaultHandler->handle($exception, $request, $response, $statusCode, $exitCode);
        }
    }
}
