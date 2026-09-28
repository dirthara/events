<?php

declare(strict_types=1);

namespace Dirthara\Events\Tests\Fixtures;

use Throwable;
use Psr\EventDispatcher\EventDispatcherInterface;

final class RecordingDispatcher implements EventDispatcherInterface
{
    /**
     * @var list<object>
     */
    public array $dispatched = [];

    public function __construct(
        private readonly ?Throwable $failure = null,
    ) {}

    /**
     * @throws Throwable
     */
    public function dispatch(object $event): object
    {
        $this->dispatched[] = $event;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new UnrelatedEvent();
    }
}
