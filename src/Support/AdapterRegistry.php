<?php

declare(strict_types=1);

namespace RouterBenchmarks\Support;

use RouterBenchmarks\Adapter\AltoRouterAdapter;
use RouterBenchmarks\Adapter\ArielRadixRouterAdapter;
use RouterBenchmarks\Adapter\BramusRouterAdapter;
use RouterBenchmarks\Adapter\FastRouteAdapter;
use RouterBenchmarks\Adapter\IlluminateRoutingAdapter;
use RouterBenchmarks\Adapter\SimplePhpRouterAdapter;
use RouterBenchmarks\Adapter\SymfonyRoutingAdapter;
use RouterBenchmarks\Contract\RouterAdapterInterface;

final class AdapterRegistry
{
    /** @return list<RouterAdapterInterface> */
    public static function all(): array
    {
        return [
            new ArielRadixRouterAdapter(),
            new IlluminateRoutingAdapter(),
            new BramusRouterAdapter(),
            new AltoRouterAdapter(),
            new SymfonyRoutingAdapter(),
            new FastRouteAdapter(),
            new SimplePhpRouterAdapter(),
        ];
    }

    public static function byName(string $name): RouterAdapterInterface
    {
        return match ($name) {
            'Ariel Radix Router' => new ArielRadixRouterAdapter(),
            'Illuminate Routing' => new IlluminateRoutingAdapter(),
            'Bramus Router' => new BramusRouterAdapter(),
            'AltoRouter' => new AltoRouterAdapter(),
            'Symfony Routing' => new SymfonyRoutingAdapter(),
            'FastRoute' => new FastRouteAdapter(),
            'Simple PHP Router' => new SimplePhpRouterAdapter(),
            default => throw new \InvalidArgumentException(\sprintf('Unknown router "%s".', $name)),
        };
    }
}
