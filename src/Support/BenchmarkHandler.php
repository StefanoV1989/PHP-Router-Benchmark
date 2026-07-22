<?php

declare(strict_types=1);

namespace RouterBenchmarks\Support;

final class BenchmarkHandler
{
    public const SEPARATOR = '|';

    public static function result(string $routeId, mixed ...$parameters): string
    {
        return $routeId . self::SEPARATOR . implode(',', array_map(
            static fn (mixed $value): string => (string) $value,
            $parameters,
        ));
    }

    public static function forRoute(string $routeId): \Closure
    {
        return static fn (mixed ...$parameters): string => self::result($routeId, ...$parameters);
    }
}
