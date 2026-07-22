<?php

declare(strict_types=1);

namespace RouterBenchmarks\Adapter;

use Closure;
use Pecee\Http\Url;
use Pecee\SimpleRouter\Exceptions\NotFoundHttpException;
use Pecee\SimpleRouter\Route\RouteUrl;
use Pecee\SimpleRouter\Router;
use RouterBenchmarks\Contract\Feature;
use RouterBenchmarks\Contract\UnsupportedFeature;
use RouterBenchmarks\Dataset\GeneratedRoute;
use RouterBenchmarks\Result\DispatchMode;
use RouterBenchmarks\Result\DispatchResult;
use RouterBenchmarks\Result\FinalizationResult;
use RouterBenchmarks\Result\FinalizationStatus;
use RouterBenchmarks\Result\MatchResult;
use RouterBenchmarks\Result\MatchStatus;
use RouterBenchmarks\Support\PreparedRequest;
use RouterBenchmarks\Support\RouterIdentity;

final class SimplePhpRouterAdapter extends AbstractRouterAdapter
{
    private Router $router;

    public function __construct()
    {
        $this->reset();
    }

    public function identity(): RouterIdentity
    {
        return new RouterIdentity(
            'Simple PHP Router',
            'pecee/simple-router',
            '5.4.1.7',
            'a2843d5b1e037f8b61cc99f27eab52a28bf41dfd',
        );
    }

    public function supports(Feature $feature): bool
    {
        return match ($feature) {
            Feature::NativeHandlerDispatch,
            Feature::ConstrainedParameters,
            Feature::OptionalParameters,
            Feature::Finalization => true,
            default => false,
        };
    }

    public function reset(): void
    {
        $_SERVER['REQUEST_METHOD'] ??= 'GET';
        $_SERVER['REQUEST_URI'] ??= '/';
        $_SERVER['HTTP_HOST'] ??= 'localhost';
        $this->router = new Router();
        $this->definitions = [];
    }

    public function addRoute(GeneratedRoute $route, callable $handler): void
    {
        $nativeHandler = $handler instanceof Closure ? $handler : Closure::fromCallable($handler);
        $native = new RouteUrl($route->path, $nativeHandler);
        $native->setRequestMethods(array_map(strtolower(...), $route->methods));
        if ($route->constraints !== []) {
            $native->where($route->constraints);
        }
        $this->router->addRoute($native);
        $this->remember($route);
    }

    public function finalize(): FinalizationResult
    {
        $this->router->loadRoutes();

        return new FinalizationResult(FinalizationStatus::Finalized);
    }

    public function prepareRequest(string $method, string $path): PreparedRequest
    {
        $native = $this->router->getRequest();
        $native->setMethod(strtolower($method));
        $native->setUrl(new Url(urldecode($path)));
        $native->setLoadedRoutes([]);

        return new PreparedRequest(strtolower($method), $path, $native);
    }

    public function match(PreparedRequest $request): MatchResult
    {
        throw new UnsupportedFeature('Simple PHP Router does not expose handler-free matching.');
    }

    public function dispatch(PreparedRequest $request): DispatchResult
    {
        try {
            return new DispatchResult(
                MatchStatus::Found,
                DispatchMode::Native,
                $this->router->routeRequest(),
            );
        } catch (NotFoundHttpException) {
            return new DispatchResult(MatchStatus::NotFound, DispatchMode::Native);
        }
    }
}
