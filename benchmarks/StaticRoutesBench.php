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

final class StaticRoutesBench extends AdapterBenchCase
{
    /** @return iterable<string, array{router: string, size: int, position: float}> */
    public function provideCases(): iterable
    {
        foreach (BenchmarkParameters::combinations(Feature::MatchWithoutDispatch) as $key => $params) {
            yield $key . '-early' => [...$params, 'position' => 0.0];
            yield $key . '-middle' => [...$params, 'position' => 0.5];
            yield $key . '-late' => [...$params, 'position' => 1.0];
        }
    }

    /** @param array{router: string, size: int, position: float} $params */
    public function setUp(array $params): void
    {
        $this->createAdapter($params);
        $this->registerDataset();
        $route = $this->dataset->staticAt($params['position']);
        $this->prepare($route);
        $this->adapter->finalize();
    }

    #[Subject, BeforeMethods('setUp'), ParamProviders('provideCases'), Revs(1_000), Iterations(9), Warmup(2)]
    public function benchWarmStaticMatch(): MatchResult
    {
        return $this->adapter->match($this->request);
    }
}
