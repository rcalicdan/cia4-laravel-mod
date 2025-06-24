<?php

namespace Rcalicdan\Ci4Larabridge\Exceptions;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Throwable;

class QueueExceptionHandler implements ExceptionHandler
{
    public function shouldReport(Throwable $e): bool
    {
        return true;
    }

    public function report(Throwable $e): void
    {
        log_message('error', 'Queue job exception: '.$e->getMessage(), [
            'exception' => $e,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    public function render($request, Throwable $e)
    {
        return null;
    }

    public function renderForConsole($output, Throwable $e): void
    {
        if ($output && method_exists($output, 'writeln')) {
            $output->writeln('<error>Queue Exception: '.$e->getMessage().'</error>');
            $output->writeln('<comment>File: '.$e->getFile().':'.$e->getLine().'</comment>');
        }
    }
}
