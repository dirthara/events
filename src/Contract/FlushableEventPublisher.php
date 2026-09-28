<?php

declare(strict_types=1);

namespace Dirthara\Events\Contract;

interface FlushableEventPublisher extends EventPublisher
{
    public function flush(): void;
}
