<?php

declare(strict_types=1);

namespace Dirthara\Events\Contract;

interface EventPublisher
{
    public function publish(object $event): void;
}
