<?php

declare(strict_types=1);

namespace RouterBenchmarks\Support;

use Illuminate\Contracts\Events\Dispatcher;

final class NullEventDispatcher implements Dispatcher
{
    /**
     * @param string|array<array-key, mixed> $events
     * @param mixed|array<array-key, mixed> $listener
     */
    public function listen($events, $listener = null): void
    {
    }

    public function hasListeners($eventName): bool
    {
        return false;
    }

    public function subscribe($subscriber): void
    {
    }

    public function until($event, $payload = []): mixed
    {
        return null;
    }

    /** @return array<array-key, mixed>|null */
    public function dispatch($event, $payload = [], $halt = false): ?array
    {
        return $halt ? null : [];
    }

    /** @param array<array-key, mixed> $payload */
    public function push($event, $payload = []): void
    {
    }

    public function flush($event): void
    {
    }

    public function forget($event): void
    {
    }

    public function forgetPushed(): void
    {
    }
}
