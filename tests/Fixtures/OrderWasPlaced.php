<?php

declare(strict_types=1);

namespace Dirthara\Events\Tests\Fixtures;

use Psr\EventDispatcher\StoppableEventInterface;

final class OrderWasPlaced implements StoppableEventInterface
{
    public int $propagationChecks = 0;

    public function __construct(
        private bool $stopped = false,
    ) {}

    public function stopPropagation(): void
    {
        $this->stopped = true;
    }

    public function isPropagationStopped(): bool
    {
        $this->propagationChecks++;

        return $this->stopped;
    }
}
