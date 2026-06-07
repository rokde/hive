<?php

declare(strict_types=1);

namespace Hive\Events;

/**
 * Marks an event that belongs in an asynchronous queue.
 *
 * If the event implements this interface and an AsyncQueue
 * is registered in the dispatcher, the event is buffered there
 * instead of being processed synchronously immediately.
 *
 * If no AsyncQueue is available, it is processed synchronously.
 */
interface ShouldQueueInterface {}
