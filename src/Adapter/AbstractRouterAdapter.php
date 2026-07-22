<?php

declare(strict_types=1);

namespace RouterBenchmarks\Adapter;

use RouterBenchmarks\Contract\RouterAdapterInterface;
use RouterBenchmarks\Dataset\GeneratedRoute;

abstract class AbstractRouterAdapter implements RouterAdapterInterface
{
    /** @var array<string, GeneratedRoute> */
    protected array $definitions = [];

    public function registeredRouteCount(): int
    {
        return \count($this->definitions);
    }

    /**
     * @param array<int|string, mixed> $parameters
     * @return array<string, string>
     */
    protected function normalizeParameters(string $routeId, array $parameters): array
    {
        $definition = $this->definitions[$routeId];
        $normalized = [];
        foreach ($definition->parameterNames() as $index => $name) {
            $value = $parameters[$name] ?? $parameters[$index] ?? null;
            if ($value !== null && $value !== '') {
                $normalized[$name] = (string) $value;
            }
        }

        return $normalized;
    }

    protected function remember(GeneratedRoute $route): void
    {
        $this->definitions[$route->id] = $route;
    }
}
