<?php

declare(strict_types=1);

namespace Tests\Fixtures\Application;

use Hive\DependencyInjection\Container;
use Hive\DependencyInjection\Provider\ServiceProviderInterface;

final class SimpleService
{
    public string $name = 'simple';
}

final class RegisteringProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->singleton(SimpleService::class);
    }
}

final class NotAProvider {}
