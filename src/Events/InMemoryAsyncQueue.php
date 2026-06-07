<?php

declare(strict_types=1);

namespace Hive\Events;

final class InMemoryAsyncQueue implements QueueInterface
{
    /** @var list<array{event: mixed, listeners: list<ListenerInterface>}> */
    private array $queue = [];

    public function __construct(
        private readonly Executor $executor,
    ) {}

    public function push(mixed $event, array $listeners): void
    {
        $this->queue[] = [
            'event' => $event,
            'listeners' => $listeners,
        ];
    }

    /**
     * Verarbeite alle gepufferten Events.
     */
    public function processAll(): void
    {
        foreach ($this->queue as $item) {
            $this->executor->execute($item['event'], $item['listeners']);
        }

        $this->queue = [];
    }

    /**
     * Verarbeite nur die ersten N Events.
     */
    public function processBatch(int $count): void
    {
        for ($i = 0; $i < $count && $this->queue !== []; $i++) {
            $item = array_shift($this->queue);
            $this->executor->execute($item['event'], $item['listeners']);
        }
    }

    /**
     * Anzahl gepufferter Events.
     */
    public function count(): int
    {
        return count($this->queue);
    }
}
