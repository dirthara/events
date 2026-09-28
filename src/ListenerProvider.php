<?php

namespace Dirthara\Events;

use Psr\EventDispatcher\ListenerProviderInterface;

class ListenerProvider implements ListenerProviderInterface
{
    private array $listeners = [];

    public function getListenersForEvent(object $event): iterable
    {
        foreach ($this->listeners as $eventType => $listeners) {
            if (!$event instanceof $eventType) {
                continue;
            }

            yield from $listeners;
        }
    }

    public function listen($event, $listener, int $priority = 0): iterable
    {
        // todo
    }
}