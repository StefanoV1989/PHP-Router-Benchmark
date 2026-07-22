<?php

declare(strict_types=1);

namespace RouterBenchmarks\Adapter;

use Closure;
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
use StefanoV1989\ArielRouter\ArielRouter;
use StefanoV1989\ArielRouter\Exception\HttpException;
use StefanoV1989\ArielRouter\Http\Request;

final class ArielRadixRouterAdapter extends AbstractRouterAdapter
{
    private ArielRouter $router;

    /** @var array<int, string> */
    private array $routeIds = [];

    public function __construct()
    {
        $this->reset();
    }

    public function identity(): RouterIdentity
    {
        return new RouterIdentity(
            'Ariel Radix Router',
            'stefanov1989/ariel-radix-router',
            'v1.0.2',
            'dacbe9ec2769e1702264c1f4e766d088c2261c0f',
        );
    }

    public function supports(Feature $feature): bool
    {
        return $feature !== Feature::Finalization;
    }

    public function reset(): void
    {
        $this->router = new ArielRouter();
        $this->routeIds = [];
        $this->definitions = [];
    }

    public function addRoute(GeneratedRoute $route, callable $handler): void
    {
        $nativeHandler = $handler instanceof Closure ? $handler : Closure::fromCallable($handler);
        $native = $this->router->add($route->methods, $route->path, $nativeHandler);
        if ($route->constraints !== []) {
            $native->where($route->constraints);
        }
        $this->routeIds[spl_object_id($native)] = $route->id;
        $this->remember($route);
    }

    public function finalize(): FinalizationResult
    {
        $this->router->compile();

        return new FinalizationResult(FinalizationStatus::Compiled);
    }

    public function prepareRequest(string $method, string $path): PreparedRequest
    {
        return new PreparedRequest($method, $path, new Request($method, $path));
    }

    public function match(PreparedRequest $request): MatchResult
    {
        /** @var Request $native */
        $native = $request->native;
        $result = $this->router->engine()->resolve($native->method(), $native->url()->path());
        if ($result->route === null) {
            return $result->methodNotAllowed ? MatchResult::methodNotAllowed() : MatchResult::notFound();
        }
        $routeId = $this->routeIds[spl_object_id($result->route)];

        return MatchResult::found($routeId, $this->normalizeParameters($routeId, $result->parameters));
    }

    public function dispatch(PreparedRequest $request): DispatchResult
    {
        try {
            return new DispatchResult(
                MatchStatus::Found,
                DispatchMode::Native,
                $this->router->dispatch($request->native),
            );
        } catch (HttpException $exception) {
            return new DispatchResult(
                $exception->getCode() === 405 ? MatchStatus::MethodNotAllowed : MatchStatus::NotFound,
                DispatchMode::Native,
            );
        }
    }
}
