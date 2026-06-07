# Event System

The event system provides flexible, asynchronous event management with listener registry and queue support.

## Overview

```
EventDispatcher (central)
├─ listen(identifier, listener)       → Register listeners
├─ dispatch(event, identifier, queue) → Dispatch events
├─ addQueue(queue)                    → Register async queue
└─ SyncQueue (always available, default)
```

## Architecture

### Components

- **EventDispatcher** — central management for listeners and event dispatch
- **SyncQueue** (default) — process events immediately
- **AsyncQueue** (optional) — buffer events asynchronously
- **Executor** — executes listeners for an event
- **ListenerInterface** — class-based listeners
- **ShouldQueueInterface** — marker for events that belong in AsyncQueue

### Flow

```
dispatch(event)
    ↓
Select queue:
  1. Passed explicitly?
  2. Event: ShouldQueueInterface + AsyncQueue available?
  3. Otherwise: SyncQueue
    ↓
Queue::push(event, listeners)
    ↓
Executor::execute(event, listeners)
    ↓
foreach listener → listener->handle(event)
```

## Register Listeners

### 3 Identifier Types

```php
$events->listen('user.created', $listener);           // String
$events->listen(UserCreated::class, $listener);       // Class string
$events->listen(UserEvent::Created, $listener);       // BackedEnum value
```

### 2 Listener Types

```php
// Class (implements ListenerInterface)
class SendEmailListener implements ListenerInterface {
    public function handle(mixed $event): void { /* ... */ }
}
$events->listen('user.created', new SendEmailListener());

// Callable
$events->listen('user.created', fn($event) => sendEmail($event));
```

## Dispatch Events

### Simple (Inferred Queue)

```php
class UserCreatedEvent implements ShouldQueueInterface {
    public function __construct(public int $userId) {}
}

$dispatcher->dispatch(new UserCreatedEvent(123));
// → ShouldQueueInterface + AsyncQueue available → AsyncQueue
// → Buffered immediately, not processed
```

### Explicit Queue

```php
// Event with ShouldQueue, but explicitly SyncQueue
$dispatcher->dispatch($event, onQueue: 'sync');
// → Processed immediately, not buffered
```

### With Event Identifier

```php
$dispatcher->dispatch($event, identifier: 'user.created');
// or with BackedEnum
$dispatcher->dispatch($event, identifier: UserEvent::Created);
```

## Configure AsyncQueue

### In Application

```php
$app = Application::configure()
    ->withQueues(function (EventDispatcher $events) {
        $events->addQueue(new RedisAsyncQueue($redis));
    })
    ->withEvents(function (EventDispatcher $events) {
        $events->listen(UserCreatedEvent::class, new SendEmailListener());
    })
    ->create();
```

**Order matters:** `withQueues()` before `withEvents()`!

### In ServiceProvider

```php
class EventProvider implements ServiceProviderInterface {
    public function register(Container $c): void {
        // EventDispatcher is already available!
        $dispatcher = $c->get(EventDispatcher::class);
        $dispatcher->listen('user.created', new LogListener());
    }
}
```

## AsyncQueue Implementations

### InMemoryAsyncQueue (Tests/Demo)

```php
$queue = new InMemoryAsyncQueue(new Executor());
$dispatcher->addQueue($queue);

$dispatcher->dispatch(new UserCreatedEvent(...));
// Events buffered in memory

$queue->processAll(); // Process all
// or
$queue->processBatch(10); // Only first 10
```

### RedisAsyncQueue (Production)

```php
$redis = new Redis();
$redis->connect('localhost', 6379);

$queue = new RedisAsyncQueue($redis);
$dispatcher->addQueue($queue);

// Dispatch: Event in Redis
$dispatcher->dispatch(new UserCreatedEvent(...));

// Worker (separate process)
$queue->work(timeout: 300); // Work for 5 minutes
```

### FileAsyncQueue (Shared Hosting)

```php
$queue = new FileAsyncQueue('/var/events-queue');
$dispatcher->addQueue($queue);

// Dispatch: Event as file
$dispatcher->dispatch(new UserCreatedEvent(...));

// Cron job
$queue->work(batchSize: 50); // Max 50 per run
```

## ShouldQueueInterface

Marks events that should be processed **asynchronously**:

```php
interface ShouldQueueInterface {}

class UserCreatedEvent implements ShouldQueueInterface {
    public function __construct(public string $email) {}
}
```

**Behavior:**

- AsyncQueue available? → Event buffered
- No AsyncQueue? → Event processed synchronously (fallback)
- Explicit queue during dispatch? → That one is used (overrides)

## Practical Examples

### Email Send (Async)

```php
class UserCreatedEvent implements ShouldQueueInterface {
    public function __construct(public string $email) {}
}

class SendWelcomeEmailListener implements ListenerInterface {
    public function __construct(private MailService $mail) {}

    public function handle(mixed $event): void {
        $this->mail->send($event->email, 'Welcome!');
    }
}

// Bootstrap
$app = Application::configure()
    ->withQueues(fn($e) => $e->addQueue(new RedisAsyncQueue($redis)))
    ->withEvents(fn($e) => $e->listen(UserCreatedEvent::class, new SendWelcomeEmailListener()))
    ->create();

// Usage
$dispatcher = $app->make(EventDispatcher::class);
$dispatcher->dispatch(new UserCreatedEvent('alice@example.com'));
// → Email will be sent later by worker
```

### Logging (Sync)

```php
class AnyEvent {}

class LogListener implements ListenerInterface {
    public function __construct(private LoggerInterface $log) {}

    public function handle(mixed $event): void {
        $this->log->info('Event occurred', ['event' => get_class($event)]);
    }
}

// No ShouldQueueInterface → processed immediately
$dispatcher->dispatch(new AnyEvent());
```

### Multi-Listener

```php
$dispatcher
    ->listen('order.placed', new SendConfirmationEmail())
    ->listen('order.placed', new UpdateInventory())
    ->listen('order.placed', new LogOrderAnalytics());

// All 3 listeners are called (in registration order)
$dispatcher->dispatch(new OrderPlacedEvent(...));
```

## Error Handling

Events are not caught by the exception handler — listeners must handle their own errors:

```php
class SafeListener implements ListenerInterface {
    public function handle(mixed $event): void {
        try {
            $this->doWork($event);
        } catch (Exception $e) {
            $this->logger->error('Listener failed', ['error' => $e->getMessage()]);
            // Re-throw for DLQ/retry logic, or fail silently
        }
    }
}
```

## Best Practices

1. **Events are data transfer objects** — no business logic, only properties
2. **Listeners do the work** — email, logging, notifications, etc.
3. **ShouldQueueInterface for slow operations** — email, API calls, database updates
4. **Synchronous for fast things** — validation, cache updates, simple logging
5. **Workers should be idempotent** — events can be processed multiple times (retries)

## Summary

| Feature | Description |
|---------|------------|
| **Listener Registry** | String / Class / Enum as identifier |
| **Flexible Listeners** | Classes or callables |
| **SyncQueue (default)** | Immediate processing |
| **AsyncQueue (optional)** | Asynchronous processing (Redis, file, etc.) |
| **ShouldQueueInterface** | Automatic queue selection |
| **Explicit Queue** | Manual control during dispatch |
| **Application Integration** | `->withQueues()` + `->withEvents()` |

## Files

- `src/Events/Dispatcher.php` — central
- `src/Events/ListenerInterface.php` — listener classes
- `src/Events/QueueInterface.php` — queue interface
- `src/Events/SyncQueue.php` — default (immediate)
- `src/Events/Executor.php` — executes listeners
- `src/Events/ShouldQueueInterface.php` — async marker

## Tests

- `tests/test_events.php` — Core event system
- `tests/test_events_provider.php` — Provider integration
- `tests/test_multi_queue.php` — Queue selection and inference
- `tests/test_custom_queue.php` — Custom async queue

**83 tests total — production-ready.**
