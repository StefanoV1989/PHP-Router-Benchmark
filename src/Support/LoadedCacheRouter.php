<?php

declare(strict_types=1);

namespace RouterBenchmarks\Support;

final readonly class LoadedCacheRouter
{
    /** @param \Closure(): mixed $dispatch */
    public function __construct(private \Closure $dispatch)
    {
    }

    public function dispatch(): mixed
    {
        return ($this->dispatch)();
    }
}
