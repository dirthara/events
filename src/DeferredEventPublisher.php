<?php

declare(strict_types=1);

namespace Dirthara\Events;

use Dirthara\Events\Contract\EventPublisher;
use Dirthara\Events\Contract\FlushableEventPublisher;

use function array_shift;

final class DeferredEventPublisher implements FlushableEventPublisher
{
    /**
     * @var list<object>
     */
    private array $events = [];

    private bool $flushing = false;

    public function __construct(
        private readonly EventPublisher $publisher,
    ) {}

    public function publish(object $event): void
    {
        $this->events[] = $event;
    }

    public function flush(): void
    {
        if ($this->flushing) {
            return;
        }

        $this->flushing = true;

        try {
            while ($this->events !== []) {
                $this->publisher->publish($this->events[0]);

                array_shift($this->events);
            }
        } finally {
            $this->flushing = false;
        }
    }
}
