<?php

declare(strict_types=1);

namespace RouterBenchmarks\Tests;

use PHPUnit\Framework\TestCase;
use RouterBenchmarks\Contract\Feature;
use RouterBenchmarks\Contract\UnsupportedFeature;
use RouterBenchmarks\Dataset\DatasetFactory;
use RouterBenchmarks\Support\AdapterRegistry;
use RouterBenchmarks\Support\BenchmarkHandler;

final class AdapterContractTest extends TestCase
{
    public function testIdentitiesAreUniqueAndPinned(): void
    {
        $identities = array_map(static fn ($adapter) => $adapter->identity(), AdapterRegistry::all());

        self::assertCount(7, $identities);
        self::assertCount(7, array_unique(array_map(static fn ($identity) => $identity->name, $identities)));
        self::assertCount(7, array_unique(array_map(static fn ($identity) => $identity->package, $identities)));
        foreach ($identities as $identity) {
            self::assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $identity->commit);
            self::assertNotSame('', $identity->version);
        }
    }

    public function testUnsupportedMatchDoesNotSilentlyDispatch(): void
    {
        foreach (AdapterRegistry::all() as $adapter) {
            if ($adapter->supports(Feature::MatchWithoutDispatch)) {
                continue;
            }

            try {
                $adapter->match($adapter->prepareRequest('GET', '/missing'));
                self::fail($adapter->identity()->name . ' silently implemented unsupported matching.');
            } catch (UnsupportedFeature) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testEveryAdapterRegistersTheIdenticalRouteCount(): void
    {
        $dataset = DatasetFactory::create(100);
        foreach (AdapterRegistry::all() as $adapter) {
            foreach ($dataset->routes as $route) {
                $adapter->addRoute($route, BenchmarkHandler::forRoute($route->id));
            }
            self::assertSame(100, $adapter->registeredRouteCount(), $adapter->identity()->name);
        }
    }

    public function testApprovedCapabilityExceptionsAreExplicit(): void
    {
        $capabilities = [];
        foreach (AdapterRegistry::all() as $adapter) {
            $capabilities[$adapter->identity()->name] = [
                'match' => $adapter->supports(Feature::MatchWithoutDispatch),
                'native_dispatch' => $adapter->supports(Feature::NativeHandlerDispatch),
                'compile' => $adapter->supports(Feature::Compilation),
                'finalize' => $adapter->supports(Feature::Finalization),
                '405' => $adapter->supports(Feature::MethodNotAllowed),
            ];
        }

        self::assertFalse($capabilities['Bramus Router']['match']);
        self::assertFalse($capabilities['Simple PHP Router']['match']);
        self::assertFalse($capabilities['AltoRouter']['native_dispatch']);
        self::assertFalse($capabilities['Symfony Routing']['native_dispatch']);
        self::assertFalse($capabilities['FastRoute']['native_dispatch']);
        self::assertFalse($capabilities['Bramus Router']['compile']);
        self::assertFalse($capabilities['AltoRouter']['compile']);
        self::assertTrue($capabilities['Simple PHP Router']['finalize']);
        self::assertFalse($capabilities['Simple PHP Router']['compile']);
        self::assertFalse($capabilities['Bramus Router']['405']);
        self::assertFalse($capabilities['AltoRouter']['405']);
        self::assertFalse($capabilities['Simple PHP Router']['405']);
    }
}
