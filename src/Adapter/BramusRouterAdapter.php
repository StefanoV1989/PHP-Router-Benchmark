<?php

declare(strict_types=1);

namespace RouterBenchmarks\Adapter;

use Bramus\Router\Router;
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

final class BramusRouterAdapter extends AbstractRouterAdapter
{
    private Router $router;
    private mixed $lastResult = null;

    public function __construct()
    {
        $this->reset();
    }

    public function identity(): RouterIdentity
    {
        return new RouterIdentity(
            'Bramus Router',
            'bramus/router',
            '1.6.1',
            '55657b76da8a0a509250fb55b9dd24e1aa237eba',
        );
    }

    public function supports(Feature $feature): bool
    {
        return match ($feature) {
            Feature::NativeHandlerDispatch,
            Feature::ConstrainedParameters,
            Feature::OptionalParameters => true,
            default => false,
        };
    }

    public function reset(): void
    {
        self::initializeGlobals();
        $this->router = new Router();
        $this->router->setBasePath('/');
        $this->router->set404(static function (): void {
        });
        $this->definitions = [];
        $this->lastResult = null;
    }

    public function addRoute(GeneratedRoute $route, callable $handler): void
    {
        $path = preg_replace_callback(
            '#(/?)\{([A-Za-z_][A-Za-z0-9_]*)(\?)?\}#',
            function (array $matches) use ($route): string {
                $constraint = $route->constraints[$matches[2]] ?? '[^/]+';
                $capture = '(' . $constraint . ')';

                return ($matches[3] ?? '') === '?'
                    ? '(?:' . $matches[1] . $capture . ')?'
                    : $matches[1] . $capture;
            },
            $route->path,
        );
        if (!\is_string($path)) {
            throw new \LogicException('Unable to translate Bramus route.');
        }
        $this->router->match(implode('|', $route->methods), $path, function (mixed ...$parameters) use ($handler): void {
            $this->lastResult = $handler(...$parameters);
        });
        $this->remember($route);
    }

    public function finalize(): FinalizationResult
    {
        return new FinalizationResult(FinalizationStatus::NotApplicable);
    }

    public function prepareRequest(string $method, string $path): PreparedRequest
    {
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['REQUEST_URI'] = $path;
        $this->lastResult = null;

        return new PreparedRequest($method, $path, null);
    }

    public function match(PreparedRequest $request): MatchResult
    {
        throw new UnsupportedFeature('Bramus Router does not expose handler-free matching.');
    }

    public function dispatch(PreparedRequest $request): DispatchResult
    {
        $this->lastResult = null;
        $matched = $this->router->run();

        return new DispatchResult(
            $matched ? MatchStatus::Found : MatchStatus::NotFound,
            DispatchMode::Native,
            $this->lastResult,
        );
    }

    private static function initializeGlobals(): void
    {
        $_SERVER['REQUEST_METHOD'] ??= 'GET';
        $_SERVER['REQUEST_URI'] ??= '/';
        $_SERVER['SCRIPT_NAME'] ??= '/index.php';
        $_SERVER['SERVER_PROTOCOL'] ??= 'HTTP/1.1';
    }
}
