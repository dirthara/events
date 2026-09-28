<?php

declare(strict_types=1);

namespace Dirthara\Events\Tests\Fixtures;

use Closure;
use Throwable;
use Dirthara\Events\Contract\EventPublisher;

final class RecordingPublisher implements EventPublisher
{
    /**
     * @var list<object>
     */
    public array $published = [];

    public ?Throwable $failure = null;

    public ?object $failingEvent = null;

    /**
     * @param null|Closure(object): void $react
     */
    public function __construct(
        private readonly ?Closure $react = null,
    ) {}

    /**
     * @throws Throwable
     */
    public function publish(object $event): void
    {
        $this->published[] = $event;

        if ($this->failure !== null && ($this->failingEvent === null || $this->failingEvent === $event)) {
            throw $this->failure;
        }

        if ($this->react !== null) {
            ($this->react)($event);
        }
    }
}
