<?php

declare(strict_types=1);

namespace RouterBenchmarks\Adapter;

use AltoRouter;
use RouterBenchmarks\Contract\Feature;
use RouterBenchmarks\Dataset\GeneratedRoute;
use RouterBenchmarks\Result\DispatchMode;
use RouterBenchmarks\Result\DispatchResult;
use RouterBenchmarks\Result\FinalizationResult;
use RouterBenchmarks\Result\FinalizationStatus;
use RouterBenchmarks\Result\MatchResult;
use RouterBenchmarks\Result\MatchStatus;
use RouterBenchmarks\Support\PreparedRequest;
use RouterBenchmarks\Support\RouterIdentity;

final class AltoRouterAdapter extends AbstractRouterAdapter
{
    private AltoRouter $router;

    public function __construct()
    {
        $this->reset();
    }

    public function identity(): RouterIdentity
    {
        return new RouterIdentity(
            'AltoRouter',
            'altorouter/altorouter',
            '2.0.3',
            '9931b976423f7334c94f7b5b348be8ab1da3415d',
        );
    }

    public function supports(Feature $feature): bool
    {
        return match ($feature) {
            Feature::MatchWithoutDispatch,
            Feature::ConstrainedParameters,
            Feature::OptionalParameters => true,
            default => false,
        };
    }

    public function reset(): void
    {
        $this->router = new AltoRouter();
        $this->definitions = [];
    }

    public function addRoute(GeneratedRoute $route, callable $handler): void
    {
        $path = $route->path;
        foreach ($route->parameterNames() as $name) {
            $constraint = $route->constraints[$name] ?? '';
            if ($constraint !== '') {
                $type = 'bench_' . substr(hash('sha256', $constraint), 0, 12);
                $this->router->addMatchTypes([$type => $constraint]);
                $replacement = '[' . $type . ':' . $name . ']';
            } else {
                $replacement = '[:' . $name . ']';
            }
            $path = str_replace('{' . $name . '}', $replacement, $path);
            $path = str_replace('{' . $name . '?}', $replacement . '?', $path);
        }
        $this->router->map(implode('|', $route->methods), $path, $handler, $route->id);
        $this->remember($route);
    }

    public function finalize(): FinalizationResult
    {
        return new FinalizationResult(FinalizationStatus::NotApplicable);
    }

    public function prepareRequest(string $method, string $path): PreparedRequest
    {
        return new PreparedRequest($method, $path, [$path, strtoupper($method)]);
    }

    public function match(PreparedRequest $request): MatchResult
    {
        $match = $this->router->match($request->path, strtoupper($request->method));
        if (!\is_array($match)) {
            return MatchResult::notFound();
        }
        $routeId = $match['name'];
        if (!\is_string($routeId)) {
            throw new \LogicException('AltoRouter route has no benchmark identifier.');
        }

        return MatchResult::found($routeId, $this->normalizeParameters($routeId, $match['params']));
    }

    public function dispatch(PreparedRequest $request): DispatchResult
    {
        $match = $this->router->match($request->path, strtoupper($request->method));
        if (!\is_array($match)) {
            return new DispatchResult(MatchStatus::NotFound, DispatchMode::AdapterManaged);
        }

        return new DispatchResult(
            MatchStatus::Found,
            DispatchMode::AdapterManaged,
            ($match['target'])(...array_values($match['params'])),
        );
    }
}
