<?php

declare(strict_types=1);

namespace Hive\Events;

/**
 * A queue processes events (synchronously or asynchronously).
 *
 * It receives an event and the listeners that are to process it,
 * and delegates execution to an executor (immediately or after a delay).
 */
interface QueueInterface
{
    /**
     * Adds an event and its listeners to the queue.
     *
     * @param  mixed  $event  The event.
     * @param  list<ListenerInterface>  $listeners  The listeners for this event.
     */
    public function push(mixed $event, array $listeners): void;
}
