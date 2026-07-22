<?php

declare(strict_types=1);

namespace RouterBenchmarks\Benchmarks;

use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Subject;
use RouterBenchmarks\Benchmarks\Support\BenchmarkParameters;
use RouterBenchmarks\Contract\Feature;
use RouterBenchmarks\Dataset\DatasetFactory;
use RouterBenchmarks\Support\AdapterRegistry;
use RouterBenchmarks\Support\BenchmarkHandler;

final class MemoryBench
{
    /** @return iterable<string, array{router: string, size: int}> */
    public function provideCases(): iterable
    {
        return BenchmarkParameters::combinations();
    }

    /**
     * Diagnostic only. Official memory reports use bin/memory-worker in isolated processes.
     *
     * @param array{router: string, size: int} $params
     * @return array{empty: int, registered: int, finalized: int|null, peak: int}
     */
    #[Subject, ParamProviders('provideCases'), Revs(1), Iterations(3)]
    public function benchMemoryPhases(array $params): array
    {
        $dataset = DatasetFactory::create($params['size']);
        $handlers = [];
        foreach ($dataset->routes as $route) {
            $handlers[$route->id] = BenchmarkHandler::forRoute($route->id);
        }
        gc_collect_cycles();
        memory_reset_peak_usage();
        $baseline = memory_get_usage(false);
        $adapter = AdapterRegistry::byName($params['router']);
        $empty = memory_get_usage(false) - $baseline;
        foreach ($dataset->routes as $route) {
            $adapter->addRoute($route, $handlers[$route->id]);
        }
        $registered = memory_get_usage(false) - $baseline;
        $finalized = null;
        if ($adapter->supports(Feature::Compilation) || $adapter->supports(Feature::Finalization)) {
            $adapter->prepareRequest('GET', '/');
            $adapter->finalize();
            $finalized = memory_get_usage(false) - $baseline;
        }

        return [
            'empty' => $empty,
            'registered' => $registered,
            'finalized' => $finalized,
            'peak' => memory_get_peak_usage(false) - $baseline,
        ];
    }
}
