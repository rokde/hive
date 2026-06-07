<?php

declare(strict_types=1);

use Hive\Config\Resolver\ArrayConfigResolver;
use Hive\DependencyInjection\Container;
use Hive\DependencyInjection\Exceptions\CircularDependencyException;
use Hive\DependencyInjection\Exceptions\ContainerException;
use Hive\DependencyInjection\Exceptions\NotFoundException;
use Hive\DependencyInjection\Lifetime;
use Hive\DependencyInjection\ServiceDefinition;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Tests\Fixtures\Container\AbstractThing;
use Tests\Fixtures\Container\ConfigMissingNoDefault;
use Tests\Fixtures\Container\ConfigParam;
use Tests\Fixtures\Container\ConfigWithAttributeDefault;
use Tests\Fixtures\Container\ConfigWithNullAttributeDefault;
use Tests\Fixtures\Container\ConfigWithParamDefault;
use Tests\Fixtures\Container\CycleA;
use Tests\Fixtures\Container\CycleB;
use Tests\Fixtures\Container\Greeter;
use Tests\Fixtures\Container\GreeterInterface;
use Tests\Fixtures\Container\InjectById;
use Tests\Fixtures\Container\Invokable;
use Tests\Fixtures\Container\LoudGreeter;
use Tests\Fixtures\Container\NeedsGreeter;
use Tests\Fixtures\Container\NeedsInterface;
use Tests\Fixtures\Container\NoConstructor;
use Tests\Fixtures\Container\NullableDependency;
use Tests\Fixtures\Container\RegisteringProvider;
use Tests\Fixtures\Container\ScalarDefault;
use Tests\Fixtures\Container\TrackingBootableProvider;
use Tests\Fixtures\Container\UnresolvablePrimitive;
use Tests\Fixtures\Container\WithMethods;

require_once __DIR__.'/../../Fixtures/Container/Fixtures.php';

// ---------------------------------------------------------------------
// Self resolution / construction
// ---------------------------------------------------------------------

test('resolves itself via PSR-11 interface and concrete class', function (): void {
    $container = new Container;

    expect($container->get(ContainerInterface::class))->toBe($container)
        ->and($container->get(Container::class))->toBe($container);
});

test('uses a default ArrayConfigResolver when none is given', function (): void {
    expect((new Container)->config())->toBeInstanceOf(ArrayConfigResolver::class);
});

test('exposes the injected config resolver', function (): void {
    $config = new ArrayConfigResolver(['app' => 'hive']);

    expect(new Container($config)->config())->toBe($config);
});

// ---------------------------------------------------------------------
// Registration API
// ---------------------------------------------------------------------

test('singleton returns the same instance every time', function (): void {
    $container = new Container;
    $container->singleton(Greeter::class);

    expect($container->get(Greeter::class))->toBe($container->get(Greeter::class));
});

test('bind returns a fresh instance every time', function (): void {
    $container = new Container;
    $container->bind(Greeter::class);

    $a = $container->get(Greeter::class);
    $b = $container->get(Greeter::class);

    expect($a)->toBeInstanceOf(Greeter::class)
        ->and($a)->not->toBe($b);
});

test('singleton binds an interface to a concrete class', function (): void {
    $container = new Container;
    $container->singleton(GreeterInterface::class, Greeter::class);

    expect($container->get(GreeterInterface::class))->toBeInstanceOf(Greeter::class);
});

test('bind accepts a closure factory receiving the container', function (): void {
    $container = new Container;
    $container->bind('answer', fn (ContainerInterface $c): mixed => $c);

    expect($container->get('answer'))->toBe($container);
});

test('factory binding defaults to transient', function (): void {
    $container = new Container;
    $container->factory('thing', fn (): object => new stdClass);

    expect($container->get('thing'))->not->toBe($container->get('thing'));
});

test('factory binding can be made singleton', function (): void {
    $container = new Container;
    $container->factory('thing', fn (): object => new stdClass, Lifetime::Singleton);

    expect($container->get('thing'))->toBe($container->get('thing'));
});

test('instance stores a shared value', function (): void {
    $container = new Container;
    $value = new stdClass;
    $container->instance('cfg', $value);

    expect($container->get('cfg'))->toBe($value)
        ->and($container->has('cfg'))->toBeTrue();
});

test('instance accepts scalar values', function (): void {
    $container = new Container;
    $container->instance('pi', 3.14);

    expect($container->get('pi'))->toBe(3.14);
});

test('alias delegates resolution to the target id', function (): void {
    $container = new Container;
    $container->singleton('greeter.real', Greeter::class);
    $container->alias(GreeterInterface::class, 'greeter.real');

    expect($container->get(GreeterInterface::class))->toBe($container->get('greeter.real'));
});

test('define registers a raw service definition', function (): void {
    $container = new Container;
    $container->define('g', ServiceDefinition::forClass(Greeter::class, Lifetime::Singleton));

    expect($container->get('g'))->toBeInstanceOf(Greeter::class)
        ->and($container->get('g'))->toBe($container->get('g'));
});

test('redefining an id discards the cached singleton', function (): void {
    $container = new Container;
    $container->singleton('g', Greeter::class);

    $first = $container->get('g');

    $container->singleton('g', Greeter::class);

    expect($container->get('g'))->not->toBe($first);
});

test('registration methods are chainable', function (): void {
    $container = new Container;

    expect($container->singleton('a', Greeter::class))->toBe($container)
        ->and($container->bind('b', Greeter::class))->toBe($container)
        ->and($container->alias('c', 'a'))->toBe($container)
        ->and($container->instance('d', 1))->toBe($container)
        ->and($container->factory('e', fn (): int => 1))->toBe($container);
});

// ---------------------------------------------------------------------
// Auto-wiring (zero-config)
// ---------------------------------------------------------------------

test('autowires an unregistered instantiable class as transient', function (): void {
    $container = new Container;

    expect($container->get(Greeter::class))->toBeInstanceOf(Greeter::class)
        ->and($container->get(Greeter::class))->not->toBe($container->get(Greeter::class));
});

test('instantiates a class without a constructor', function (): void {
    expect((new Container)->get(NoConstructor::class)->marker)->toBe('no-ctor');
});

test('recursively resolves class type-hinted dependencies', function (): void {
    $instance = (new Container)->get(NeedsGreeter::class);

    expect($instance)->toBeInstanceOf(NeedsGreeter::class)
        ->and($instance->greeter)->toBeInstanceOf(Greeter::class);
});

test('resolves an interface dependency from a binding', function (): void {
    $container = new Container;
    $container->bind(GreeterInterface::class, LoudGreeter::class);

    expect($container->get(NeedsInterface::class)->greeter)->toBeInstanceOf(LoudGreeter::class);
});

test('uses a scalar default value when no override is given', function (): void {
    expect((new Container)->get(ScalarDefault::class)->count)->toBe(7);
});

test('passes null for an unresolvable nullable dependency', function (): void {
    expect((new Container)->get(NullableDependency::class)->greeter)->toBeNull();
});

test('throws for an unresolvable primitive parameter', function (): void {
    (new Container)->get(UnresolvablePrimitive::class);
})->throws(ContainerException::class, 'Cannot resolve parameter $count');

test('an unregistered abstract class is not found', function (): void {
    (new Container)->get(AbstractThing::class);
})->throws(NotFoundException::class);

test('throws when a binding points at an abstract class', function (): void {
    $container = new Container;
    $container->define('thing', ServiceDefinition::forClass(AbstractThing::class));

    $container->get('thing');
})->throws(ContainerException::class, 'Cannot instantiate');

// ---------------------------------------------------------------------
// Attribute injection
// ---------------------------------------------------------------------

test('#[Inject] resolves a parameter by explicit id', function (): void {
    $container = new Container;
    $container->singleton('greeter.loud', LoudGreeter::class);

    expect($container->get(InjectById::class)->greeter)->toBeInstanceOf(LoudGreeter::class);
});

test('#[Config] resolves a configuration value', function (): void {
    $container = new Container(new ArrayConfigResolver(['app' => ['name' => 'hive']]));

    expect($container->get(ConfigParam::class)->name)->toBe('hive');
});

test('#[Config] falls back to the attribute default when key is missing', function (): void {
    expect((new Container)->get(ConfigWithAttributeDefault::class)->value)->toBe('attr-default');
});

test('#[Config] honours an explicit null attribute default over the param default', function (): void {
    expect((new Container)->get(ConfigWithNullAttributeDefault::class)->value)->toBeNull();
});

test('#[Config] falls back to the parameter default when no attribute default', function (): void {
    expect((new Container)->get(ConfigWithParamDefault::class)->value)->toBe('param-default');
});

test('#[Config] throws when key is missing and no default exists', function (): void {
    (new Container)->get(ConfigMissingNoDefault::class);
})->throws(ContainerException::class, 'Config key "missing.key" not found');

// ---------------------------------------------------------------------
// Circular dependency detection
// ---------------------------------------------------------------------

test('detects a circular dependency', function (): void {
    (new Container)->get(CycleA::class);
})->throws(CircularDependencyException::class, 'Circular dependency detected');

test('circular dependency message contains the full path', function (): void {
    try {
        (new Container)->get(CycleA::class);
        $this->fail('expected exception');
    } catch (CircularDependencyException $circularDependencyException) {
        expect($circularDependencyException->getMessage())
            ->toContain(CycleA::class)
            ->toContain(CycleB::class);
    }
});

// ---------------------------------------------------------------------
// PSR-11 has() / get()
// ---------------------------------------------------------------------

test('has is true for shared, defined and instantiable ids', function (): void {
    $container = new Container;
    $container->instance('shared', new stdClass);
    $container->bind('defined', Greeter::class);

    expect($container->has('shared'))->toBeTrue()
        ->and($container->has('defined'))->toBeTrue()
        ->and($container->has(Greeter::class))->toBeTrue();
});

test('has is false for unknown non-instantiable ids', function (): void {
    $container = new Container;

    expect($container->has('nope'))->toBeFalse()
        ->and($container->has(GreeterInterface::class))->toBeFalse();
});

test('get throws a PSR-11 NotFoundException for an unknown id', function (): void {
    (new Container)->get('does.not.exist');
})->throws(NotFoundException::class, 'No entry was found');

test('NotFoundException implements the PSR-11 interface', function (): void {
    expect(NotFoundException::for('x'))->toBeInstanceOf(NotFoundExceptionInterface::class);
});

// ---------------------------------------------------------------------
// Service providers & boot
// ---------------------------------------------------------------------

test('boot runs provider registration', function (): void {
    $container = new Container;
    $provider = new RegisteringProvider;
    $container->addProvider($provider);

    $container->boot();

    expect($provider->registered)->toBeTrue()
        ->and($container->get(GreeterInterface::class))->toBeInstanceOf(Greeter::class);
});

test('boot registers all providers before booting any', function (): void {
    TrackingBootableProvider::$log = [];
    $container = new Container;
    $container->addProvider(new TrackingBootableProvider);
    $container->addProvider(new TrackingBootableProvider);

    $container->boot();

    expect(TrackingBootableProvider::$log)->toBe(['register', 'register', 'boot', 'boot']);
});

test('boot is idempotent', function (): void {
    TrackingBootableProvider::$log = [];
    $container = new Container;
    $container->addProvider(new TrackingBootableProvider);

    $container->boot();
    $container->boot();

    expect(TrackingBootableProvider::$log)->toBe(['register', 'boot']);
});

test('boot eagerly instantiates eager definitions', function (): void {
    $container = new Container;
    $built = 0;
    $container->define('eager', ServiceDefinition::forFactory(function () use (&$built): object {
        $built++;

        return new stdClass;
    }, Lifetime::Singleton)->asEager());

    expect($built)->toBe(0);

    $container->boot();

    expect($built)->toBe(1);
});

test('addProvider is chainable', function (): void {
    $container = new Container;

    expect($container->addProvider(new RegisteringProvider))->toBe($container);
});

// ---------------------------------------------------------------------
// call()
// ---------------------------------------------------------------------

test('call invokes a closure with injected dependencies', function (): void {
    $result = (new Container)->call(fn (Greeter $g): string => $g->greet());

    expect($result)->toBe('hello');
});

test('call applies named parameter overrides', function (): void {
    $result = (new Container)->call(
        fn (Greeter $g, string $name): string => $g->greet().' '.$name,
        ['name' => 'world'],
    );

    expect($result)->toBe('hello world');
});

test('call invokes an instance method via [object, method]', function (): void {
    $result = (new Container)->call([new WithMethods, 'instanceMethod'], ['name' => 'bob']);

    expect($result)->toBe('hello bob');
});

test('call resolves the object for [class-string, method] instance method', function (): void {
    $result = (new Container)->call([WithMethods::class, 'instanceMethod'], ['name' => 'ann']);

    expect($result)->toBe('hello ann');
});

test('call invokes a static method via "Class::method" string', function (): void {
    expect((new Container)->call(WithMethods::class.'::staticMethod', ['value' => 5]))->toBe(50);
});

test('call invokes a static method via [class-string, staticMethod]', function (): void {
    expect((new Container)->call(WithMethods::staticMethod(...)))->toBe(10);
});

test('call invokes an invokable object', function (): void {
    expect((new Container)->call(new Invokable))->toBe('hello!');
});

test('call invokes an invokable class-string by resolving it', function (): void {
    expect((new Container)->call(Invokable::class))->toBe('hello!');
});

test('call invokes a global function by name', function (): void {
    expect((new Container)->call('strtoupper', ['string' => 'hi']))->toBe('HI');
});

test('call skips variadic parameters', function (): void {
    expect((new Container)->call([new WithMethods, 'variadicMethod'], ['first' => 'x']))->toBe('x:0');
});

test('call throws for a non-resolvable string callable', function (): void {
    (new Container)->call('totally_unknown_callable_xyz');
})->throws(ContainerException::class, 'not a resolvable callable');
