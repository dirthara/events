<?php

declare(strict_types=1);

namespace Dirthara\Events\Exception;

use Throwable;

interface EventsException extends Throwable
{
    /**
     * @var array<string, mixed>
     */
    public array $context { get; }

    /**
     * @param array<string, mixed> $context
     */
    public function addContext(array $context): static;
}
