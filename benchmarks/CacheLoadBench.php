<?php

declare(strict_types=1);

namespace RouterBenchmarks\Benchmarks;

use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Subject;
use PhpBench\Attributes\Warmup;
use RouterBenchmarks\Benchmarks\Support\BenchmarkParameters;
use RouterBenchmarks\Dataset\DatasetFactory;
use RouterBenchmarks\Dataset\RouteKind;
use RouterBenchmarks\Support\LoadedCacheRouter;
use RouterBenchmarks\Support\NativeCacheLifecycle;

final class CacheLoadBench
{
    private string $cacheFile;
    private string $router;
    private string $method;
    private string $path;
    private LoadedCacheRouter $loaded;

    /** @return iterable<string, array{router: string, size: int}> */
    public function provideCases(): iterable
    {
        foreach (BenchmarkParameters::sizes() as $size) {
            foreach (NativeCacheLifecycle::routers() as $router) {
                yield $router . '-' . $size => ['router' => $router, 'size' => $size];
            }
        }
    }

    /** @param array{router: string, size: int} $params */
    public function setUpArtifact(array $params): void
    {
        $directory = sys_get_temp_dir() . '/router-bench-cache';
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create benchmark cache directory.');
        }
        $this->router = $params['router'];
        $this->cacheFile = $directory . '/' . hash('sha256', $this->router . '-' . $params['size'] . '-' . getmypid()) . '.php';
        $dataset = DatasetFactory::create($params['size']);
        $target = $dataset->first(RouteKind::MultipleParameters);
        $this->method = $target->methods[0];
        $this->path = $target->samplePath;
        NativeCacheLifecycle::generate($this->router, $dataset, $this->cacheFile);
    }

    /** @param array{router: string, size: int} $params */
    public function setUpLoaded(array $params): void
    {
        $this->setUpArtifact($params);
        $this->loaded = NativeCacheLifecycle::load($this->router, $this->cacheFile, $this->method, $this->path);
        $this->loaded->dispatch();
    }

    public function cleanUp(): void
    {
        if (isset($this->cacheFile) && is_file($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    #[Subject, BeforeMethods('setUpArtifact'), AfterMethods('cleanUp'), ParamProviders('provideCases'), Revs(1), Iterations(7)]
    public function benchLoadCacheAndFirstDispatch(): mixed
    {
        return NativeCacheLifecycle::load($this->router, $this->cacheFile, $this->method, $this->path)->dispatch();
    }

    #[Subject, BeforeMethods('setUpLoaded'), AfterMethods('cleanUp'), ParamProviders('provideCases'), Revs(500), Iterations(9), Warmup(2)]
    public function benchSubsequentCachedDispatch(): mixed
    {
        return $this->loaded->dispatch();
    }
}
