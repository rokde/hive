<?php

declare(strict_types=1);

namespace Hive\DependencyInjection;

use Psr\Container\ContainerInterface;

class Container implements ContainerInterface
{
    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id)
    {
        // TODO: Implement get() method.
    }

    /**
     * @param class-string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        // TODO: Implement has() method.
    }
}
