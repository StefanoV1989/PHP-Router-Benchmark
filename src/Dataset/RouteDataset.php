<?php

declare(strict_types=1);

namespace RouterBenchmarks\Dataset;

final readonly class RouteDataset
{
    /** @param list<GeneratedRoute> $routes */
    public function __construct(public int $seed, public array $routes)
    {
    }

    public function count(): int
    {
        return \count($this->routes);
    }

    public function first(RouteKind $kind): GeneratedRoute
    {
        foreach ($this->routes as $route) {
            if ($route->kind === $kind) {
                return $route;
            }
        }

        throw new \LogicException(\sprintf('Dataset contains no %s route.', $kind->value));
    }

    public function staticAt(float $position): GeneratedRoute
    {
        $routes = array_values(array_filter(
            $this->routes,
            static fn (GeneratedRoute $route): bool => $route->kind === RouteKind::StaticRoute,
        ));
        $index = (int) floor((\count($routes) - 1) * max(0.0, min(1.0, $position)));

        return $routes[$index];
    }
}
