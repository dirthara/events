---
id: deferred-publishing
title: Deferred publishing
sidebar_position: 6
description: Buffer published events with DeferredEventPublisher and publish them later with an explicit flush.
---

## Defer events until a flush

`DeferredEventPublisher` holds the events published to it and passes them to another `EventPublisher` when it is
flushed:

```php
use Dirthara\Events\DeferredEventPublisher;

$deferred = new DeferredEventPublisher($publisher);

$deferred->publish(new UserWasCreated($userId));

// later:
$deferred->flush();
```

`publish()` only adds the event to a buffer in memory. Nothing reaches the wrapped publisher, and no listener runs,
until `flush()` is called. Any `EventPublisher` can be wrapped: a `SynchronousEventPublisher`, a publisher from another
package, or another `DeferredEventPublisher`.

`DeferredEventPublisher` implements `Dirthara\Events\Contract\FlushableEventPublisher`, an `EventPublisher` with a
`flush()` method. Code that only publishes events types against `EventPublisher`; the code that decides when the buffer
is emptied types against `FlushableEventPublisher`.

## Deferred is not asynchronous

Deferring changes when an event is handled, not where. Flushing into a `SynchronousEventPublisher` runs every listener
inside the `flush()` call, in the same process, and a slow listener delays whatever called `flush()`.

| Operation | What happens |
| --- | --- |
| `dispatch()` | The listeners run now, and the caller may rely on their effects. |
| `publish()` on `SynchronousEventPublisher` | A one-way notification, handled now by dispatching it. |
| `publish()` on `DeferredEventPublisher` | The event is buffered and handled later in the same process, when it is flushed. |
| `publish()` on an asynchronous publisher | Another package hands the event to a queue, to be handled in another process. |

This package provides the first three. See [asynchronous publishing](publishing.md#asynchronous-publishing).

## Flush order

`flush()` publishes the buffered events one at a time, in the order they were published, and returns once the buffer
is empty. An event published twice is published twice. Flushing an empty buffer does nothing, and so does flushing
again straight after a flush that succeeded.

An event published to the same `DeferredEventPublisher` while it is flushing, for example by a listener, joins the end
of the buffer and is published before `flush()` returns:

```php
$deferred->publish($a);
$deferred->publish($b);

// a listener for $a publishes $c to $deferred

$deferred->flush();
// publishes $a, $b, $c
```

A call to `flush()` made while the same publisher is already flushing returns straight away. The flush in progress
publishes everything that is left.

## When a flush fails

An event leaves the buffer only once the wrapped publisher has returned from publishing it. When the wrapped publisher
throws, `flush()` stops at once and the exception leaves `flush()` unchanged:

| Event | During the failed flush | Afterwards |
| --- | --- | --- |
| 1 | Published | Removed from the buffer |
| 2 | Threw an exception | Still buffered |
| 3 | Not attempted | Still buffered |
| 4 | Not attempted | Still buffered |

The next `flush()` starts again with event 2, followed by events 3 and 4 and anything published in between.

:::caution
A flush that fails is not a delivery guarantee. The wrapped publisher may have done part of its work before it threw:
with a `SynchronousEventPublisher`, the listeners before the one that threw have already run, and run again when event
2 is retried. Listeners that must not repeat an effect have to guard against it themselves.
:::

## Events are kept as they are

The buffer holds the event objects themselves. They are not cloned, serialised, or copied, and the object passed to
`publish()` is the one the wrapped publisher later receives. A change made to a mutable event between `publish()` and
`flush()` is therefore visible when it is flushed:

```php
$event = new ProfileWasUpdated();
$event->changes = ['name'];

$deferred->publish($event);

$event->changes[] = 'email';

$deferred->flush();
// the listeners see ['name', 'email']
```

This is intended, and one more reason to publish [immutable events](publishing.md#publish-immutable-events).

## Deciding when to flush

`DeferredEventPublisher` does not flush itself. It knows nothing about requests, responses, commands, jobs, or the end
of the process, and events still in the buffer when it is discarded are never published.

Whatever owns the unit of work calls `flush()` once that work is done:

```text
handle the work
    ↓
produce the result
    ↓
finish the output that belongs to this kind of work
    ↓
flush the deferred events
```

A web runtime can flush after it has sent a response, a console runtime after a command has finished, and a worker
after each job. This package leaves that decision to them.
