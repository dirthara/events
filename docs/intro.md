---
id: intro
title: Dirthara Events
sidebar_position: 1
description: PSR-14 event dispatching, listener registration, and event publishing with plain PHP objects.
---

Dirthara Events is a [PSR-14](https://www.php-fig.org/psr/psr-14/) event dispatcher. `EventDispatcher` implements
`EventDispatcherInterface` and `ListenerProvider` implements `ListenerProviderInterface`, so either can be combined with
any other PSR-14 implementation, and code written against the interfaces can swap them out.

## Events are plain PHP objects

An event is any object. Dirthara has no event interface, base class, trait, or attribute, and an event does not have to
extend or implement anything:

```php
final readonly class UserWasCreated
{
    public function __construct(
        public User $user,
    ) {}
}
```

An application can still give its events a common interface of its own, such as `DomainEvent`, and listen for that
interface; see [matching parent classes and interfaces](listeners.md#match-parent-classes-and-interfaces).

## A first event

```php
use Dirthara\Events\EventDispatcher;
use Dirthara\Events\ListenerProvider;

$provider = new ListenerProvider();

$provider->listen(
    UserWasCreated::class,
    static function (UserWasCreated $event): void {
        // react to the event
    },
);

$dispatcher = new EventDispatcher($provider);

$dispatcher->dispatch(
    new UserWasCreated($user),
);
```

`ListenerProvider` holds the listeners and decides which of them apply to an event, and in which order.
`EventDispatcher` asks its provider for those listeners and calls each of them in turn with the event.

Code that only announces an event, and does not rely on its listeners, can [publish](publishing.md) it through an
`EventPublisher` instead of dispatching it.

| Page | Covers |
| --- | --- |
| [Listening for events](listeners.md) | Registering listeners, priorities, parent class and interface matching, and duplicate registrations. |
| [Dispatching events](dispatching.md) | The dispatcher, stoppable events, and exceptions thrown by listeners. |
| [Publishing events](publishing.md) | One-way publishing, and when to publish rather than dispatch. |
| [Deferred publishing](deferred-publishing.md) | Buffering published events and flushing them later. |
| [Installation](installation.md) | Requirements and installation. |
