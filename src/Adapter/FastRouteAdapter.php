<?php

declare(strict_types=1);

namespace RouterBenchmarks\Adapter;

use FastRoute\DataGenerator\GroupCountBased as GroupCountDataGenerator;
use FastRoute\Dispatcher;
use FastRoute\Dispatcher\GroupCountBased as GroupCountDispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
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

final class FastRouteAdapter extends AbstractRouterAdapter
{
    private RouteCollector $collector;
    private ?Dispatcher $dispatcher = null;

    public function __construct()
    {
        $this->reset();
    }

    public function identity(): RouterIdentity
    {
        return new RouterIdentity(
            'FastRoute',
            'nikic/fast-route',
            'v1.3.0',
            '181d480e08d9476e61381e04a71b34dc0432e812',
        );
    }

    public function supports(Feature $feature): bool
    {
        return !\in_array($feature, [Feature::NativeHandlerDispatch, Feature::Finalization], true);
    }

    public function reset(): void
    {
        $this->collector = new RouteCollector(new Std(), new GroupCountDataGenerator());
        $this->dispatcher = null;
        $this->definitions = [];
    }

    public function addRoute(GeneratedRoute $route, callable $handler): void
    {
        $path = preg_replace_callback(
            '#(/?)\{([A-Za-z_][A-Za-z0-9_]*)(\?)?\}#',
            function (array $matches) use ($route): string {
                $constraint = $route->constraints[$matches[2]] ?? null;
                $parameter = '{' . $matches[2] . ($constraint === null ? '' : ':' . $constraint) . '}';

                return ($matches[3] ?? '') === '?'
                    ? '[' . $matches[1] . $parameter . ']'
                    : $matches[1] . $parameter;
            },
            $route->path,
        );
        if (!\is_string($path)) {
            throw new \LogicException('Unable to translate FastRoute route.');
        }
        $this->collector->addRoute($route->methods, $path, [$route->id, $handler]);
        $this->remember($route);
    }

    public function finalize(): FinalizationResult
    {
        $this->dispatcher = new GroupCountDispatcher($this->collector->getData());

        return new FinalizationResult(FinalizationStatus::Compiled);
    }

    public function prepareRequest(string $method, string $path): PreparedRequest
    {
        $path = rawurldecode($path);

        return new PreparedRequest(strtoupper($method), $path, null);
    }

    public function match(PreparedRequest $request): MatchResult
    {
        $result = $this->nativeDispatch($request);

        return match ($result[0]) {
            Dispatcher::FOUND => MatchResult::found(
                $result[1][0],
                $this->normalizeParameters($result[1][0], $result[2]),
            ),
            Dispatcher::METHOD_NOT_ALLOWED => MatchResult::methodNotAllowed($result[1]),
            default => MatchResult::notFound(),
        };
    }

    public function dispatch(PreparedRequest $request): DispatchResult
    {
        $result = $this->nativeDispatch($request);
        if ($result[0] === Dispatcher::NOT_FOUND) {
            return new DispatchResult(MatchStatus::NotFound, DispatchMode::AdapterManaged);
        }
        if ($result[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            return new DispatchResult(MatchStatus::MethodNotAllowed, DispatchMode::AdapterManaged);
        }
        [$routeId, $handler] = $result[1];

        return new DispatchResult(
            MatchStatus::Found,
            DispatchMode::AdapterManaged,
            $handler(...array_values($this->normalizeParameters($routeId, $result[2]))),
        );
    }

    /** @return array<int, mixed> */
    private function nativeDispatch(PreparedRequest $request): array
    {
        if ($this->dispatcher === null) {
            $this->finalize();
        }
        $dispatcher = $this->dispatcher;
        if ($dispatcher === null) {
            throw new \LogicException('FastRoute finalization did not create a dispatcher.');
        }

        return $dispatcher->dispatch($request->method, $request->path);
    }
}
