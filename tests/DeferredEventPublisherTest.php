<?php

declare(strict_types=1);

namespace Dirthara\Events\Tests;

use stdClass;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use Dirthara\Events\EventDispatcher;
use Dirthara\Events\ListenerProvider;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Events\DeferredEventPublisher;
use Dirthara\Events\SynchronousEventPublisher;
use Dirthara\Events\Tests\Fixtures\UserWasCreated;
use Dirthara\Events\Contract\FlushableEventPublisher;
use Dirthara\Events\Tests\Fixtures\RecordingListener;
use Dirthara\Events\Tests\Fixtures\RecordingPublisher;

final class DeferredEventPublisherTest extends TestCase
{
    #[Test]
    public function it_is_a_flushable_event_publisher(): void
    {
        self::assertInstanceOf(FlushableEventPublisher::class, new DeferredEventPublisher(new RecordingPublisher()));
    }

    #[Test]
    public function it_does_not_publish_an_event_before_it_is_flushed(): void
    {
        $inner = new RecordingPublisher();

        new DeferredEventPublisher($inner)->publish(new UserWasCreated());

        self::assertSame([], $inner->published);
    }

    #[Test]
    public function it_publishes_the_same_event_when_it_is_flushed(): void
    {
        $inner = new RecordingPublisher();
        $publisher = new DeferredEventPublisher($inner);
        $event = new UserWasCreated();

        $publisher->publish($event);
        $publisher->flush();

        self::assertSame([$event], $inner->published);
    }

    #[Test]
    public function it_publishes_events_in_the_order_they_were_published(): void
    {
        $inner = new RecordingPublisher();
        $publisher = new DeferredEventPublisher($inner);
        $first = new UserWasCreated();
        $second = new stdClass();
        $third = new UserWasCreated();

        $publisher->publish($first);
        $publisher->publish($second);
        $publisher->publish($third);
        $publisher->flush();

        self::assertSame([$first, $second, $third], $inner->published);
    }

    #[Test]
    public function it_keeps_an_event_published_twice_as_two_events(): void
    {
        $inner = new RecordingPublisher();
        $publisher = new DeferredEventPublisher($inner);
        $event = new UserWasCreated();

        $publisher->publish($event);
        $publisher->publish($event);
        $publisher->flush();

        self::assertSame([$event, $event], $inner->published);
    }

    #[Test]
    public function it_does_nothing_when_flushed_with_no_events(): void
    {
        $inner = new RecordingPublisher();

        new DeferredEventPublisher($inner)->flush();

        self::assertSame([], $inner->published);
    }

    #[Test]
    public function it_empties_its_buffer_once_a_flush_succeeds(): void
    {
        $inner = new RecordingPublisher();
        $publisher = new DeferredEventPublisher($inner);
        $first = new UserWasCreated();
        $second = new UserWasCreated();

        $publisher->publish($first);
        $publisher->flush();
        $publisher->flush();

        self::assertSame([$first], $inner->published);

        $publisher->publish($second);
        $publisher->flush();

        self::assertSame([$first, $second], $inner->published);
    }

    #[Test]
    public function it_lets_an_exception_through_unchanged_and_keeps_the_failed_and_later_events(): void
    {
        $events = [new UserWasCreated(), new UserWasCreated(), new UserWasCreated(), new UserWasCreated()];
        $inner = new RecordingPublisher();
        $inner->failure = new RuntimeException('Publishing failed.');
        $inner->failingEvent = $events[1];
        $publisher = new DeferredEventPublisher($inner);

        foreach ($events as $event) {
            $publisher->publish($event);
        }

        self::assertFlushFails($publisher, $inner);

        self::assertSame([$events[0], $events[1]], $inner->published);

        $inner->failure = null;
        $inner->published = [];
        $publisher->flush();

        self::assertSame([$events[1], $events[2], $events[3]], $inner->published);
    }

    #[Test]
    public function it_retries_a_failed_event_on_every_flush_until_it_succeeds(): void
    {
        $event = new UserWasCreated();
        $inner = new RecordingPublisher();
        $inner->failure = new RuntimeException('Publishing failed.');
        $publisher = new DeferredEventPublisher($inner);

        $publisher->publish($event);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            self::assertFlushFails($publisher, $inner);
        }

        $inner->failure = null;
        $publisher->flush();
        $publisher->flush();

        self::assertSame([$event, $event, $event], $inner->published);
    }

    #[Test]
    public function it_keeps_an_event_published_after_a_failure_behind_the_failed_one(): void
    {
        $failed = new UserWasCreated();
        $later = new UserWasCreated();
        $inner = new RecordingPublisher();
        $inner->failure = new RuntimeException('Publishing failed.');
        $publisher = new DeferredEventPublisher($inner);

        $publisher->publish($failed);

        self::assertFlushFails($publisher, $inner);

        $publisher->publish($later);
        $inner->failure = null;
        $inner->published = [];
        $publisher->flush();

        self::assertSame([$failed, $later], $inner->published);
    }

    #[Test]
    public function it_publishes_events_published_during_a_flush_before_the_flush_returns(): void
    {
        $a = new UserWasCreated();
        $b = new UserWasCreated();
        $c = new stdClass();
        $d = new stdClass();
        $publisher = null;
        $inner = new RecordingPublisher(static function (object $event) use ($a, $c, $d, &$publisher): void {
            if ($event === $a) {
                $publisher?->publish($c);
            }

            if ($event === $c) {
                $publisher?->publish($d);
            }
        });
        $publisher = new DeferredEventPublisher($inner);

        $publisher->publish($a);
        $publisher->publish($b);
        $publisher->flush();

        self::assertSame([$a, $b, $c, $d], $inner->published);
    }

    #[Test]
    public function it_ignores_a_flush_requested_during_a_flush_and_publishes_each_event_once(): void
    {
        $first = new UserWasCreated();
        $second = new UserWasCreated();
        $publisher = null;
        $inner = new RecordingPublisher(static function (object $_event) use (&$publisher): void {
            $publisher?->flush();
        });
        $publisher = new DeferredEventPublisher($inner);

        $publisher->publish($first);
        $publisher->publish($second);
        $publisher->flush();

        self::assertSame([$first, $second], $inner->published);
    }

    #[Test]
    public function it_publishes_an_event_as_it_is_when_flushed_rather_than_when_published(): void
    {
        $inner = new RecordingPublisher();
        $publisher = new DeferredEventPublisher($inner);
        $event = new stdClass();
        $event->status = 'published';

        $publisher->publish($event);
        $event->status = 'changed';
        $publisher->flush();

        self::assertSame([$event], $inner->published);
        self::assertSame('changed', $event->status);
    }

    #[Test]
    public function it_publishes_through_a_synchronous_publisher_when_flushed(): void
    {
        $provider = new ListenerProvider();
        $listener = new RecordingListener();
        $event = new UserWasCreated();

        $provider->listen(UserWasCreated::class, $listener);

        $publisher = new DeferredEventPublisher(new SynchronousEventPublisher(new EventDispatcher($provider)));
        $publisher->publish($event);

        self::assertSame([], $listener->received);

        $publisher->flush();

        self::assertSame([$event], $listener->received);
    }

    #[Test]
    public function it_publishes_through_another_deferred_publisher(): void
    {
        $inner = new RecordingPublisher();
        $deferred = new DeferredEventPublisher($inner);
        $publisher = new DeferredEventPublisher($deferred);
        $event = new UserWasCreated();

        $publisher->publish($event);
        $publisher->flush();

        self::assertSame([], $inner->published);

        $deferred->flush();

        self::assertSame([$event], $inner->published);
    }

    private static function assertFlushFails(DeferredEventPublisher $publisher, RecordingPublisher $inner): void
    {
        try {
            $publisher->flush();
            self::fail('The publisher exception was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertSame($inner->failure, $exception);
        }
    }
}
