<?php

declare(strict_types=1);

namespace Dirthara\Events\Tests\Fixtures;

use Psr\EventDispatcher\ListenerProviderInterface;

final readonly class StaticProvider implements ListenerProviderInterface
{
    /**
     * @param list<callable> $listeners
     */
    public function __construct(
        private array $listeners,
    ) {}

    /**
     * @return iterable<callable>
     */
    public function getListenersForEvent(object $event): iterable
    {
        return $this->listeners;
    }
}
