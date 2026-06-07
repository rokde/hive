<?php

declare(strict_types=1);

namespace Hive\Exception;

use Throwable;

/**
 * A handler for a specific exception class.
 *
 * The ExceptionHandler finds all handlers whose registered class
 * matches the exception or is a superclass of it.
 *
 * The handler can:
 * - Log additional details
 * - Transform/re-throw the exception
 * - Generate a response (HTTP/CLI)
 * - Modify state
 * All via side effects (void return type).
 */
interface HandlerInterface
{
    /**
     * Handle the exception.
     *
     * @param  Throwable  $exception  The exception to be handled.
     *
     * @throws Throwable The handler can re-throw the exception or throw it in a transformed form.
     */
    public function handle(Throwable $exception): void;
}
