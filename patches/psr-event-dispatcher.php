<?php

declare(strict_types=1);

namespace Psr\EventDispatcher;

interface ListenerProviderInterface
{
    /**
     * @return iterable<callable>
     */
    public function getListenersForEvent(object $event): iterable;
}
