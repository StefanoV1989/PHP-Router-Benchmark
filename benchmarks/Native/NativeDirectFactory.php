<?php

declare(strict_types=1);

namespace RouterBenchmarks\Benchmarks\Native;

use FastRoute\RouteCollector;

use function FastRoute\simpleDispatcher;

use Illuminate\Container\Container;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Routing\CallableDispatcher;
use Illuminate\Routing\Contracts\CallableDispatcher as CallableDispatcherContract;
use Illuminate\Routing\RouteCollection as IlluminateRouteCollection;
use Illuminate\Routing\Router as IlluminateRouter;
use Pecee\Http\Url;
use Pecee\SimpleRouter\Route\RouteUrl;
use Pecee\SimpleRouter\Router as PeceeRouter;
use RouterBenchmarks\Dataset\GeneratedRoute;
use RouterBenchmarks\Dataset\RouteDataset;
use RouterBenchmarks\Support\NullEventDispatcher;
use StefanoV1989\ArielRouter\ArielRouter;
use StefanoV1989\ArielRouter\Http\Request as ArielRequest;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route as SymfonyRoute;
use Symfony\Component\Routing\RouteCollection;

final class NativeDirectFactory
{
    public static function fullDispatch(string $routerName, RouteDataset $dataset, GeneratedRoute $target): \Closure
    {
        return match ($routerName) {
            'Ariel Radix Router' => self::ariel($dataset, $target, true),
            'Illuminate Routing' => self::illuminate($dataset, $target, true),
            'Bramus Router' => self::bramus($dataset, $target),
            'AltoRouter' => self::alto($dataset, $target, true),
            'Symfony Routing' => self::symfony($dataset, $target, true),
            'FastRoute' => self::fastRoute($dataset, $target, true),
            'Simple PHP Router' => self::pecee($dataset, $target),
            default => throw new \InvalidArgumentException('Unknown router.'),
        };
    }

    public static function match(string $routerName, RouteDataset $dataset, GeneratedRoute $target): \Closure
    {
        return match ($routerName) {
            'Ariel Radix Router' => self::ariel($dataset, $target, false),
            'Illuminate Routing' => self::illuminate($dataset, $target, false),
            'AltoRouter' => self::alto($dataset, $target, false),
            'Symfony Routing' => self::symfony($dataset, $target, false),
            'FastRoute' => self::fastRoute($dataset, $target, false),
            default => throw new \InvalidArgumentException('Router has no handler-free native match.'),
        };
    }

    private static function ariel(RouteDataset $dataset, GeneratedRoute $target, bool $dispatch): \Closure
    {
        $router = new ArielRouter();
        foreach ($dataset->routes as $route) {
            $native = $router->add($route->methods, $route->path, self::handler());
            if ($route->constraints !== []) {
                $native->where($route->constraints);
            }
        }
        $router->compile();
        if (!$dispatch) {
            return static fn (): mixed => $router->engine()->resolve($target->methods[0], $target->samplePath);
        }
        $request = new ArielRequest($target->methods[0], $target->samplePath);

        return static fn (): mixed => $router->dispatch($request);
    }

    private static function illuminate(RouteDataset $dataset, GeneratedRoute $target, bool $dispatch): \Closure
    {
        $container = new Container();
        $container->bind(CallableDispatcherContract::class, CallableDispatcher::class);
        $router = new IlluminateRouter(new NullEventDispatcher(), $container);
        foreach ($dataset->routes as $route) {
            $native = $router->addRoute($route->methods, $route->path, self::handler());
            if ($route->constraints !== []) {
                $native->where($route->constraints);
            }
        }
        $routes = $router->getRoutes();
        if (!$routes instanceof IlluminateRouteCollection) {
            throw new \LogicException('Illuminate did not expose a compilable route collection.');
        }
        $router->setCompiledRoutes($routes->compile());
        $request = IlluminateRequest::create($target->samplePath, $target->methods[0]);

        return $dispatch
            ? static fn (): mixed => $router->dispatch($request)
            : static fn (): mixed => $router->getRoutes()->match($request);
    }

    private static function bramus(RouteDataset $dataset, GeneratedRoute $target): \Closure
    {
        $_SERVER['REQUEST_METHOD'] = $target->methods[0];
        $_SERVER['REQUEST_URI'] = $target->samplePath;
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $router = new \Bramus\Router\Router();
        $router->setBasePath('/');
        $last = null;
        foreach ($dataset->routes as $route) {
            $router->match(implode('|', $route->methods), self::bramusPath($route), function (mixed ...$params) use (&$last): void {
                $last = 'native|' . implode(',', $params);
            });
        }

        return static function () use ($router, &$last): mixed {
            $router->run();

            return $last;
        };
    }

    private static function alto(RouteDataset $dataset, GeneratedRoute $target, bool $dispatch): \Closure
    {
        $router = new \AltoRouter();
        foreach ($dataset->routes as $route) {
            $path = self::altoPath($router, $route);
            $router->map(implode('|', $route->methods), $path, self::handler(), $route->id);
        }
        if (!$dispatch) {
            return static fn (): mixed => $router->match($target->samplePath, $target->methods[0]);
        }

        return static function () use ($router, $target): mixed {
            $match = $router->match($target->samplePath, $target->methods[0]);
            if (!\is_array($match)) {
                throw new \LogicException('Prepared AltoRouter target did not match.');
            }

            return $match['target'](...array_values($match['params']));
        };
    }

    private static function symfony(RouteDataset $dataset, GeneratedRoute $target, bool $dispatch): \Closure
    {
        $routes = new RouteCollection();
        foreach ($dataset->routes as $route) {
            $routes->add($route->id, new SymfonyRoute(
                $route->path,
                ['_handler' => self::handler()],
                $route->constraints,
                methods: $route->methods,
            ));
        }
        $context = new RequestContext();
        $context->setMethod($target->methods[0]);
        $matcher = new CompiledUrlMatcher((new CompiledUrlMatcherDumper($routes))->getCompiledRoutes(), $context);
        if (!$dispatch) {
            return static fn (): mixed => $matcher->match($target->samplePath);
        }

        return static function () use ($matcher, $target): mixed {
            $parameters = $matcher->match($target->samplePath);
            $handler = $parameters['_handler'];
            unset($parameters['_route'], $parameters['_handler']);

            return $handler(...array_values($parameters));
        };
    }

    private static function fastRoute(RouteDataset $dataset, GeneratedRoute $target, bool $dispatch): \Closure
    {
        $dispatcher = simpleDispatcher(static function (RouteCollector $collector) use ($dataset): void {
            foreach ($dataset->routes as $route) {
                $collector->addRoute($route->methods, self::fastRoutePath($route), self::handler());
            }
        });
        if (!$dispatch) {
            return static fn (): mixed => $dispatcher->dispatch($target->methods[0], $target->samplePath);
        }

        return static function () use ($dispatcher, $target): mixed {
            $result = $dispatcher->dispatch($target->methods[0], $target->samplePath);

            return $result[1](...array_values($result[2]));
        };
    }

    private static function pecee(RouteDataset $dataset, GeneratedRoute $target): \Closure
    {
        $_SERVER['REQUEST_METHOD'] = $target->methods[0];
        $_SERVER['REQUEST_URI'] = $target->samplePath;
        $_SERVER['HTTP_HOST'] = 'localhost';
        $router = new PeceeRouter();
        foreach ($dataset->routes as $route) {
            $native = new RouteUrl($route->path, self::handler());
            $native->setRequestMethods(array_map(strtolower(...), $route->methods));
            if ($route->constraints !== []) {
                $native->where($route->constraints);
            }
            $router->addRoute($native);
        }
        $router->getRequest()->setMethod(strtolower($target->methods[0]));
        $router->getRequest()->setUrl(new Url($target->samplePath));
        $router->loadRoutes();

        return static fn (): mixed => $router->routeRequest();
    }

    private static function handler(): \Closure
    {
        return static fn (mixed ...$parameters): string => 'native|' . implode(',', $parameters);
    }

    private static function bramusPath(GeneratedRoute $route): string
    {
        return preg_replace_callback(
            '#(/?)\{([A-Za-z_][A-Za-z0-9_]*)(\?)?\}#',
            static function (array $matches) use ($route): string {
                $capture = '(' . ($route->constraints[$matches[2]] ?? '[^/]+') . ')';

                return ($matches[3] ?? '') === '?' ? '(?:' . $matches[1] . $capture . ')?' : $matches[1] . $capture;
            },
            $route->path,
        ) ?? $route->path;
    }

    private static function altoPath(\AltoRouter $router, GeneratedRoute $route): string
    {
        $path = $route->path;
        foreach ($route->parameterNames() as $name) {
            $constraint = $route->constraints[$name] ?? null;
            if ($constraint === null) {
                $replacement = '[:' . $name . ']';
            } else {
                $type = 'native_' . substr(hash('sha256', $constraint), 0, 10);
                $router->addMatchTypes([$type => $constraint]);
                $replacement = '[' . $type . ':' . $name . ']';
            }
            $path = str_replace('{' . $name . '}', $replacement, $path);
        }

        return $path;
    }

    private static function fastRoutePath(GeneratedRoute $route): string
    {
        return preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*)\}/',
            static fn (array $matches): string => '{' . $matches[1]
                . (isset($route->constraints[$matches[1]]) ? ':' . $route->constraints[$matches[1]] : '') . '}',
            $route->path,
        ) ?? $route->path;
    }
}
