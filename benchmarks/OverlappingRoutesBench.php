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
use RouterBenchmarks\Result\MatchResult;

final class OverlappingRoutesBench extends AdapterBenchCase
{
    /** @return iterable<string, array{router: string, size: int}> */
    public function provideCases(): iterable
    {
        return BenchmarkParameters::combinations(Feature::MatchWithoutDispatch);
    }

    /** @param array{router: string, size: int} $params */
    public function setUp(array $params): void
    {
        $this->createAdapter($params);
        $this->registerDataset();
        $this->request = $this->adapter->prepareRequest('GET', '/users/new');
        $this->adapter->finalize();
    }

    #[Subject, BeforeMethods('setUp'), ParamProviders('provideCases'), Revs(1_000), Iterations(9), Warmup(2)]
    public function benchOverlappingStaticRoute(): MatchResult
    {
        return $this->adapter->match($this->request);
    }
}
