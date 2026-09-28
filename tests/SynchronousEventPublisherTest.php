<?php

declare(strict_types=1);

namespace Dirthara\Events\Tests;

use stdClass;
use LogicException;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use Dirthara\Events\EventDispatcher;
use Dirthara\Events\ListenerProvider;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Events\Contract\EventPublisher;
use Dirthara\Events\SynchronousEventPublisher;
use Dirthara\Events\Tests\Fixtures\UserWasCreated;
use Dirthara\Events\Tests\Fixtures\RecordingListener;
use Dirthara\Events\Tests\Fixtures\RecordingDispatcher;

final class SynchronousEventPublisherTest extends TestCase
{
    #[Test]
    public function it_is_an_event_publisher(): void
    {
        self::assertInstanceOf(EventPublisher::class, new SynchronousEventPublisher(new RecordingDispatcher()));
    }

    #[Test]
    public function it_dispatches_the_same_event_exactly_once(): void
    {
        $dispatcher = new RecordingDispatcher();
        $event = new UserWasCreated();

        new SynchronousEventPublisher($dispatcher)->publish($event);

        self::assertSame([$event], $dispatcher->dispatched);
    }

    #[Test]
    public function it_ignores_what_the_dispatcher_returns(): void
    {
        $dispatcher = new RecordingDispatcher();
        $publisher = new SynchronousEventPublisher($dispatcher);
        $first = new UserWasCreated();
        $second = new UserWasCreated();

        $publisher->publish($first);
        $publisher->publish($second);

        self::assertSame([$first, $second], $dispatcher->dispatched);
    }

    #[Test]
    public function it_lets_a_dispatcher_exception_through_unchanged(): void
    {
        $thrown = new RuntimeException('Dispatch failed.');
        $dispatcher = new RecordingDispatcher($thrown);
        $event = new UserWasCreated();

        try {
            new SynchronousEventPublisher($dispatcher)->publish($event);
            self::fail('The dispatcher exception was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertSame($thrown, $exception);
        }

        self::assertSame([$event], $dispatcher->dispatched);
    }

    #[Test]
    public function it_lets_a_dispatcher_error_through_unchanged(): void
    {
        $thrown = new LogicException('Dispatch error.');

        $this->expectExceptionObject($thrown);

        new SynchronousEventPublisher(new RecordingDispatcher($thrown))->publish(new UserWasCreated());
    }

    #[Test]
    public function it_publishes_a_plain_object_through_the_event_dispatcher(): void
    {
        $provider = new ListenerProvider();
        $listener = new RecordingListener();
        $event = new stdClass();

        $provider->listen(stdClass::class, $listener);

        new SynchronousEventPublisher(new EventDispatcher($provider))->publish($event);

        self::assertSame([$event], $listener->received);
    }
}
