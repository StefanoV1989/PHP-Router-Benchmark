<?php

declare(strict_types=1);

namespace RouterBenchmarks\Support;

use function FastRoute\cachedDispatcher;

use FastRoute\RouteCollector;
use Illuminate\Container\Container;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Routing\CallableDispatcher;
use Illuminate\Routing\Contracts\CallableDispatcher as CallableDispatcherContract;
use Illuminate\Routing\RouteCollection as IlluminateRouteCollection;
use Illuminate\Routing\Router as IlluminateRouter;
use RouterBenchmarks\Dataset\GeneratedRoute;
use RouterBenchmarks\Dataset\RouteDataset;
use StefanoV1989\ArielRouter\ArielRouter;
use StefanoV1989\ArielRouter\Http\Request as ArielRequest;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route as SymfonyRoute;
use Symfony\Component\Routing\RouteCollection;

final class NativeCacheLifecycle
{
    /** @return list<string> */
    public static function routers(): array
    {
        return ['Ariel Radix Router', 'Illuminate Routing', 'Symfony Routing', 'FastRoute'];
    }

    public static function generate(string $router, RouteDataset $dataset, string $file): void
    {
        match ($router) {
            'Ariel Radix Router' => self::generateAriel($dataset, $file),
            'Illuminate Routing' => self::generateIlluminate($dataset, $file),
            'Symfony Routing' => self::generateSymfony($dataset, $file),
            'FastRoute' => self::generateFastRoute($dataset, $file),
            default => throw new \InvalidArgumentException('Router has no compiled cache benchmark.'),
        };
    }

    public static function load(
        string $router,
        string $file,
        string $method,
        string $path,
    ): LoadedCacheRouter {
        return match ($router) {
            'Ariel Radix Router' => self::loadAriel($file, $method, $path),
            'Illuminate Routing' => self::loadIlluminate($file, $method, $path),
            'Symfony Routing' => self::loadSymfony($file, $method, $path),
            'FastRoute' => self::loadFastRoute($file, $method, $path),
            default => throw new \InvalidArgumentException('Router has no compiled cache benchmark.'),
        };
    }

    private static function generateAriel(RouteDataset $dataset, string $file): void
    {
        $router = new ArielRouter();
        foreach ($dataset->routes as $route) {
            $native = $router->add($route->methods, $route->path, [CacheBenchmarkHandler::class, 'handle']);
            if ($route->constraints !== []) {
                $native->where($route->constraints);
            }
        }
        self::writePhpArray($file, $router->compiledPayload());
    }

    private static function generateIlluminate(RouteDataset $dataset, string $file): void
    {
        [$router] = self::newIlluminateRouter();
        foreach ($dataset->routes as $route) {
            $native = $router->addRoute($route->methods, $route->path, [CacheBenchmarkHandler::class, 'handle']);
            $native->name($route->id);
            if ($route->constraints !== []) {
                $native->where($route->constraints);
            }
        }
        $routes = $router->getRoutes();
        if (!$routes instanceof IlluminateRouteCollection) {
            throw new \LogicException('Illuminate did not expose a compilable route collection.');
        }
        self::writePhpArray($file, $routes->compile());
    }

    private static function generateSymfony(RouteDataset $dataset, string $file): void
    {
        $routes = new RouteCollection();
        foreach ($dataset->routes as $route) {
            $routes->add($route->id, new SymfonyRoute(
                $route->path,
                ['_handler' => [CacheBenchmarkHandler::class, 'handle']],
                $route->constraints,
                methods: $route->methods,
            ));
        }
        $dump = (new CompiledUrlMatcherDumper($routes))->dump();
        if (file_put_contents($file, $dump, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write Symfony cache artifact.');
        }
    }

    private static function generateFastRoute(RouteDataset $dataset, string $file): void
    {
        cachedDispatcher(
            static function (RouteCollector $collector) use ($dataset): void {
                foreach ($dataset->routes as $route) {
                    $collector->addRoute(
                        $route->methods,
                        self::fastRoutePath($route),
                        [CacheBenchmarkHandler::class, 'handle'],
                    );
                }
            },
            ['cacheFile' => $file, 'cacheDisabled' => false],
        );
    }

    private static function loadAriel(string $file, string $method, string $path): LoadedCacheRouter
    {
        $router = new ArielRouter();
        $payload = require $file;
        if (
            !\is_array($payload)
            || !isset($payload['version'], $payload['definitions'], $payload['tree'])
            || !\is_int($payload['version'])
            || !\is_array($payload['definitions'])
            || !\is_array($payload['tree'])
        ) {
            throw new \RuntimeException('Invalid Ariel Radix Router cache artifact.');
        }
        // The payload crosses a generated-PHP boundary; the router performs its own full format validation.
        (new \ReflectionMethod($router, 'appendCompiledDefinitions'))->invoke($router, 'benchmark', $payload);
        $router->compile();
        $request = new ArielRequest($method, $path);

        return new LoadedCacheRouter(static fn (): mixed => $router->dispatch($request));
    }

    private static function loadIlluminate(string $file, string $method, string $path): LoadedCacheRouter
    {
        [$router] = self::newIlluminateRouter();
        $compiled = require $file;
        if (
            !\is_array($compiled)
            || !isset($compiled['compiled'], $compiled['attributes'])
            || !\is_array($compiled['compiled'])
            || !\is_array($compiled['attributes'])
        ) {
            throw new \RuntimeException('Invalid Illuminate Routing cache artifact.');
        }
        $router->setCompiledRoutes([
            'compiled' => $compiled['compiled'],
            'attributes' => $compiled['attributes'],
        ]);
        $request = IlluminateRequest::create($path, strtoupper($method));

        return new LoadedCacheRouter(static fn (): mixed => $router->dispatch($request)->getContent());
    }

    private static function loadSymfony(string $file, string $method, string $path): LoadedCacheRouter
    {
        $compiled = require $file;
        if (!\is_array($compiled)) {
            throw new \RuntimeException('Invalid Symfony Routing cache artifact.');
        }
        $context = new RequestContext();
        $context->setMethod(strtoupper($method));
        $matcher = new CompiledUrlMatcher($compiled, $context);

        return new LoadedCacheRouter(static function () use ($matcher, $path): mixed {
            $parameters = $matcher->match($path);
            $handler = $parameters['_handler'];
            unset($parameters['_route'], $parameters['_handler']);

            return $handler(...array_values($parameters));
        });
    }

    private static function loadFastRoute(string $file, string $method, string $path): LoadedCacheRouter
    {
        $dispatcher = cachedDispatcher(
            static function (): never {
                throw new \LogicException('FastRoute cache unexpectedly missed.');
            },
            ['cacheFile' => $file, 'cacheDisabled' => false],
        );

        return new LoadedCacheRouter(static function () use ($dispatcher, $method, $path): mixed {
            $result = $dispatcher->dispatch(strtoupper($method), $path);
            $handler = $result[1];

            return $handler(...array_values($result[2]));
        });
    }

    /** @return array{IlluminateRouter, Container} */
    private static function newIlluminateRouter(): array
    {
        $container = new Container();
        $container->bind(CallableDispatcherContract::class, CallableDispatcher::class);
        $router = new IlluminateRouter(new NullEventDispatcher(), $container);

        return [$router, $container];
    }

    private static function fastRoutePath(GeneratedRoute $route): string
    {
        $path = preg_replace_callback(
            '#(/?)\{([A-Za-z_][A-Za-z0-9_]*)(\?)?\}#',
            static function (array $matches) use ($route): string {
                $constraint = $route->constraints[$matches[2]] ?? null;
                $parameter = '{' . $matches[2] . ($constraint === null ? '' : ':' . $constraint) . '}';

                return ($matches[3] ?? '') === '?'
                    ? '[' . $matches[1] . $parameter . ']'
                    : $matches[1] . $parameter;
            },
            $route->path,
        );

        return \is_string($path) ? $path : $route->path;
    }

    /** @param array<array-key, mixed> $data */
    private static function writePhpArray(string $file, array $data): void
    {
        $contents = '<?php return ' . var_export($data, true) . ';';
        if (file_put_contents($file, $contents, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write cache artifact.');
        }
    }
}
