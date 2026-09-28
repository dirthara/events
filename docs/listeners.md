---
id: listeners
title: Listening for events
sidebar_position: 3
description: Register listeners on a ListenerProvider, order them by priority, and match parent classes and interfaces.
---

## Register a listener

```php
use Dirthara\Events\ListenerProvider;

$provider = new ListenerProvider();

$provider->listen(
    UserWasCreated::class,
    static function (UserWasCreated $event) use ($mailer): void {
        $mailer->sendWelcome($event->user);
    },
);
```

`listen()` takes three arguments and returns nothing:

| Parameter | Type | Default | Meaning |
| --- | --- | --- | --- |
| `$event` | `class-string` | | The class, interface, or enum the listener is for. |
| `$listener` | `callable` | | Called with the event. What it returns is ignored. |
| `$priority` | `int` | `0` | Higher priorities are called earlier; see [priorities](#order-listeners-by-priority). |

A listener can be any callable: a closure, a first-class callable such as `$mailer->sendWelcome(...)`, an invokable
object, or an `[$object, 'method']` array. The provider stores each one as a `Closure`, and returns a closure it was
given as the same instance.

The event type is always the one passed to `listen()`. The provider does not read it from the listener's parameter
type and does not inspect the listener at all.

:::caution
The provider cannot check that a listener accepts the events it is registered for. A listener registered for
`DomainEvent` whose parameter is typed `UserWasCreated` throws a `TypeError` when any other `DomainEvent` is
dispatched. Type the parameter as the registered type, or as something wider.
:::

## Event types that are accepted

The event type has to be the name of an existing class, interface, or enum. Enum cases are objects, so an enum works
as an event type like any class:

```php
$provider->listen(
    DeploymentStage::class,
    static function (DeploymentStage $stage): void {
        // ...
    },
);

$dispatcher->dispatch(DeploymentStage::Finished);
```

Anything else is rejected when it is registered rather than stored as a listener that can never be called: a class
that does not exist or cannot be autoloaded, a trait, a scalar type name such as `int`, or an empty string.
`listen()` then throws `Dirthara\Events\Exception\InvalidEventTypeException`, an `InvalidArgumentException` that
implements the package's `EventsException` interface:

```php
use Dirthara\Events\Exception\InvalidEventTypeException;

try {
    $provider->listen('App\\Event\\UserWasCreatd', $listener);
} catch (InvalidEventTypeException $exception) {
    $exception->getMessage();
    // Unable to listen for "App\Event\UserWasCreatd": an event type has to be an existing class, interface, or enum.

    $exception->context;
    // ['event' => 'App\\Event\\UserWasCreatd']
}
```

The message and the context escape control characters in the rejected name, so a value that reaches `listen()` from
outside cannot forge a line in a log.

## Match parent classes and interfaces

A listener applies to every event that is an instance of its event type, so a listener registered for a parent class
or an interface also receives the events that extend or implement it:

```php
interface DomainEvent {}

final readonly class UserWasCreated implements DomainEvent
{
    // ...
}

$provider->listen(
    DomainEvent::class,
    static function (DomainEvent $event) use ($auditLog): void {
        $auditLog->record($event);
    },
);
```

Dispatching a `UserWasCreated` calls the `DomainEvent` listener along with any registered for `UserWasCreated` itself.
A listener registered for an unrelated type is never called.

## Order listeners by priority

The listeners that apply to an event are called in this order:

1. Higher priority first.
2. Within one priority, in the order they were registered.

```php
$provider->listen(OrderWasPlaced::class, $reserveStock);
$provider->listen(OrderWasPlaced::class, $checkForFraud, priority: 100);
$provider->listen(OrderWasPlaced::class, $sendConfirmation);
$provider->listen(OrderWasPlaced::class, $updateStatistics, priority: -100);
```

A placed order calls `$checkForFraud`, `$reserveStock`, `$sendConfirmation`, and then `$updateStatistics`. A priority
is any integer: negative priorities run after the default of `0`, and no value has a meaning of its own.

The order covers every listener that applies, whatever type it was registered for. A listener registered for
`UserWasCreated` with priority `100` runs before a `DomainEvent` listener with priority `0`, and listeners with equal
priorities keep their registration order even when some were registered for the class and some for its interface:

```php
$provider->listen(DomainEvent::class, $first);
$provider->listen(UserWasCreated::class, $second);
$provider->listen(DomainEvent::class, $third);
```

A `UserWasCreated` calls `$first`, `$second`, and `$third`, in that order.

## Duplicate registrations

Every call to `listen()` is a registration of its own. The provider does not compare listeners, so registering the
same listener twice calls it twice:

```php
$provider->listen(UserWasCreated::class, $listener);
$provider->listen(UserWasCreated::class, $listener);
```

One listener can also be registered for several event types, once for each:

```php
$provider->listen(UserWasCreated::class, $auditLog);
$provider->listen(OrderWasPlaced::class, $auditLog);
```

Listeners cannot be removed once they are registered.

## Listeners for an event

`getListenersForEvent()` returns the listeners that apply to an event, in the order above. The dispatcher calls it
once at the start of every dispatch, so a listener registered while an event is being dispatched applies from the
next dispatch onwards, not to the event in progress.
