<?php

declare(strict_types=1);

namespace Dirthara\Events\Tests;

use LogicException;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use Dirthara\Events\EventDispatcher;
use Dirthara\Events\ListenerProvider;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Events\Tests\Fixtures\UserEvent;
use Dirthara\Events\Tests\Fixtures\DomainEvent;
use Dirthara\Events\Tests\Fixtures\OrderWasPlaced;
use Dirthara\Events\Tests\Fixtures\StaticProvider;
use Dirthara\Events\Tests\Fixtures\UnrelatedEvent;
use Dirthara\Events\Tests\Fixtures\UserWasCreated;

final class EventDispatcherTest extends TestCase
{
    #[Test]
    public function it_returns_the_same_event_when_there_are_no_listeners(): void
    {
        $event = new UserWasCreated();

        self::assertSame($event, new EventDispatcher(new ListenerProvider())->dispatch($event));
    }

    #[Test]
    public function it_gives_every_listener_the_same_event_and_returns_it(): void
    {
        $event = new UserWasCreated();
        $received = [];
        $record = static function (object $given) use (&$received): void {
            $received[] = $given;
        };

        $returned = new EventDispatcher(new StaticProvider([$record, $record, $record]))->dispatch($event);

        self::assertSame($event, $returned);
        self::assertSame([$event, $event, $event], $received);
    }

    #[Test]
    public function it_calls_listeners_in_the_order_the_provider_returns_them(): void
    {
        $calls = [];
        $listener = static function (string $name) use (&$calls): callable {
            return static function (object $_event) use ($name, &$calls): void {
                $calls[] = $name;
            };
        };

        new EventDispatcher(new StaticProvider([$listener('c'), $listener('a'), $listener('b')]))->dispatch(
            new UserWasCreated(),
        );

        self::assertSame(['c', 'a', 'b'], $calls);
    }

    #[Test]
    public function it_ignores_what_a_listener_returns(): void
    {
        $event = new UserWasCreated();
        $calls = 0;

        $returned = new EventDispatcher(new StaticProvider([
            static fn(object $_event): object => new UnrelatedEvent(),
            static fn(object $_event): bool => false,
            static function (object $_event) use (&$calls): void {
                $calls++;
            },
        ]))->dispatch($event);

        self::assertSame($event, $returned);
        self::assertSame(1, $calls);
    }

    #[Test]
    public function it_calls_no_listener_for_an_event_that_is_already_stopped(): void
    {
        $event = new OrderWasPlaced(stopped: true);
        $calls = 0;
        $listener = static function (object $_event) use (&$calls): void {
            $calls++;
        };

        $returned = new EventDispatcher(new StaticProvider([$listener, $listener]))->dispatch($event);

        self::assertSame($event, $returned);
        self::assertSame(0, $calls);
    }

    #[Test]
    public function it_skips_the_remaining_listeners_once_a_listener_stops_the_event(): void
    {
        $event = new OrderWasPlaced();
        $calls = [];

        $returned = new EventDispatcher(new StaticProvider([
            static function (OrderWasPlaced $_event) use (&$calls): void {
                $calls[] = 'first';
            },
            static function (OrderWasPlaced $given) use (&$calls): void {
                $calls[] = 'second';
                $given->stopPropagation();
            },
            static function (OrderWasPlaced $_event) use (&$calls): void {
                $calls[] = 'third';
            },
        ]))->dispatch($event);

        self::assertSame($event, $returned);
        self::assertSame(['first', 'second'], $calls);
    }

    #[Test]
    public function it_checks_propagation_before_each_listener(): void
    {
        $event = new OrderWasPlaced();
        $checks = [];
        $listener = static function (OrderWasPlaced $given) use (&$checks): void {
            $checks[] = $given->propagationChecks;
        };

        new EventDispatcher(new StaticProvider([$listener, $listener, $listener]))->dispatch($event);

        self::assertSame([1, 2, 3], $checks);
    }

    #[Test]
    public function it_lets_a_listener_exception_through_unchanged_and_calls_no_later_listener(): void
    {
        $thrown = new RuntimeException('Listener failed.');
        $calls = [];

        $dispatcher = new EventDispatcher(new StaticProvider([
            static function (object $_event) use (&$calls): void {
                $calls[] = 'first';
            },
            static function (object $_event) use ($thrown): never {
                throw $thrown;
            },
            static function (object $_event) use (&$calls): void {
                $calls[] = 'third';
            },
        ]));

        try {
            $dispatcher->dispatch(new UserWasCreated());
            self::fail('The listener exception was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertSame($thrown, $exception);
        }

        self::assertSame(['first'], $calls);
    }

    #[Test]
    public function it_lets_a_listener_error_through_unchanged(): void
    {
        $thrown = new LogicException('Listener error.');

        $this->expectExceptionObject($thrown);

        new EventDispatcher(new StaticProvider([
            static function (object $_event) use ($thrown): never {
                throw $thrown;
            },
        ]))->dispatch(new UserWasCreated());
    }

    #[Test]
    public function it_dispatches_to_parent_and_interface_listeners_through_the_provider(): void
    {
        $provider = new ListenerProvider();
        $event = new UserWasCreated();
        $calls = [];

        $provider->listen(DomainEvent::class, static function (DomainEvent $given) use (&$calls, $event): void {
            self::assertSame($event, $given);
            $calls[] = 'interface';
        });
        $provider->listen(UserEvent::class, static function (UserEvent $given) use (&$calls, $event): void {
            self::assertSame($event, $given);
            $calls[] = 'parent';
        });
        $provider->listen(UnrelatedEvent::class, static function (UnrelatedEvent $_event) use (&$calls): void {
            $calls[] = 'unrelated';
        });

        self::assertSame($event, new EventDispatcher($provider)->dispatch($event));
        self::assertSame(['interface', 'parent'], $calls);
    }

    #[Test]
    public function it_dispatches_in_priority_order_through_the_provider(): void
    {
        $provider = new ListenerProvider();
        $calls = [];

        $provider->listen(DomainEvent::class, static function (DomainEvent $_event) use (&$calls): void {
            $calls[] = 'interface at 0';
        });
        $provider->listen(
            UserWasCreated::class,
            static function (UserWasCreated $_event) use (&$calls): void {
                $calls[] = 'exact at -10';
            },
            priority: -10,
        );
        $provider->listen(
            UserEvent::class,
            static function (UserEvent $_event) use (&$calls): void {
                $calls[] = 'parent at 100';
            },
            priority: 100,
        );
        $provider->listen(UserWasCreated::class, static function (UserWasCreated $_event) use (&$calls): void {
            $calls[] = 'exact at 0';
        });

        new EventDispatcher($provider)->dispatch(new UserWasCreated());

        self::assertSame(['parent at 100', 'interface at 0', 'exact at 0', 'exact at -10'], $calls);
    }

    #[Test]
    public function it_stops_a_stoppable_event_between_provider_listeners(): void
    {
        $provider = new ListenerProvider();
        $calls = [];

        $provider->listen(
            OrderWasPlaced::class,
            static function (OrderWasPlaced $_event) use (&$calls): void {
                $calls[] = 'late';
            },
            priority: -1,
        );
        $provider->listen(OrderWasPlaced::class, static function (OrderWasPlaced $event) use (&$calls): void {
            $calls[] = 'stopper';
            $event->stopPropagation();
        });

        new EventDispatcher($provider)->dispatch(new OrderWasPlaced());

        self::assertSame(['stopper'], $calls);
    }
}
