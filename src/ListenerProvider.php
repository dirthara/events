<?php

declare(strict_types=1);

namespace Dirthara\Events;

use Closure;
use Psr\EventDispatcher\ListenerProviderInterface;
use Dirthara\Events\Exception\InvalidEventTypeException;

use function usort;
use function array_map;
use function array_filter;
use function array_values;
use function class_exists;
use function interface_exists;

final class ListenerProvider implements ListenerProviderInterface
{
    /**
     * @var list<ListenerRegistration>
     */
    private array $registrations = [];

    private int $order = 0;

    /**
     * @param class-string $event
     *
     * @throws InvalidEventTypeException
     */
    public function listen(string $event, callable $listener, int $priority = 0): void
    {
        if (!class_exists($event) && !interface_exists($event)) {
            throw InvalidEventTypeException::notAnObjectType($event);
        }

        $this->registrations[] = new ListenerRegistration(
            $event,
            $listener(...),
            $priority,
            $this->order++,
        );

        usort($this->registrations, ListenerRegistration::compare(...));
    }

    /**
     * @return iterable<callable>
     */
    public function getListenersForEvent(object $event): iterable
    {
        return array_filter(
            $this->registrations,
            static fn(ListenerRegistration $registration): bool => $registration->appliesTo($event),
        )
            |> array_values(...)
            |> (static fn(array $x) => array_map(
                static fn(ListenerRegistration $registration): Closure => $registration->listener,
                $x,
            ));
    }
}
