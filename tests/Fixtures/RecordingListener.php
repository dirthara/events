<?php

declare(strict_types=1);

namespace Dirthara\Events\Tests\Fixtures;

final class RecordingListener
{
    /**
     * @var list<object>
     */
    public array $received = [];

    public function __invoke(object $event): void
    {
        $this->received[] = $event;
    }

    public function record(object $event): void
    {
        $this->received[] = $event;
    }
}
