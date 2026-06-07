<?php

declare(strict_types=1);

namespace Hive\DependencyInjection\Provider;

use Hive\DependencyInjection\Container;

/**
 * Provider with a boot phase. boot() is called AFTER all providers have
 * been registered. Services may be resolved here.
 */
interface BootableServiceProviderInterface
{
    public function boot(Container $container): void;
}
