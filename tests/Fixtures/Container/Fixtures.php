<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

use Hive\DependencyInjection\Attribute\Config;
use Hive\DependencyInjection\Attribute\Inject;
use Hive\DependencyInjection\Container;
use Hive\DependencyInjection\Provider\BootableServiceProviderInterface;
use Hive\DependencyInjection\Provider\ServiceProviderInterface;

interface GreeterInterface
{
    public function greet(): string;
}

final class Greeter implements GreeterInterface
{
    public function greet(): string
    {
        return 'hello';
    }
}

final class LoudGreeter implements GreeterInterface
{
    public function greet(): string
    {
        return 'HELLO';
    }
}

final class NoConstructor
{
    public string $marker = 'no-ctor';
}

final class NeedsGreeter
{
    public function __construct(public Greeter $greeter) {}
}

final class NeedsInterface
{
    public function __construct(public GreeterInterface $greeter) {}
}

final class ScalarDefault
{
    public function __construct(public int $count = 7) {}
}

final class NullableDependency
{
    public function __construct(public ?GreeterInterface $greeter) {}
}

final class UnresolvablePrimitive
{
    public function __construct(public int $count) {}
}

abstract class AbstractThing {}

final class InjectById
{
    public function __construct(
        #[Inject('greeter.loud')]
        public GreeterInterface $greeter,
    ) {}
}

final class ConfigParam
{
    public function __construct(
        #[Config('app.name')]
        public string $name,
    ) {}
}

final class ConfigWithAttributeDefault
{
    public function __construct(
        #[Config('missing.key', default: 'attr-default')]
        public string $value,
    ) {}
}

final class ConfigWithNullAttributeDefault
{
    public function __construct(
        #[Config('missing.key', default: null)]
        public ?string $value,
    ) {}
}

final class ConfigWithParamDefault
{
    public function __construct(
        #[Config('missing.key')]
        public string $value = 'param-default',
    ) {}
}

final class ConfigMissingNoDefault
{
    public function __construct(
        #[Config('missing.key')]
        public string $value,
    ) {}
}

final class CycleA
{
    public function __construct(public CycleB $b) {}
}

final class CycleB
{
    public function __construct(public CycleA $a) {}
}

final class Invokable
{
    public function __invoke(Greeter $greeter, string $suffix = '!'): string
    {
        return $greeter->greet().$suffix;
    }
}

final class WithMethods
{
    public string $marker = 'instance';

    public function instanceMethod(Greeter $greeter, string $name): string
    {
        return $greeter->greet().' '.$name;
    }

    public static function staticMethod(int $value = 1): int
    {
        return $value * 10;
    }

    public function variadicMethod(string $first, int ...$rest): string
    {
        return $first.':'.count($rest);
    }
}

final class RegisteringProvider implements ServiceProviderInterface
{
    public bool $registered = false;

    public function register(Container $container): void
    {
        $this->registered = true;
        $container->singleton(GreeterInterface::class, Greeter::class);
    }
}

final class TrackingBootableProvider implements BootableServiceProviderInterface, ServiceProviderInterface
{
    /** @var list<string> */
    public static array $log = [];

    public function register(Container $container): void
    {
        self::$log[] = 'register';
    }

    public function boot(Container $container): void
    {
        self::$log[] = 'boot';
    }
}
