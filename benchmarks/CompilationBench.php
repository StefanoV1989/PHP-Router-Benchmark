<?php

declare(strict_types=1);

namespace RouterBenchmarks\Benchmarks;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Subject;
use PhpBench\Attributes\Warmup;
use RouterBenchmarks\Benchmarks\Support\AdapterBenchCase;
use RouterBenchmarks\Benchmarks\Support\BenchmarkParameters;
use RouterBenchmarks\Contract\Feature;
use RouterBenchmarks\Result\FinalizationResult;

final class CompilationBench extends AdapterBenchCase
{
    /** @return iterable<string, array{router: string, size: int}> */
    public function provideCases(): iterable
    {
        return BenchmarkParameters::combinations(Feature::Compilation);
    }

    /** @param array{router: string, size: int} $params */
    public function setUp(array $params): void
    {
        $this->createAdapter($params);
        $this->registerDataset();
    }

    #[Subject, BeforeMethods('setUp'), ParamProviders('provideCases'), Revs(1), Iterations(9), Warmup(0)]
    public function benchCompileRouteTable(): FinalizationResult
    {
        return $this->adapter->finalize();
    }
}
