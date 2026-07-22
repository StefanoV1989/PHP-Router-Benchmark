<?php

declare(strict_types=1);

namespace RouterBenchmarks\Tests;

use PHPUnit\Framework\TestCase;
use RouterBenchmarks\Contract\Feature;
use RouterBenchmarks\Contract\RouterAdapterInterface;
use RouterBenchmarks\Dataset\GeneratedRoute;
use RouterBenchmarks\Dataset\RouteKind;
use RouterBenchmarks\Result\MatchStatus;
use RouterBenchmarks\Support\AdapterRegistry;
use RouterBenchmarks\Support\BenchmarkHandler;

final class EquivalentBehaviorTest extends TestCase
{
    public function testAllAdaptersDispatchEquivalentStaticAndDynamicRoutes(): void
    {
        foreach (AdapterRegistry::all() as $adapter) {
            self::registerFixture($adapter);

            self::assertDispatch($adapter, 'GET', '/users', 'users-index|');
            self::assertDispatch($adapter, 'GET', '/users/new', 'users-new|');
            self::assertDispatch($adapter, 'GET', '/users/42', 'users-show|42');
            self::assertDispatch($adapter, 'GET', '/users/42/posts/91', 'user-post|42,91');
            self::assertDispatch($adapter, 'GET', '/numeric/123', 'numeric|123');
            self::assertDispatch($adapter, 'GET', '/alphabetic/Alpha', 'alphabetic|Alpha');
            self::assertDispatch($adapter, 'GET', '/method', 'method-get|');
            self::assertDispatch($adapter, 'POST', '/method', 'method-post|');

            $miss = $adapter->dispatch($adapter->prepareRequest('GET', '/does-not-exist'));
            self::assertSame(MatchStatus::NotFound, $miss->status, $adapter->identity()->name);
        }
    }

    public function testHandlerFreeAdaptersExtractEquivalentParametersAndStatuses(): void
    {
        foreach (AdapterRegistry::all() as $adapter) {
            if (!$adapter->supports(Feature::MatchWithoutDispatch)) {
                continue;
            }
            self::registerFixture($adapter);

            $single = $adapter->match($adapter->prepareRequest('GET', '/users/42'));
            self::assertSame(MatchStatus::Found, $single->status, $adapter->identity()->name);
            self::assertSame('users-show', $single->routeId, $adapter->identity()->name);
            self::assertSame(['id' => '42'], $single->parameters, $adapter->identity()->name);

            $multiple = $adapter->match($adapter->prepareRequest('GET', '/users/42/posts/91'));
            self::assertSame(['id' => '42', 'postId' => '91'], $multiple->parameters, $adapter->identity()->name);

            self::assertSame(
                MatchStatus::NotFound,
                $adapter->match($adapter->prepareRequest('GET', '/numeric/not-a-number'))->status,
                $adapter->identity()->name,
            );
            self::assertSame(
                MatchStatus::NotFound,
                $adapter->match($adapter->prepareRequest('GET', '/alphabetic/123'))->status,
                $adapter->identity()->name,
            );
            self::assertSame(
                MatchStatus::NotFound,
                $adapter->match($adapter->prepareRequest('GET', '/missing'))->status,
                $adapter->identity()->name,
            );

            if ($adapter->supports(Feature::MethodNotAllowed)) {
                self::assertSame(
                    MatchStatus::MethodNotAllowed,
                    $adapter->match($adapter->prepareRequest('PUT', '/method'))->status,
                    $adapter->identity()->name,
                );
            }
        }
    }

    public function testUrlDecodingBehaviorIsRecorded(): void
    {
        foreach (AdapterRegistry::all() as $adapter) {
            $route = new GeneratedRoute(
                'encoded',
                ['GET'],
                '/encoded/{value}',
                '/encoded/hello%41',
                RouteKind::SingleParameter,
                expectedParameters: ['value' => 'helloA'],
            );
            $adapter->addRoute($route, BenchmarkHandler::forRoute($route->id));
            $adapter->prepareRequest('GET', $route->samplePath);
            $adapter->finalize();
            $result = $adapter->dispatch($adapter->prepareRequest('GET', $route->samplePath));
            $expected = $adapter->identity()->name === 'AltoRouter'
                ? 'encoded|hello%41'
                : 'encoded|helloA';

            self::assertSame($expected, $result->value, $adapter->identity()->name);
        }
    }

    public function testTrailingSlashBehaviorIsRecorded(): void
    {
        $tolerant = ['Ariel Radix Router', 'Illuminate Routing', 'Bramus Router', 'Simple PHP Router'];
        foreach (AdapterRegistry::all() as $adapter) {
            $route = new GeneratedRoute('slash', ['GET'], '/slash', '/slash', RouteKind::StaticRoute);
            $adapter->addRoute($route, BenchmarkHandler::forRoute($route->id));
            $adapter->prepareRequest('GET', '/slash/');
            $adapter->finalize();
            $result = $adapter->dispatch($adapter->prepareRequest('GET', '/slash/'));
            $expected = \in_array($adapter->identity()->name, $tolerant, true)
                ? MatchStatus::Found
                : MatchStatus::NotFound;

            self::assertSame($expected, $result->status, $adapter->identity()->name);
        }
    }

    public function testOptionalRouteBehaviorForSupportingAdapters(): void
    {
        foreach (AdapterRegistry::all() as $adapter) {
            if (!$adapter->supports(Feature::OptionalParameters)) {
                continue;
            }
            $route = new GeneratedRoute(
                'optional',
                ['GET'],
                '/optional/{value?}',
                '/optional/value',
                RouteKind::SingleParameter,
                expectedParameters: ['value' => 'value'],
            );
            $adapter->addRoute($route, BenchmarkHandler::forRoute($route->id));
            $adapter->prepareRequest('GET', '/optional');
            $adapter->finalize();

            self::assertSame(
                'optional|',
                $adapter->dispatch($adapter->prepareRequest('GET', '/optional'))->value,
                $adapter->identity()->name,
            );
            self::assertSame(
                'optional|value',
                $adapter->dispatch($adapter->prepareRequest('GET', '/optional/value'))->value,
                $adapter->identity()->name,
            );
        }
    }

    public function testDuplicateRouteBehaviorIsRecordedWithoutNormalization(): void
    {
        $expected = [
            'Illuminate Routing' => 'duplicate-second|',
            'Bramus Router' => 'duplicate-first|',
            'AltoRouter' => 'duplicate-first|',
            'Symfony Routing' => 'duplicate-first|',
            'Simple PHP Router' => 'duplicate-first|',
        ];

        foreach (AdapterRegistry::all() as $adapter) {
            $first = new GeneratedRoute('duplicate-first', ['GET'], '/duplicate', '/duplicate', RouteKind::StaticRoute);
            $second = new GeneratedRoute('duplicate-second', ['GET'], '/duplicate', '/duplicate', RouteKind::StaticRoute);
            $adapter->addRoute($first, BenchmarkHandler::forRoute($first->id));
            if ($adapter->identity()->name === 'FastRoute') {
                $thrown = false;
                try {
                    $adapter->addRoute($second, BenchmarkHandler::forRoute($second->id));
                } catch (\Throwable) {
                    $thrown = true;
                }
                self::assertTrue($thrown, 'FastRoute should reject duplicate routes during registration.');
                continue;
            }
            $adapter->addRoute($second, BenchmarkHandler::forRoute($second->id));
            $adapter->prepareRequest('GET', '/duplicate');

            if ($adapter->identity()->name === 'Ariel Radix Router') {
                $thrown = false;
                try {
                    $adapter->finalize();
                } catch (\Throwable) {
                    $thrown = true;
                }
                self::assertTrue($thrown, $adapter->identity()->name . ' should reject duplicate routes.');
                continue;
            }

            $adapter->finalize();
            self::assertSame(
                $expected[$adapter->identity()->name],
                $adapter->dispatch($adapter->prepareRequest('GET', '/duplicate'))->value,
                $adapter->identity()->name,
            );
        }
    }

    public function testStaticPriorityWhenDynamicRouteWasRegisteredFirstIsRecorded(): void
    {
        foreach (AdapterRegistry::all() as $adapter) {
            $dynamic = new GeneratedRoute(
                'priority-dynamic',
                ['GET'],
                '/priority/{id}',
                '/priority/value',
                RouteKind::SingleParameter,
                expectedParameters: ['id' => 'value'],
            );
            $static = new GeneratedRoute(
                'priority-static',
                ['GET'],
                '/priority/new',
                '/priority/new',
                RouteKind::StaticRoute,
            );
            $adapter->addRoute($dynamic, BenchmarkHandler::forRoute($dynamic->id));
            if ($adapter->identity()->name === 'FastRoute') {
                self::expectRegistrationFailure($adapter, $static);
                continue;
            }
            $adapter->addRoute($static, BenchmarkHandler::forRoute($static->id));
            $adapter->finalize();
            $expected = $adapter->identity()->name === 'Ariel Radix Router'
                ? 'priority-static|'
                : 'priority-dynamic|new';
            self::assertSame(
                $expected,
                $adapter->dispatch($adapter->prepareRequest('GET', '/priority/new'))->value,
                $adapter->identity()->name,
            );
        }
    }

    private static function registerFixture(RouterAdapterInterface $adapter): void
    {
        $routes = [
            new GeneratedRoute('users-index', ['GET'], '/users', '/users', RouteKind::StaticRoute),
            new GeneratedRoute('users-new', ['GET'], '/users/new', '/users/new', RouteKind::StaticRoute),
            new GeneratedRoute(
                'users-show',
                ['GET'],
                '/users/{id}',
                '/users/42',
                RouteKind::SingleParameter,
                expectedParameters: ['id' => '42'],
            ),
            new GeneratedRoute(
                'user-post',
                ['GET'],
                '/users/{id}/posts/{postId}',
                '/users/42/posts/91',
                RouteKind::MultipleParameters,
                expectedParameters: ['id' => '42', 'postId' => '91'],
            ),
            new GeneratedRoute(
                'numeric',
                ['GET'],
                '/numeric/{id}',
                '/numeric/123',
                RouteKind::Constrained,
                ['id' => '\\d+'],
                ['id' => '123'],
            ),
            new GeneratedRoute(
                'alphabetic',
                ['GET'],
                '/alphabetic/{name}',
                '/alphabetic/Alpha',
                RouteKind::Constrained,
                ['name' => '[A-Za-z]+'],
                ['name' => 'Alpha'],
            ),
            new GeneratedRoute('method-get', ['GET'], '/method', '/method', RouteKind::StaticRoute),
            new GeneratedRoute('method-post', ['POST'], '/method', '/method', RouteKind::StaticRoute),
        ];

        foreach ($routes as $route) {
            $adapter->addRoute($route, BenchmarkHandler::forRoute($route->id));
        }
        $adapter->prepareRequest('GET', '/');
        $adapter->finalize();
    }

    private static function assertDispatch(
        RouterAdapterInterface $adapter,
        string $method,
        string $path,
        string $expected,
    ): void {
        $result = $adapter->dispatch($adapter->prepareRequest($method, $path));
        self::assertSame(MatchStatus::Found, $result->status, $adapter->identity()->name);
        self::assertSame($expected, $result->value, $adapter->identity()->name);
    }

    private static function expectRegistrationFailure(
        RouterAdapterInterface $adapter,
        GeneratedRoute $route,
    ): void {
        $thrown = false;
        try {
            $adapter->addRoute($route, BenchmarkHandler::forRoute($route->id));
        } catch (\Throwable) {
            $thrown = true;
        }
        self::assertTrue($thrown, $adapter->identity()->name . ' should reject a shadowed static route.');
    }
}
