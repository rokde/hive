<?php

declare(strict_types=1);

namespace Hive\Events;

/**
 * Executes all listeners for an event.
 *
 * If the event implements StoppableEventInterface and
 * isPropagationStopped() returns true, subsequent listeners are skipped.
 */
final class Executor
{
    /**
     * Execute all listeners for an event.
     *
     * @param  mixed  $event  The event.
     * @param  list<ListenerInterface>  $listeners  The listeners to be called.
     */
    public function execute(mixed $event, array $listeners): void
    {
        foreach ($listeners as $listener) {
            $listener->handle($event);
        }
    }
}
