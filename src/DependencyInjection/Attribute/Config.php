<?php

declare(strict_types=1);

namespace Hive\DependencyInjection\Attribute;

use Attribute;

/**
 * Resolves a parameter from the ConfigResolver (dot notation).
 *
 * Example:
 *   public function __construct(
 *       #[Config('database.host')] string $host,
 *       #[Config('database.port', default: 5432)] int $port,
 *   ) {}
 *
 * The container uses `ReflectionAttribute::getArguments()` to determine
 * whether a default value has been specified, so that `default: null` can
 * be correctly distinguished from “no default”.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Config
{
    public function __construct(
        public string $key,
        public mixed $default = null,
    ) {
    }
}
