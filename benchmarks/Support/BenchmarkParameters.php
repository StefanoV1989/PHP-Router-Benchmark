<?php

declare(strict_types=1);

namespace RouterBenchmarks\Benchmarks\Support;

use RouterBenchmarks\Contract\Feature;
use RouterBenchmarks\Support\AdapterRegistry;

final class BenchmarkParameters
{
    /** @return list<int> */
    public static function sizes(): array
    {
        $configured = getenv('ROUTER_BENCH_SIZES');
        if ($configured === false || $configured === '') {
            return [100];
        }

        return array_values(array_map(
            static fn (string $size): int => (int) trim($size),
            explode(',', $configured),
        ));
    }

    /** @return list<string> */
    public static function routers(?Feature $feature = null): array
    {
        $names = [];
        foreach (AdapterRegistry::all() as $adapter) {
            if ($feature === null || $adapter->supports($feature)) {
                $names[] = $adapter->identity()->name;
            }
        }

        $offset = (int) (getenv('ROUTER_BENCH_ORDER_OFFSET') ?: 0);
        if ($names !== []) {
            $offset %= \count($names);
            $names = [...\array_slice($names, $offset), ...\array_slice($names, 0, $offset)];
        }

        return $names;
    }

    /** @return iterable<string, array{router: string, size: int}> */
    public static function combinations(?Feature $feature = null): iterable
    {
        foreach (self::sizes() as $size) {
            foreach (self::routers($feature) as $router) {
                yield $router . '-' . $size => ['router' => $router, 'size' => $size];
            }
        }
    }
}
