<?php

declare(strict_types=1);

namespace RouterBenchmarks\Dataset;

final class DatasetFactory
{
    public const DEFAULT_SEED = 19890717;

    /** @var list<string> */
    private const GROUPS = [
        'users',
        'products',
        'admin/reports',
        'api/v1/organizations',
        'projects',
        'teams',
        'articles',
        'accounts',
    ];

    public static function create(int $size, int $seed = self::DEFAULT_SEED): RouteDataset
    {
        if ($size < 1) {
            throw new \InvalidArgumentException('Dataset size must be positive.');
        }

        $staticCount = (int) floor($size * 0.5);
        $singleCount = (int) floor($size * 0.3);
        $multipleCount = (int) floor($size * 0.1);
        $constraintCount = $size - $staticCount - $singleCount - $multipleCount;
        $routes = [];

        self::appendStatic($routes, $staticCount, $seed);
        self::appendSingle($routes, $singleCount, $seed);
        self::appendMultiple($routes, $multipleCount, $seed);
        self::appendConstrained($routes, $constraintCount, $seed);

        return new RouteDataset($seed, $routes);
    }

    /** @param list<GeneratedRoute> $routes */
    private static function appendStatic(array &$routes, int $count, int $seed): void
    {
        $realistic = ['/users', '/users/new', '/products', '/admin/reports/current'];
        for ($i = 0; $i < $count; ++$i) {
            $path = $realistic[$i] ?? \sprintf('/%s/static-%d-%d', self::group($i), $seed, $i);
            $routes[] = new GeneratedRoute(
                \sprintf('static-%05d', $i),
                self::methods($i),
                $path,
                $path,
                RouteKind::StaticRoute,
            );
        }
    }

    /** @param list<GeneratedRoute> $routes */
    private static function appendSingle(array &$routes, int $count, int $seed): void
    {
        $realistic = [
            ['/users/{id}', '/users/42', ['id' => '42']],
            ['/users/{id}/profile', '/users/42/profile', ['id' => '42']],
            ['/products/{slug}', '/products/blue-widget', ['slug' => 'blue-widget']],
        ];
        for ($i = 0; $i < $count; ++$i) {
            $value = \sprintf('slug%d%d', $seed % 1000, $i);
            $prefix = \sprintf('/%s/item-%d', self::group($i + 1), $i);
            [$path, $samplePath, $parameters] = $realistic[$i]
                ?? [$prefix . '/{id}', $prefix . '/' . $value, ['id' => $value]];
            $routes[] = new GeneratedRoute(
                \sprintf('dynamic-one-%05d', $i),
                self::methods($i + 3),
                $path,
                $samplePath,
                RouteKind::SingleParameter,
                expectedParameters: $parameters,
            );
        }
    }

    /** @param list<GeneratedRoute> $routes */
    private static function appendMultiple(array &$routes, int $count, int $seed): void
    {
        $realistic = [
            ['/users/{id}/posts/{postId}', '/users/42/posts/91', ['id' => '42', 'postId' => '91']],
            [
                '/admin/reports/{year}/{month}',
                '/admin/reports/2026/07',
                ['year' => '2026', 'month' => '07'],
            ],
            [
                '/api/v1/organizations/{organizationId}/members/{memberId}',
                '/api/v1/organizations/17/members/29',
                ['organizationId' => '17', 'memberId' => '29'],
            ],
        ];
        for ($i = 0; $i < $count; ++$i) {
            $first = (string) (($seed + $i) % 90000 + 10000);
            $second = (string) (($seed + 17 + $i) % 90000 + 10000);
            $prefix = \sprintf('/%s/parent-%d', self::group($i + 2), $i);
            [$path, $samplePath, $parameters] = $realistic[$i]
                ?? [
                    $prefix . '/{id}/children/{childId}',
                    $prefix . '/' . $first . '/children/' . $second,
                    ['id' => $first, 'childId' => $second],
                ];
            $routes[] = new GeneratedRoute(
                \sprintf('dynamic-many-%05d', $i),
                self::methods($i + 5),
                $path,
                $samplePath,
                RouteKind::MultipleParameters,
                expectedParameters: $parameters,
            );
        }
    }

    /** @param list<GeneratedRoute> $routes */
    private static function appendConstrained(array &$routes, int $count, int $seed): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $numeric = $i % 2 === 0;
            $value = $numeric ? (string) (($seed + $i) % 900000 + 100000) : \sprintf('alpha%d', $i);
            $constraint = $numeric ? '\\d+' : '[A-Za-z]+';
            $prefix = \sprintf('/%s/constrained-%d', self::group($i + 3), $i);
            $routes[] = new GeneratedRoute(
                \sprintf('constrained-%05d', $i),
                self::methods($i + 7),
                $prefix . '/{value}',
                $prefix . '/' . $value,
                RouteKind::Constrained,
                ['value' => $constraint],
                ['value' => $value],
            );
        }
    }

    private static function group(int $index): string
    {
        return self::GROUPS[$index % \count(self::GROUPS)];
    }

    /** @return list<string> */
    private static function methods(int $index): array
    {
        return $index % 11 === 0 ? ['POST'] : ['GET'];
    }
}
