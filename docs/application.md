## Application (Bootstrapping)

Composition over inheritance: the `Application` owns a `Container` and
only orchestrates its setup (cf. Laravel's `bootstrap/app.php`).

```php
use Core\Container\Application;

$app = Application::configure()
    ->withConfig(['db' => ['host' => 'localhost', 'port' => 5432]]) // array or ConfigResolverInterface
    ->withProviders([
        DatabaseProvider::class,   // class-string (instantiated without parameters)
        new CacheProvider(),       // or ready instance
    ])
    ->withEnvironment('production') // optional; defaults to APP_ENV or 'production'
    ->create();                     // builds container, registers providers, boots

$service = $app->make(UserService::class);   // = $app->container()->get(...)
$container = $app->container();

$app->environment();              // 'production'
$app->isProduction();             // true
$app->isEnvironment('local', 'testing');
```

After `create()`, the container automatically has: `Application::class`,
`ConfigResolverInterface::class` and (from the container itself) `Container::class`
and `Psr\Container\ContainerInterface`.

Rules:
- After `create()`, configuration is locked (`withConfig`/`withProviders`/
  `withEnvironment` throw `LogicException`).
- `container()` before `create()` throws `LogicException`.
- `create()` is idempotent.
- Provider class names are instantiated without parameters (`new $class()`) — providers
  should therefore have no constructor dependencies (they run before booting).
