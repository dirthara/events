<?php

declare(strict_types=1);

namespace Dirthara\Events\Exception;

use Throwable;
use InvalidArgumentException;

use function sprintf;

final class InvalidEventTypeException extends InvalidArgumentException implements EventsException
{
    use HasExceptionContext;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);

        $this->context = $context;
    }

    public static function notAnObjectType(string $event): self
    {
        return new self(
            message: sprintf(
                'Unable to listen for "%s": an event type has to be an existing class, interface, or enum.',
                self::printable($event),
            ),
            context: ['event' => $event],
        );
    }
}
