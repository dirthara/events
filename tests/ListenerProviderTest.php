<?php

declare(strict_types=1);

namespace Dirthara\Events\Tests;

use PHPUnit\Framework\TestCase;
use Dirthara\Events\ListenerProvider;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Events\Tests\Fixtures\Signal;
use PHPUnit\Framework\Attributes\TestWith;
use Dirthara\Events\Tests\Fixtures\UserEvent;
use Dirthara\Events\Exception\EventsException;
use Dirthara\Events\Tests\Fixtures\DomainEvent;
use Dirthara\Events\Tests\Fixtures\Timestamped;
use Dirthara\Events\Tests\Fixtures\UnrelatedEvent;
use Dirthara\Events\Tests\Fixtures\UserWasCreated;
use Dirthara\Events\Tests\Fixtures\RecordingListener;
use Dirthara\Events\Exception\InvalidEventTypeException;

use function iterator_to_array;

final class ListenerProviderTest extends TestCase
{
    #[Test]
    public function it_returns_no_listeners_when_none_are_registered(): void
    {
        self::assertSame([], $this->listenersFor(new ListenerProvider(), new UserWasCreated()));
    }

    #[Test]
    public function it_returns_a_listener_registered_for_the_exact_class(): void
    {
        $provider = new ListenerProvider();
        $listener = static function (UserWasCreated $_event): void {};

        $provider->listen(UserWasCreated::class, $listener);

        self::assertSame([$listener], $this->listenersFor($provider, new UserWasCreated()));
    }

    #[Test]
    public function it_leaves_out_a_listener_registered_for_an_unrelated_class(): void
    {
        $provider = new ListenerProvider();
        $provider->listen(UnrelatedEvent::class, static function (UnrelatedEvent $_event): void {});

        self::assertSame([], $this->listenersFor($provider, new UserWasCreated()));
    }

    #[Test]
    public function it_returns_a_listener_registered_for_a_parent_class(): void
    {
        $provider = new ListenerProvider();
        $listener = static function (UserEvent $_event): void {};

        $provider->listen(UserEvent::class, $listener);

        self::assertSame([$listener], $this->listenersFor($provider, new UserWasCreated()));
    }

    #[Test]
    public function it_returns_a_listener_registered_for_an_implemented_interface(): void
    {
        $provider = new ListenerProvider();
        $listener = static function (DomainEvent $_event): void {};

        $provider->listen(DomainEvent::class, $listener);

        self::assertSame([$listener], $this->listenersFor($provider, new UserWasCreated()));
        self::assertSame([], $this->listenersFor($provider, new UnrelatedEvent()));
    }

    #[Test]
    public function it_returns_a_listener_registered_for_an_enum(): void
    {
        $provider = new ListenerProvider();
        $listener = static function (Signal $_event): void {};

        $provider->listen(Signal::class, $listener);

        self::assertSame([$listener], $this->listenersFor($provider, Signal::Started));
        self::assertSame([$listener], $this->listenersFor($provider, Signal::Stopped));
        self::assertSame([], $this->listenersFor($provider, new UserWasCreated()));
    }

    #[Test]
    public function it_returns_every_matching_listener_and_only_those(): void
    {
        $provider = new ListenerProvider();
        $exact = static function (UserWasCreated $_event): void {};
        $parent = static function (UserEvent $_event): void {};
        $interface = static function (DomainEvent $_event): void {};

        $provider->listen(UserWasCreated::class, $exact);
        $provider->listen(UnrelatedEvent::class, static function (UnrelatedEvent $_event): void {});
        $provider->listen(UserEvent::class, $parent);
        $provider->listen(DomainEvent::class, $interface);

        self::assertSame([$exact, $parent, $interface], $this->listenersFor($provider, new UserWasCreated()));
    }

    #[Test]
    public function it_returns_higher_priorities_first(): void
    {
        $provider = new ListenerProvider();
        $low = static function (UserWasCreated $_event): void {};
        $high = static function (UserWasCreated $_event): void {};
        $middle = static function (UserWasCreated $_event): void {};

        $provider->listen(UserWasCreated::class, $low, priority: 1);
        $provider->listen(UserWasCreated::class, $high, priority: 100);
        $provider->listen(UserWasCreated::class, $middle, priority: 10);

        self::assertSame([$high, $middle, $low], $this->listenersFor($provider, new UserWasCreated()));
    }

    #[Test]
    public function it_returns_negative_priorities_after_the_default(): void
    {
        $provider = new ListenerProvider();
        $late = static function (UserWasCreated $_event): void {};
        $default = static function (UserWasCreated $_event): void {};
        $latest = static function (UserWasCreated $_event): void {};

        $provider->listen(UserWasCreated::class, $late, priority: -100);
        $provider->listen(UserWasCreated::class, $default);
        $provider->listen(UserWasCreated::class, $latest, priority: -200);

        self::assertSame([$default, $late, $latest], $this->listenersFor($provider, new UserWasCreated()));
    }

    #[Test]
    public function it_keeps_registration_order_within_a_priority(): void
    {
        $provider = new ListenerProvider();
        $one = static function (UserWasCreated $_event): void {};
        $two = static function (UserWasCreated $_event): void {};
        $three = static function (UserWasCreated $_event): void {};

        $provider->listen(UserWasCreated::class, $one, priority: 0);
        $provider->listen(UserWasCreated::class, $two, priority: 100);
        $provider->listen(UserWasCreated::class, $three, priority: 0);

        self::assertSame([$two, $one, $three], $this->listenersFor($provider, new UserWasCreated()));
    }

    #[Test]
    public function it_orders_by_priority_across_exact_parent_and_interface_registrations(): void
    {
        $provider = new ListenerProvider();
        $interface = static function (DomainEvent $_event): void {};
        $exact = static function (UserWasCreated $_event): void {};
        $parent = static function (UserEvent $_event): void {};

        $provider->listen(DomainEvent::class, $interface, priority: 0);
        $provider->listen(UserWasCreated::class, $exact, priority: 100);
        $provider->listen(UserEvent::class, $parent, priority: 50);

        self::assertSame([$exact, $parent, $interface], $this->listenersFor($provider, new UserWasCreated()));
    }

    #[Test]
    public function it_keeps_registration_order_within_a_priority_across_event_types(): void
    {
        $provider = new ListenerProvider();
        $first = static function (DomainEvent $_event): void {};
        $second = static function (UserWasCreated $_event): void {};
        $third = static function (DomainEvent $_event): void {};
        $fourth = static function (UserEvent $_event): void {};

        $provider->listen(DomainEvent::class, $first);
        $provider->listen(UserWasCreated::class, $second);
        $provider->listen(DomainEvent::class, $third);
        $provider->listen(UserEvent::class, $fourth);

        self::assertSame([$first, $second, $third, $fourth], $this->listenersFor($provider, new UserWasCreated()));
    }

    #[Test]
    public function it_keeps_every_registration_of_the_same_listener(): void
    {
        $provider = new ListenerProvider();
        $listener = static function (UserWasCreated $_event): void {};
        $other = static function (UserWasCreated $_event): void {};

        $provider->listen(UserWasCreated::class, $listener);
        $provider->listen(UserWasCreated::class, $other);
        $provider->listen(UserWasCreated::class, $listener);

        self::assertSame([$listener, $other, $listener], $this->listenersFor($provider, new UserWasCreated()));
    }

    #[Test]
    public function it_registers_one_listener_for_several_event_types(): void
    {
        $provider = new ListenerProvider();
        $listener = static function (object $_event): void {};

        $provider->listen(UserWasCreated::class, $listener);
        $provider->listen(UnrelatedEvent::class, $listener);

        self::assertSame([$listener], $this->listenersFor($provider, new UserWasCreated()));
        self::assertSame([$listener], $this->listenersFor($provider, new UnrelatedEvent()));
        self::assertSame([], $this->listenersFor($provider, Signal::Started));
    }

    #[Test]
    public function it_accepts_any_callable_as_a_listener(): void
    {
        $provider = new ListenerProvider();
        $invokable = new RecordingListener();
        $method = new RecordingListener();
        $event = new UserWasCreated();

        $provider->listen(UserWasCreated::class, $invokable);
        $provider->listen(UserWasCreated::class, [$method, 'record']);
        $provider->listen(UserWasCreated::class, $method->record(...));

        foreach ($this->listenersFor($provider, $event) as $listener) {
            $listener($event);
        }

        self::assertSame([$event], $invokable->received);
        self::assertSame([$event, $event], $method->received);
    }

    #[Test]
    #[TestWith(['Dirthara\\Events\\Tests\\Fixtures\\Missing'])]
    #[TestWith([''])]
    #[TestWith(['int'])]
    #[TestWith([Timestamped::class])]
    public function it_rejects_an_event_type_no_object_can_have(string $event): void
    {
        $provider = new ListenerProvider();

        try {
            $provider->listen($event, static function (object $_event): void {});
            self::fail('The event type was accepted.');
        } catch (InvalidEventTypeException $exception) {
            self::assertInstanceOf(EventsException::class, $exception);
            self::assertSame(['event' => $event], $exception->context);
        }

        self::assertSame([], $this->listenersFor($provider, new UserWasCreated()));
    }

    #[Test]
    public function it_escapes_control_characters_in_a_rejected_event_type(): void
    {
        try {
            new ListenerProvider()->listen("Missing\nForged log line", static function (object $_event): void {});
            self::fail('The event type was accepted.');
        } catch (InvalidEventTypeException $exception) {
            self::assertSame(
                'Unable to listen for "Missing\\nForged log line": an event type has to be an existing class, interface, or enum.',
                $exception->getMessage(),
            );
            self::assertSame(['event' => "Missing\nForged log line"], $exception->context);
        }
    }

    /**
     * @return list<callable>
     */
    private function listenersFor(ListenerProvider $provider, object $event): array
    {
        return [...iterator_to_array($provider->getListenersForEvent($event), preserve_keys: false)];
    }
}
