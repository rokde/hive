<?php

declare(strict_types=1);

namespace Hive\DependencyInjection\Exceptions;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

/**
 * General container error (e.g., an unsolvable dependency).
 */
class ContainerException extends RuntimeException implements ContainerExceptionInterface {}
