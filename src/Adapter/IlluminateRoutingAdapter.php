<?php

declare(strict_types=1);

namespace RouterBenchmarks\Adapter;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\CallableDispatcher;
use Illuminate\Routing\Contracts\CallableDispatcher as CallableDispatcherContract;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use RouterBenchmarks\Contract\Feature;
use RouterBenchmarks\Dataset\GeneratedRoute;
use RouterBenchmarks\Result\DispatchMode;
use RouterBenchmarks\Result\DispatchResult;
use RouterBenchmarks\Result\FinalizationResult;
use RouterBenchmarks\Result\FinalizationStatus;
use RouterBenchmarks\Result\MatchResult;
use RouterBenchmarks\Result\MatchStatus;
use RouterBenchmarks\Support\NullEventDispatcher;
use RouterBenchmarks\Support\PreparedRequest;
use RouterBenchmarks\Support\RouterIdentity;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class IlluminateRoutingAdapter extends AbstractRouterAdapter
{
    private Router $router;
    private Container $container;

    public function __construct()
    {
        $this->reset();
    }

    public function identity(): RouterIdentity
    {
        return new RouterIdentity(
            'Illuminate Routing',
            'illuminate/routing',
            'v13.21.1',
            'ead1511bfebcb8540c751e73b1321ffaa582e668',
        );
    }

    public function supports(Feature $feature): bool
    {
        return $feature !== Feature::Finalization;
    }

    public function reset(): void
    {
        $this->container = new Container();
        $this->container->bind(CallableDispatcherContract::class, CallableDispatcher::class);
        $this->router = new Router(new NullEventDispatcher(), $this->container);
        $this->definitions = [];
    }

    public function addRoute(GeneratedRoute $route, callable $handler): void
    {
        $native = $this->router->addRoute($route->methods, $route->path, $handler);
        if ($route->constraints !== []) {
            $native->where($route->constraints);
        }
        $native->name($route->id);
        $this->remember($route);
    }

    public function finalize(): FinalizationResult
    {
        $routes = $this->router->getRoutes();
        if (!$routes instanceof RouteCollection) {
            throw new \LogicException('Illuminate did not expose a compilable route collection.');
        }
        $compiled = $routes->compile();
        $this->router->setCompiledRoutes($compiled);

        return new FinalizationResult(FinalizationStatus::Compiled);
    }

    public function prepareRequest(string $method, string $path): PreparedRequest
    {
        return new PreparedRequest($method, $path, Request::create($path, strtoupper($method)));
    }

    public function match(PreparedRequest $request): MatchResult
    {
        try {
            /** @var Route $route */
            $route = $this->router->getRoutes()->match($request->native);
            $routeId = $route->getName();
            if (!\is_string($routeId)) {
                throw new \LogicException('Illuminate route has no benchmark identifier.');
            }

            return MatchResult::found($routeId, $this->normalizeParameters($routeId, $route->parameters()));
        } catch (MethodNotAllowedHttpException $exception) {
            $allow = $exception->getHeaders()['Allow'] ?? '';

            return MatchResult::methodNotAllowed(
                $allow === '' ? [] : array_map(trim(...), explode(',', $allow)),
            );
        } catch (NotFoundHttpException) {
            return MatchResult::notFound();
        }
    }

    public function dispatch(PreparedRequest $request): DispatchResult
    {
        try {
            $response = $this->router->dispatch($request->native);

            return new DispatchResult(MatchStatus::Found, DispatchMode::Native, $response->getContent());
        } catch (MethodNotAllowedHttpException) {
            return new DispatchResult(MatchStatus::MethodNotAllowed, DispatchMode::Native);
        } catch (NotFoundHttpException) {
            return new DispatchResult(MatchStatus::NotFound, DispatchMode::Native);
        }
    }
}
