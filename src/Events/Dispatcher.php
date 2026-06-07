<?php

declare(strict_types=1);

namespace Hive\Events;

use BackedEnum;
use InvalidArgumentException;

/**
 * Central event dispatcher with listener registry and queue.
 *
 * Listeners register based on event identifiers (string, class name, or BackedEnum value).
 *  During dispatch, events are processed via a queue:
 *  - SyncQueue: always available (default)
 *  - AsyncQueue: optional (e.g., Redis, job queue)
 *
 *  Events can implement ShouldQueueInterface to use AsyncQueue,
 *  or a queue can be explicitly specified during dispatch.
 *
 *  Example:
 *    $dispatcher = new EventDispatcher($syncQueue);
 *    $dispatcher->setAsyncQueue($asyncQueue);
 *    $dispatcher->listen(‘user.created’, new SendEmailListener());
 *
 *    // Uses AsyncQueue (because the event implements ShouldQueueInterface)
 *    $dispatcher->dispatch(new UserCreatedEvent(...));
 *    // or explicitly: $dispatcher->dispatch($event, queue: $syncQueue);
 */
final class Dispatcher
{
    /**
     * @var array<string, list<ListenerInterface>>
     *                                             Listeners per event identifier.
     */
    private array $listeners = [];

    /**
     * @var array<string, QueueInterface>
     */
    private array $queues = [];

    public function __construct(
    ) {
        $this->queues['sync'] = new SyncQueue;
    }

    /**
     * Register a queue.
     */
    public function addQueue(QueueInterface $queue, string $key = 'async'): self
    {
        $this->queues[$key] = $queue;

        return $this;
    }

    /**
     * Register a listener on an event identifier.
     *
     * @param  string|class-string|BackedEnum  $identifier  Event identifier (string, class, or enum value).
     * @param  ListenerInterface|callable  $listener  Listener (class or callable).
     */
    public function listen(string|BackedEnum $identifier, ListenerInterface|callable $listener): self
    {
        $key = $this->normalizeIdentifier($identifier);
        $listenerInstance = $this->normalizeListener($listener);

        if (! isset($this->listeners[$key])) {
            $this->listeners[$key] = [];
        }

        $this->listeners[$key][] = $listenerInstance;

        return $this;
    }

    /**
     * Dispatch an event (optionally with an explicit ID; otherwise, the ID is determined from the event).
     *
     * @param  mixed  $event  The event.
     * @param  string|class-string|BackedEnum|null  $identifier  Explicit ID (optional).
     */
    public function dispatch(mixed $event, string|BackedEnum|null $identifier = null, ?string $onQueue = null): void
    {
        $key = $identifier !== null
            ? $this->normalizeIdentifier($identifier)
            : $this->inferIdentifier($event);

        $matchingListeners = $this->listeners[$key] ?? [];

        if (! empty($matchingListeners)) {
            $queue = $this->queues['sync'];
            if ($onQueue !== null) {
                $queue = $this->resolveQueue($event, $onQueue);
            }

            $queue->push($event, $matchingListeners);
        }
    }

    private function resolveQueue(mixed $event, string $requestedQueue): QueueInterface
    {
        if ($event instanceof ShouldQueueInterface && $this->queues[$requestedQueue] !== null) {
            return $this->queues[$requestedQueue];
        }

        return $this->queues['sync'];
    }

    /**
     * Convert an event identifier to a string key.
     */
    private function normalizeIdentifier(string|BackedEnum $identifier): string
    {
        if (is_string($identifier)) {
            return $identifier;
        }

        return (string) $identifier->value;
    }

    /**
     * Attempts to determine an event identifier from the event itself.
     *
     * - Object: class name
     * - BackedEnum: enum value
     * - String: use directly
     *
     * @throws InvalidArgumentException If the event cannot be inferred.
     */
    private function inferIdentifier(mixed $event): string
    {
        if (is_string($event)) {
            return $event;
        }

        if ($event instanceof BackedEnum) {
            return (string) $event->value;
        }

        if (is_object($event)) {
            return $event::class;
        }

        throw new InvalidArgumentException(
            sprintf('Cannot infer event identifier from type %s', get_debug_type($event)),
        );
    }

    /**
     * Normalize a listener (class or callable) to ListenerInterface.
     */
    private function normalizeListener(ListenerInterface|callable $listener): ListenerInterface
    {
        if ($listener instanceof ListenerInterface) {
            return $listener;
        }

        // Callable: Wraps in an anonymous listener class
        return new readonly class($listener) implements ListenerInterface
        {
            public function __construct(private mixed $callable) {}

            public function handle(mixed $event): void
            {
                ($this->callable)($event);
            }
        };
    }
}
