<?php

declare(strict_types=1);

namespace RouterBenchmarks\Contract;

use RouterBenchmarks\Dataset\GeneratedRoute;
use RouterBenchmarks\Result\DispatchResult;
use RouterBenchmarks\Result\FinalizationResult;
use RouterBenchmarks\Result\MatchResult;
use RouterBenchmarks\Support\PreparedRequest;
use RouterBenchmarks\Support\RouterIdentity;

interface RouterAdapterInterface
{
    public function identity(): RouterIdentity;

    public function supports(Feature $feature): bool;

    public function reset(): void;

    public function addRoute(GeneratedRoute $route, callable $handler): void;

    public function registeredRouteCount(): int;

    public function finalize(): FinalizationResult;

    public function prepareRequest(string $method, string $path): PreparedRequest;

    public function match(PreparedRequest $request): MatchResult;

    public function dispatch(PreparedRequest $request): DispatchResult;
}
