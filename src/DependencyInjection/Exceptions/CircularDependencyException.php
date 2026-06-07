<?php

declare(strict_types=1);

namespace Hive\DependencyInjection\Exceptions;

/**
 * Thrown when a resolution cycle is detected (A -> B -> A).
 */
class CircularDependencyException extends ContainerException
{
    /**
     * @param list<string> $path The complete resolution path, including the ID that appears again.
     */
    public static function fromPath(array $path): self
    {
        return new self(sprintf('Circular dependency detected: %s', implode(' -> ', $path)));
    }
}
