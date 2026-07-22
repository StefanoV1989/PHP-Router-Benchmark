<?php

declare(strict_types=1);

namespace RouterBenchmarks\Support;

final class CacheBenchmarkHandler
{
    public static function handle(mixed ...$parameters): string
    {
        return 'cache|' . implode(',', array_map(
            static fn (mixed $value): string => (string) $value,
            $parameters,
        ));
    }
}
