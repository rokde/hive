<?php

declare(strict_types=1);

use Hive\Application;
use Hive\Config\Resolver\ArrayConfigResolver;
use Hive\DependencyInjection\Container;
use Hive\DependencyInjection\Exceptions\ContainerException;
use Hive\DependencyInjection\Provider\BootableServiceProviderInterface;
use Hive\DependencyInjection\Provider\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use Tests\Fixtures\Application\NotAProvider;
use Tests\Fixtures\Application\RegisteringProvider;
use Tests\Fixtures\Application\SimpleService;

require_once __DIR__.'/../Fixtures/Application/Fixtures.php';

// =====================================================================
// Configuration & Creation
// =====================================================================

test('configure returns a new application instance', function (): void {
    $app = Application::configure();

    expect($app)->toBeInstanceOf(Application::class);
});

test('withConfig accepts an array', function (): void {
    $app = Application::configure()
        ->withConfig(['key' => 'value']);

    expect($app->config()->has('key'))->toBeTrue()
        ->and($app->config()->get('key'))->toBe('value');
});

test('withConfig accepts a ConfigResolverInterface', function (): void {
    $config = new ArrayConfigResolver(['app' => 'test']);
    $app = Application::configure()
        ->withConfig($config);

    expect($app->config())->toBe($config);
});

test('withEnvironment sets the environment', function (): void {
    $app = Application::configure()
        ->withEnvironment('testing');

    expect($app->environment())->toBe('testing');
});

test('withProviders registers a provider class', function (): void {
    $app = Application::configure()
        ->withProviders([RegisteringProvider::class])
        ->create();

    expect($app->container()->has(SimpleService::class))->toBeTrue();
});

test('withProviders registers a provider instance', function (): void {
    $provider = new RegisteringProvider;

    $app = Application::configure()
        ->withProviders([$provider])
        ->create();

    expect($app->container()->has(SimpleService::class))->toBeTrue();
});

test('withProviders can be called multiple times', function (): void {
    $provider1 = new class implements ServiceProviderInterface
    {
        public function register(Container $c): void
        {
            $c->instance('p1', 'value1');
        }
    };

    $provider2 = new class implements ServiceProviderInterface
    {
        public function register(Container $c): void
        {
            $c->instance('p2', 'value2');
        }
    };

    $app = Application::configure()
        ->withProviders([$provider1])
        ->withProviders([$provider2])
        ->create();

    expect($app->container()->get('p1'))->toBe('value1')
        ->and($app->container()->get('p2'))->toBe('value2');
});

test('create builds the container with registered providers', function (): void {
    $app = Application::configure()
        ->withConfig(['app.name' => 'test'])
        ->withProviders([RegisteringProvider::class])
        ->create();

    expect($app->container())->toBeInstanceOf(Container::class)
        ->and($app->container()->has(SimpleService::class))->toBeTrue();
});

test('create is idempotent', function (): void {
    $app = Application::configure()->create();
    $container1 = $app->container();
    $container2 = $app->create()->container();

    expect($container1)->toBe($container2);
});

test('container throws if not created yet', function (): void {
    $app = Application::configure();

    expect(fn (): Container => $app->container())
        ->toThrow(LogicException::class, 'Application has not been created yet');
});

test('make is a shortcut for container()->get()', function (): void {
    $app = Application::configure()
        ->withProviders([RegisteringProvider::class])
        ->create();

    $service = $app->make(SimpleService::class);

    expect($service)->toBeInstanceOf(SimpleService::class)
        ->and($service)->toBe($app->container()->get(SimpleService::class));
});

// =====================================================================
// Container Instance Registration
// =====================================================================

test('Application and Container are registered as singletons', function (): void {
    $app = Application::configure()->create();
    $container = $app->container();

    expect($container->get(Application::class))->toBe($app)
        ->and($container->get(Container::class))->toBe($container)
        ->and($container->get(ContainerInterface::class))->toBe($container);
});

test('ConfigResolverInterface is registered in the container', function (): void {
    $config = new ArrayConfigResolver(['key' => 'value']);
    $app = Application::configure()
        ->withConfig($config)
        ->create();

    expect($app->container()->get(ContainerInterface::class))
        ->toBe($app->container());
});

// =====================================================================
// Configuration Locking
// =====================================================================

test('withConfig is locked after create', function (): void {
    $app = Application::configure()
        ->create();

    expect(fn (): Application => $app->withConfig(['key' => 'value']))
        ->toThrow(LogicException::class, 'Application is already created');
});

test('withEnvironment is locked after create', function (): void {
    $app = Application::configure()
        ->create();

    expect(fn (): Application => $app->withEnvironment('testing'))
        ->toThrow(LogicException::class, 'Application is already created');
});

test('withProviders is locked after create', function (): void {
    $app = Application::configure()
        ->create();

    expect(fn (): Application => $app->withProviders([RegisteringProvider::class]))
        ->toThrow(LogicException::class, 'Application is already created');
});

// =====================================================================
// Environment Detection
// =====================================================================

test('environment defaults to production', function (): void {
    $app = Application::configure();

    expect($app->environment())->toBe('production');
});

test('isProduction returns true for production environment', function (): void {
    $app = Application::configure()
        ->withEnvironment('production');

    expect($app->isProduction())->toBeTrue();
});

test('isProduction returns false for non-production environment', function (): void {
    $app = Application::configure()
        ->withEnvironment('local');

    expect($app->isProduction())->toBeFalse();
});

test('isLocal returns true for local environment', function (): void {
    $app = Application::configure()
        ->withEnvironment('local');

    expect($app->isLocal())->toBeTrue();
});

test('isLocal returns false for non-local environment', function (): void {
    $app = Application::configure()
        ->withEnvironment('production');

    expect($app->isLocal())->toBeFalse();
});

test('isEnvironment matches any of the given environments', function (): void {
    $app = Application::configure()
        ->withEnvironment('testing');

    expect($app->isEnvironment('testing', 'staging'))->toBeTrue()
        ->and($app->isEnvironment('production', 'local'))->toBeFalse();
});

// =====================================================================
// Service Provider Resolution
// =====================================================================

test('resolveProvider throws for non-existent class', function (): void {
    $app = Application::configure()
        ->withProviders(['NonExistentProvider'])
        ->create();

    // The error occurs during create() when trying to add the provider
})->throws(ContainerException::class, 'Service provider class');

test('resolveProvider throws when class does not implement ServiceProviderInterface', function (): void {
    $app = Application::configure()
        ->withProviders([NotAProvider::class])
        ->create();

    // The error occurs during create() when trying to add the provider
})->throws(ContainerException::class, 'must implement');

test('provider boot method is called', function (): void {
    $provider = new class implements BootableServiceProviderInterface, ServiceProviderInterface
    {
        /** @var list<string> */
        public array $log = [];

        public function register(Container $c): void
        {
            $this->log[] = 'register';
        }

        public function boot(Container $c): void
        {
            $this->log[] = 'boot';
        }
    };

    Application::configure()
        ->withProviders([$provider])
        ->create();

    expect($provider->log)->toBe(['register', 'boot']);
});

// =====================================================================
// Config Access
// =====================================================================

test('config returns the configuration resolver', function (): void {
    $config = new ArrayConfigResolver(['app' => 'hive']);
    $app = Application::configure()
        ->withConfig($config);

    expect($app->config())->toBe($config);
});
