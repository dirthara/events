<?php

declare(strict_types=1);

namespace Dirthara\Events\Tests\Exception;

use RuntimeException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Events\Exception\EventsException;
use Dirthara\Events\Exception\InvalidEventTypeException;

final class InvalidEventTypeExceptionTest extends TestCase
{
    #[Test]
    public function it_carries_nothing_by_default(): void
    {
        $exception = new InvalidEventTypeException();

        self::assertInstanceOf(EventsException::class, $exception);
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertSame('', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
        self::assertSame([], $exception->context);
    }

    #[Test]
    public function it_keeps_a_previous_exception_and_its_context(): void
    {
        $previous = new RuntimeException('cause');
        $exception = new InvalidEventTypeException('message', 3, $previous, ['event' => 'Missing']);

        self::assertSame('message', $exception->getMessage());
        self::assertSame(3, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(['event' => 'Missing'], $exception->context);
    }

    #[Test]
    public function it_merges_what_is_added_to_its_context(): void
    {
        $exception = new InvalidEventTypeException(context: ['event' => 'Missing', 'kept' => true]);

        self::assertSame($exception, $exception->addContext(['event' => 'Replaced', 'priority' => 10]));
        self::assertSame(['event' => 'Replaced', 'kept' => true, 'priority' => 10], $exception->context);
    }

    #[Test]
    public function it_describes_an_event_type_no_object_can_have(): void
    {
        $exception = InvalidEventTypeException::notAnObjectType("Missing\n\0Event\x7f");

        self::assertSame(
            'Unable to listen for "Missing\\n\\000Event\\177": an event type has to be an existing class, interface, or enum.',
            $exception->getMessage(),
        );
        self::assertSame(['event' => "Missing\n\0Event\x7f"], $exception->context);
    }
}
