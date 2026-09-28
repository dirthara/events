---
id: dispatching
title: Dispatching events
sidebar_position: 4
description: Dispatch events synchronously with EventDispatcher, stop propagation, and handle exceptions from listeners.
---

## Create a dispatcher

`EventDispatcher` takes the listener provider it asks for listeners:

```php
use Dirthara\Events\EventDispatcher;
use Dirthara\Events\ListenerProvider;

$provider = new ListenerProvider();
$dispatcher = new EventDispatcher($provider);
```

The constructor accepts any PSR-14 `ListenerProviderInterface`, so the dispatcher also works with a provider from
another library. Listeners registered on the provider after the dispatcher is created apply to every later dispatch.

Type code that dispatches events against `Psr\EventDispatcher\EventDispatcherInterface` rather than against
`EventDispatcher`.

## Dispatch an event

```php
$event = $dispatcher->dispatch(new UserWasCreated($user));
```

`dispatch()` calls every listener that applies to the event, in the order the provider returns them, and then returns
the same event object it was given. Every listener receives that same object, so a listener can leave information on
a mutable event for the code that dispatched it:

```php
final class PasswordWasChanged
{
    /**
     * @var list<string>
     */
    public array $warnings = [];

    public function __construct(
        public readonly User $user,
    ) {}
}

$event = $dispatcher->dispatch(new PasswordWasChanged($user));

foreach ($event->warnings as $warning) {
    // ...
}
```

What a listener returns is ignored. An event with no listeners is returned unchanged.

## Dispatch is synchronous

Every listener has run by the time `dispatch()` returns. Nothing is queued or deferred, and a slow listener delays the
code that dispatched the event. To announce an event without depending on when it is handled, [publish](publishing.md)
it instead.

## Stop propagation

An event that implements `Psr\EventDispatcher\StoppableEventInterface` can stop the listeners after the current one
from being called:

```php
use Psr\EventDispatcher\StoppableEventInterface;

final class PaymentWasReceived implements StoppableEventInterface
{
    private bool $handled = false;

    public function markHandled(): void
    {
        $this->handled = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->handled;
    }
}
```

The dispatcher calls `isPropagationStopped()` before each listener, and returns the event as soon as it returns
`true`. A listener that stops the event is itself called to the end, and the listeners after it are not called. An
event that is already stopped when it is dispatched calls no listeners at all.

`StoppableEventInterface` is the only interface the dispatcher looks for. Every other event is passed to every listener
that applies.

## Exceptions thrown by listeners

The dispatcher does not catch anything. An exception or error thrown by a listener leaves `dispatch()` unchanged, and
the listeners after it are not called:

```php
try {
    $dispatcher->dispatch(new OrderWasPlaced($order));
} catch (PaymentGatewayException $exception) {
    // The listeners that ran before this one have already had their effect.
}
```

The listeners that ran before the one that threw are not undone. A listener that must not interrupt the others has to
catch its own exceptions.
