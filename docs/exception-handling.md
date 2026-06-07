## Exception Handling (PSR-3 Logger)

```php
use Core\Container\Exception\ExceptionHandler;
use Core\Container\Exception\HandlerInterface;

// ExceptionHandler with PSR-3 Logger
$handler = new ExceptionHandler($logger);

// Register handlers for specific exception classes
$handler->register(UserNotFoundException::class, new NotFoundHandler());
$handler->register(ValidationException::class, new ValidationHandler());

// When an exception occurs: log + call all matching handlers
$handler->handle($exception);
```

**Flow when `handle($exception)` is called:**
1. Log the exception with PSR-3 logger (error level with context)
2. Find all handlers whose registered class is the exception or a superclass
3. Call all matching handlers (they perform side-effects: rendering, transformation, etc.)

Handler interface:
```php
interface HandlerInterface {
    public function handle(Throwable $exception): void;
}
```

Handlers can re-throw the exception (possibly transformed), generate responses (HTTP/CLI), log additional details — all through side-effects.

**Logger:** The container automatically provides a PSR-3-compliant `LoggerInterface`. If not explicitly set via `Application::withLogger()`, a `NullLogger` (no-op) is used.
