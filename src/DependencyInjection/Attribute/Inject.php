<?php

declare(strict_types=1);

namespace Hive\DependencyInjection\Attribute;

use Attribute;

/**
 * Forces the resolution of a parameter using an explicit container ID
 * instead of the type hint.
 *
 * Example:
 *    public function __construct(
 *        #[Inject('logger.audit')] LoggerInterface $logger,
 *    ) {}
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Inject
{
    public function __construct(
        public string $id,
    ) {}
}
