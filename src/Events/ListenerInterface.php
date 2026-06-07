<?php

declare(strict_types=1);

namespace Hive\Events;

/**
 * A listener handles an event.
 *
 * Listeners can implement this interface as classes or be passed as callables.
 */
interface ListenerInterface
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event  The event (can be an object, string, enum value, etc.).
     */
    public function handle(mixed $event): void;
}
