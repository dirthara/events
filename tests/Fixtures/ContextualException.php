<?php

declare(strict_types=1);

namespace Dirthara\Events\Tests\Fixtures;

use RuntimeException;
use Dirthara\Events\Exception\EventsException;
use Dirthara\Events\Exception\HasExceptionContext;

final class ContextualException extends RuntimeException implements EventsException
{
    use HasExceptionContext;

    public static function describe(string $value): string
    {
        return self::printable($value);
    }
}
