<?php

declare(strict_types=1);

namespace Hive\DependencyInjection\Provider;

use Hive\DependencyInjection\Container;

/**
 * A service provider encapsulates the registration of related services.
 *
 * register() is called during the registration phase. Only bindings may be
 * registered here; services must not be resolved, as other providers may not
 * yet be registered.
 */
interface ServiceProviderInterface
{
    public function register(Container $container): void;
}
