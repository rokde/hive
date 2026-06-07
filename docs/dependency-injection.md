# Core\Container

PSR-11 DI Container with auto-wiring, deferred instantiation (lazy by default),
attribute-based injection, circular dependency detection, and service providers.

## Installation

```bash
composer require psr/container
# Copy code from src/Container/ to your project (PSR-4: Core\Container\)
```

Requires PHP >= 8.3. Runs unchanged on 8.5.

## Concepts

| Term | Meaning |
|---|---|
| **Lazy by default** | `ServiceDefinition` is stored at `register()`, instantiation happens on first `get()`. No proxy — the returned instance is real. |
| **Singleton** | A shared instance per container. |
| **Transient** | New instance on each `get()` (default). |
| **eager** | Definitions marked with `->asEager()` are instantiated immediately on `boot()` (for boot-side-effects). |

## Registration

```php
use Core\Container\Container;
use Core\Container\Lifetime;
use Core\Container\ServiceDefinition;

$c = new Container();

$c->singleton(UserService::class);              // shared, auto-wired
$c->bind(Mailer::class);                         // transient, auto-wired
$c->alias(LoggerInterface::class, FileLogger::class); // Interface -> Impl
$c->instance('config.version', '1.0');           // ready value
$c->factory(Pdo::class, fn($c) => new Pdo(...), Lifetime::Singleton);

// Low-level for full control:
$c->define(Report::class,
    ServiceDefinition::forClass(Report::class, Lifetime::Singleton)->asEager()
);
```

## Auto-Wiring & Attributes

Parameters are resolved in this order:

1. `#[Inject('id')]` — explicit service ID
2. `#[Config('key', default: ...)]` — value from ConfigResolver (dot notation)
3. Class/interface type hint — resolved recursively
4. PHP default value of the parameter
5. `null` for nullable type
6. otherwise `ContainerException`

```php
use Core\Container\Attribute\Config;
use Core\Container\Attribute\Inject;

final class Connection {
    public function __construct(
        #[Config('db.host')] string $host,
        #[Config('db.port', default: 5432)] int $port,
        #[Inject('logger.audit')] LoggerInterface $logger,
        Repository $repo, // auto-wired per type hint
    ) {}
}
```

`default: null` is correctly distinguished from "no default" (via
`ReflectionAttribute::getArguments()`).

## Config

```php
use Core\Container\ArrayConfigResolver;

$config = new ArrayConfigResolver(['db' => ['host' => 'localhost', 'port' => 5432]]);
$c = new Container($config);
```

Custom source: implement `ConfigResolverInterface` (e.g. ENV, file, vault).

## Method / Callable Injection

```php
$c->call([$controller, 'show'], ['id' => 42]); // param injected, 'id' overridden
$c->call(fn(Mailer $m) => $m->send());
$c->call('App\\Jobs\\Cleanup');                 // invokable class (__invoke)
$c->call(SomeClass::class . '::staticMethod');
```

## ServiceProvider

```php
use Core\Container\BootableServiceProviderInterface;
use Core\Container\Container;

final class DatabaseProvider implements BootableServiceProviderInterface {
    public function register(Container $c): void {
        $c->singleton(Pdo::class, fn($c) => new Pdo(/* ... */));
    }
    public function boot(Container $c): void {
        // runs after all register() — services may be resolved here
    }
}

$c->addProvider(new DatabaseProvider());
$c->boot(); // register phase -> boot phase -> eager services
```

`ServiceProviderInterface` (only `register()`) is sufficient if no boot phase is needed.

## Exceptions

- `NotFoundException` (PSR-11 `NotFoundExceptionInterface`) — ID unknown
- `ContainerException` (PSR-11 `ContainerExceptionInterface`) — cannot be resolved
- `CircularDependencyException` (extends `ContainerException`) — with path A -> B -> A

## Intentional Design Decisions

- **No Lazy Objects (PHP 8.4):** Lazy = deferred instantiation on first
  `get()`, no ghost/proxy. Deliberately omitted for simplicity. Can be added later
  (extend definition with proxy flag, use `ReflectionClass::newLazyGhost()` in `autowire()`).
- **Zero-Config:** Unregistered, instantiable classes are automatically resolved as
  transient class definitions. `has()` returns `true` for them.
- **Reflection Cache:** `ReflectionClass` instances are cached per class.
- **Variadic parameters** are not auto-filled (possible via `call()` override).
- **Union/intersection types** without `#[Inject]`/default are ambiguous and
  throw `ContainerException`.
