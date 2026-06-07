<?php

declare(strict_types=1);

namespace Hive\DependencyInjection\Exceptions;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Thrown when the container does not recognize an ID.
 *
 * Extends ContainerException, thereby implementing both PSR-11 interfaces.
 */
class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
    public static function for(string $id): self
    {
        return new self(sprintf('No entry was found for identifier "%s".', $id));
    }
}
