<?php

declare(strict_types=1);

namespace RouterBenchmarks\Tests;

use PHPUnit\Framework\TestCase;
use RouterBenchmarks\Dataset\DatasetFactory;
use RouterBenchmarks\Dataset\RouteKind;
use RouterBenchmarks\Support\NativeCacheLifecycle;

final class CacheLifecycleTest extends TestCase
{
    public function testNativeCompiledCachesLoadAndDispatch(): void
    {
        $dataset = DatasetFactory::create(100);
        $target = $dataset->first(RouteKind::MultipleParameters);
        foreach (NativeCacheLifecycle::routers() as $router) {
            $file = sys_get_temp_dir() . '/router-cache-test-' . hash('sha256', $router . getmypid()) . '.php';
            try {
                NativeCacheLifecycle::generate($router, $dataset, $file);
                self::assertFileExists($file, $router);
                self::assertGreaterThan(0, filesize($file), $router);
                $loaded = NativeCacheLifecycle::load($router, $file, $target->methods[0], $target->samplePath);
                self::assertSame(
                    'cache|' . implode(',', $target->expectedParameters),
                    $loaded->dispatch(),
                    $router,
                );
            } finally {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
