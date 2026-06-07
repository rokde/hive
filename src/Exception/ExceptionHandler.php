<?php

declare(strict_types=1);

namespace Hive\Exception;

use ErrorException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Centralized exception handling with PSR-3 logging and a handler registry.
 *
 * Process when handle($exception) is called:
 * 1. Log the exception using the PSR-3 logger (error level + context)
 * 2. Find all matching handlers (exact class + inheritance)
 * 3. Call all matching handlers (they do their thing: render, transform, etc.)
 *
 * Example:
 *   $handler = new ExceptionHandler($logger);
 *   $handler->register(UserNotFoundException::class, new NotFoundHandler());
 *   $handler->handle($exception);
 */
final class ExceptionHandler
{
    /** @var array<class-string, list<HandlerInterface>> */
    private array $handlers = [];

    /** @var callable|null Der alte Exception-Handler vor registerAsGlobal() */
    private mixed $previousExceptionHandler = null;

    /** @var callable|null Der alte Error-Handler vor registerAsGlobal() */
    private mixed $previousErrorHandler = null;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
        $this->registerAsGlobal();
    }

    // -------------------------------------------------------------------------
    // Global Handler Registration
    // -------------------------------------------------------------------------

    /**
     * Registers this handler as the global PHP exception/error handler.
     *
     * The following errors are converted to exceptions and handled:
     * - E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR (fatal)
     * - E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING (warnings)
     * - E_NOTICE, E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE (notices)
     *
     * Exceptions: E_DEPRECATED, E_STRICT (too noisy)
     *
     * Idempotent: multiple calls are safe.
     */
    public function registerAsGlobal(): self
    {
        if ($this->previousExceptionHandler !== null) {
            return $this; // bereits registriert
        }

        // Exception Handler
        $this->previousExceptionHandler = set_exception_handler($this->handle(...));

        // Error Handler: bestimmte Error-Level zu Exception konvertieren
        $errorMask = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR |
            E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING |
            E_NOTICE | E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE;

        $this->previousErrorHandler = set_error_handler(
            function (int $errno, string $errstr, string $errfile, int $errline) use ($errorMask): bool {
                // Nur Error-Level handle, die in unserer Maske sind
                if (($errno & $errorMask) === 0) {
                    return false; // an vorherigen Handler delegieren
                }

                // Error zu ErrorException konvertieren und handle
                $exception = new ErrorException($errstr, 0, $errno, $errfile, $errline);
                $this->handle($exception);

                return true; // handled
            },
            $errorMask,
        );

        return $this;
    }

    /**
     * Restores the previous exception/error handlers.
     * Safe even if `registerAsGlobal()` has not been called.
     */
    public function restoreHandlers(): self
    {
        if ($this->previousExceptionHandler !== null) {
            set_exception_handler($this->previousExceptionHandler);
            $this->previousExceptionHandler = null;
        }

        if ($this->previousErrorHandler !== null) {
            set_error_handler($this->previousErrorHandler);
            $this->previousErrorHandler = null;
        }

        return $this;
    }

    /**
     * Registers a handler for an exception class.
     * The handler is called when exceptions of this class or its subclasses occur.
     *
     * @param  class-string  $exceptionClass
     */
    public function register(string $exceptionClass, HandlerInterface $handler): self
    {
        if (! isset($this->handlers[$exceptionClass])) {
            $this->handlers[$exceptionClass] = [];
        }

        $this->handlers[$exceptionClass][] = $handler;

        return $this;
    }

    /**
     * Handle an exception.
     *
     * 1. Log (PSR-3, error level)
     * 2. Invoke all matching handlers
     *
     * Handlers can rethrow the exception (including transformed versions).
     */
    public function handle(Throwable $exception): void
    {
        $this->log($exception);
        $this->dispatch($exception);
    }

    private function log(Throwable $exception): void
    {
        $this->logger->error(
            $exception->getMessage(),
            [
                'exception' => $exception,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'code' => $exception->getCode(),
                'class' => $exception::class,
            ],
        );
    }

    /**
     * Find and call all matching handlers.
     */
    private function dispatch(Throwable $exception): void
    {
        foreach ($this->findHandlers($exception) as $handler) {
            $handler->handle($exception);
        }
    }

    /**
     * Finds handlers that match this exception (exact class or inheritance).
     *
     * @return list<HandlerInterface>
     */
    private function findHandlers(Throwable $exception): array
    {
        $matching = [];
        $exceptionClass = $exception::class;

        foreach ($this->handlers as $registeredClass => $handlers) {
            if ($exceptionClass === $registeredClass || is_a($exceptionClass, $registeredClass, true)) {
                $matching = [...$matching, ...$handlers];
            }
        }

        return $matching;
    }
}
