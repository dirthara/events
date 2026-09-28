<?php

declare(strict_types=1);

namespace Dirthara\Events;

use Closure;

/**
 * @internal
 */
final readonly class ListenerRegistration
{
    /**
     * @param class-string $event
     */
    public function __construct(
        public string $event,
        public Closure $listener,
        public int $priority,
        public int $order,
    ) {}

    public function appliesTo(object $event): bool
    {
        return $event instanceof $this->event;
    }

    public static function compare(self $left, self $right): int
    {
        return [$right->priority, $left->order] <=> [$left->priority, $right->order];
    }
}
