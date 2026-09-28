---
id: publishing
title: Publishing events
sidebar_position: 5
description: Publish events as one-way notifications with EventPublisher, and choose between publishing and dispatching.
---

## Publish an event

`Dirthara\Events\Contract\EventPublisher` announces an event without the caller depending on when or how it is handled:

```php
use Dirthara\Events\Contract\EventPublisher;

final readonly class RegisterUser
{
    public function __construct(
        private EventPublisher $events,
    ) {}

    public function __invoke(UserId $userId): void
    {
        // ...

        $this->events->publish(new UserWasCreated($userId));
    }
}
```

`publish()` takes any object and returns nothing. Events stay plain PHP objects, exactly as they are for
[dispatching](dispatching.md).

## Publishing and dispatching are different operations

`dispatch()` runs the listeners now. `publish()` is a notification, and the contract promises nothing about what
happens to the event afterwards.

| | `dispatch()` | `publish()` |
| --- | --- | --- |
| Defined by | PSR-14 `EventDispatcherInterface` | `EventPublisher` |
| Listeners have run when it returns | Yes | Not promised |
| Changes listeners make to the event are visible to the caller | Yes | Not promised |
| Propagation can be stopped | Yes | Not promised |
| Listener exceptions reach the caller | Yes | Not promised |
| Returns | The event | Nothing |

Dispatch an event when the calling code relies on its listeners, for example to collect the warnings they leave on it:

```php
$event = $dispatcher->dispatch(new PasswordWasChanged($user));
```

Publish an event when the calling code only announces that something happened, and would be correct whether its
listeners ran now, later in the same process, or somewhere else:

```php
$publisher->publish(new UserWasCreated($userId));
```

Code typed against `EventPublisher` keeps working unchanged when the application replaces its publisher with one that
handles events differently.

## Publish synchronously

`SynchronousEventPublisher` publishes an event by dispatching it straight away:

```php
use Dirthara\Events\EventDispatcher;
use Dirthara\Events\ListenerProvider;
use Dirthara\Events\SynchronousEventPublisher;

$provider = new ListenerProvider();
$publisher = new SynchronousEventPublisher(new EventDispatcher($provider));

$publisher->publish(new UserWasCreated($userId));
```

The constructor accepts any PSR-14 `EventDispatcherInterface`, so the publisher also works with a dispatcher from
another library. `publish()` dispatches the event it is given once, and ignores what `dispatch()` returns.

Because the dispatch is synchronous, so is the publish: every listener has run by the time `publish()` returns, and an
exception thrown by a listener or by the dispatcher leaves `publish()` unchanged. That is how this publisher happens to
behave, not what `EventPublisher` promises. Code that publishes an event should not rely on it.

## Publish immutable events

A published event may be handled after `publish()` returns, so nothing the caller does to it afterwards, and nothing a
listener does to it, can safely be relied on. Published events should be immutable notifications that describe what
happened:

```php
final readonly class UserWasCreated
{
    public function __construct(
        public UserId $userId,
    ) {}
}
```

The package does not enforce this, and `publish()` accepts a mutable object like any other.

:::tip
Carry identifiers and values rather than services or open resources. An event that only holds data can be handled in
another process as easily as in this one.
:::

## Asynchronous publishing

This package does not queue events or hand them to another process. It has no queue, worker, serialiser, retry, or
delivery guarantee, and no dependency on any transport.

`EventPublisher` leaves room for that. Another package can implement it on top of a queue:

```php
use Dirthara\Events\Contract\EventPublisher;

final readonly class QueuedEventPublisher implements EventPublisher
{
    public function publish(object $event): void
    {
        // hand the event to a queue
    }
}
```

Code that publishes through `EventPublisher` does not change when an application swaps `SynchronousEventPublisher` for
such a publisher.

Listeners themselves stay synchronous: `ListenerProvider::listen()` has no option to queue or defer one listener. A
listener that wants to hand work elsewhere can publish another event through a publisher that does.
