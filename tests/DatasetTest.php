<?php

declare(strict_types=1);

namespace RouterBenchmarks\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RouterBenchmarks\Dataset\DatasetFactory;
use RouterBenchmarks\Dataset\RouteKind;

final class DatasetTest extends TestCase
{
    /** @return iterable<string, array{int}> */
    public static function sizes(): iterable
    {
        yield '10 routes' => [10];
        yield '100 routes' => [100];
        yield '1,000 routes' => [1_000];
        yield '10,000 routes' => [10_000];
    }

    #[DataProvider('sizes')]
    public function testDatasetIsDeterministicUniqueAndCorrectlySized(int $size): void
    {
        $first = DatasetFactory::create($size);
        $second = DatasetFactory::create($size);

        self::assertSame(DatasetFactory::DEFAULT_SEED, $first->seed);
        self::assertCount($size, $first->routes);
        self::assertEquals($first, $second);
        self::assertCount($size, array_unique(array_map(
            static fn ($route): string => implode('|', $route->methods) . ':' . $route->path,
            $first->routes,
        )));
    }

    public function testMixedDatasetDistribution(): void
    {
        $dataset = DatasetFactory::create(100);
        $counts = array_count_values(array_map(
            static fn ($route): string => $route->kind->value,
            $dataset->routes,
        ));

        self::assertSame(50, $counts[RouteKind::StaticRoute->value]);
        self::assertSame(30, $counts[RouteKind::SingleParameter->value]);
        self::assertSame(10, $counts[RouteKind::MultipleParameters->value]);
        self::assertSame(10, $counts[RouteKind::Constrained->value]);
    }
}
