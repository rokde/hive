<?php

declare(strict_types=1);

namespace Hive\Events;

/**
 * Synchronous queue: Events are executed immediately (no queuing).
 *
 * This is the default for simple setups. For asynchronous processing,
 * a different QueueInterface implementation can be used.
 */
final readonly class SyncQueue implements QueueInterface
{
    public function __construct(
        private Executor $executor,
    ) {}

    public function push(mixed $event, array $listeners): void
    {
        $this->executor->execute($event, $listeners);
    }
}
