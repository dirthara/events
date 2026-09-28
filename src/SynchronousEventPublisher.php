<?php

declare(strict_types=1);

namespace Dirthara\Events;

use Dirthara\Events\Contract\EventPublisher;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class SynchronousEventPublisher implements EventPublisher
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {}

    public function publish(object $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
