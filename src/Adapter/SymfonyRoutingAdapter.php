<?php

declare(strict_types=1);

namespace RouterBenchmarks\Adapter;

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
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class SymfonyRoutingAdapter extends AbstractRouterAdapter
{
    private RouteCollection $routes;
    private RequestContext $context;
    private UrlMatcherInterface $matcher;

    public function __construct()
    {
        $this->reset();
    }

    public function identity(): RouterIdentity
    {
        return new RouterIdentity(
            'Symfony Routing',
            'symfony/routing',
            'v8.1.0',
            'fe0bfec72c8a806109fb9c3a5f2b898fe0c76eb3',
        );
    }

    public function supports(Feature $feature): bool
    {
        return !\in_array($feature, [Feature::NativeHandlerDispatch, Feature::Finalization], true);
    }

    public function reset(): void
    {
        $this->routes = new RouteCollection();
        $this->context = new RequestContext();
        $this->matcher = new UrlMatcher($this->routes, $this->context);
        $this->definitions = [];
    }

    public function addRoute(GeneratedRoute $route, callable $handler): void
    {
        $this->routes->add($route->id, new Route(
            $route->path,
            ['_handler' => $handler],
            $route->constraints,
            methods: $route->methods,
        ));
        $this->remember($route);
    }

    public function finalize(): FinalizationResult
    {
        $compiled = (new CompiledUrlMatcherDumper($this->routes))->getCompiledRoutes();
        $this->matcher = new CompiledUrlMatcher($compiled, $this->context);

        return new FinalizationResult(FinalizationStatus::Compiled);
    }

    public function prepareRequest(string $method, string $path): PreparedRequest
    {
        return new PreparedRequest(strtoupper($method), $path, null);
    }

    public function match(PreparedRequest $request): MatchResult
    {
        try {
            $parameters = $this->nativeMatch($request);
            $routeId = $parameters['_route'];
            if (!\is_string($routeId)) {
                throw new \LogicException('Symfony route has no benchmark identifier.');
            }
            unset($parameters['_route'], $parameters['_handler']);

            return MatchResult::found($routeId, $this->normalizeParameters($routeId, $parameters));
        } catch (MethodNotAllowedException $exception) {
            return MatchResult::methodNotAllowed(array_values($exception->getAllowedMethods()));
        } catch (ResourceNotFoundException) {
            return MatchResult::notFound();
        }
    }

    public function dispatch(PreparedRequest $request): DispatchResult
    {
        try {
            $parameters = $this->nativeMatch($request);
            $handler = $parameters['_handler'];
            $routeId = $parameters['_route'];
            unset($parameters['_route'], $parameters['_handler']);

            return new DispatchResult(
                MatchStatus::Found,
                DispatchMode::AdapterManaged,
                $handler(...array_values($this->normalizeParameters($routeId, $parameters))),
            );
        } catch (MethodNotAllowedException) {
            return new DispatchResult(MatchStatus::MethodNotAllowed, DispatchMode::AdapterManaged);
        } catch (ResourceNotFoundException) {
            return new DispatchResult(MatchStatus::NotFound, DispatchMode::AdapterManaged);
        }
    }

    /** @return array<string, mixed> */
    private function nativeMatch(PreparedRequest $request): array
    {
        $this->context->setMethod($request->method);

        return $this->matcher->match($request->path);
    }
}
