<?php

declare(strict_types=1);

namespace Hive\DependencyInjection\Resolver;

interface ConfigResolverInterface
{
    /**
     * @param  class-string|string  $key
     */
    public function has(string $key): bool;

    /**
     * @param  class-string|string  $key
     */
    public function get(string $key, mixed $default = null): mixed;
}
