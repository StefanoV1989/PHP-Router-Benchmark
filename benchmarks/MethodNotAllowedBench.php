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

final class MethodNotAllowedBench extends AdapterBenchCase
{
    /** @return iterable<string, array{router: string, size: int}> */
    public function provideCases(): iterable
    {
        return BenchmarkParameters::combinations(Feature::MethodNotAllowed);
    }

    /** @param array{router: string, size: int} $params */
    public function setUp(array $params): void
    {
        $this->createAdapter($params);
        $this->registerDataset();
        $route = $this->dataset->staticAt(0.5);
        $wrongMethod = $route->methods[0] === 'GET' ? 'PUT' : 'GET';
        $this->request = $this->adapter->prepareRequest($wrongMethod, $route->samplePath);
        $this->adapter->finalize();
    }

    #[Subject, BeforeMethods('setUp'), ParamProviders('provideCases'), Revs(250), Iterations(9), Warmup(2)]
    public function benchWarmMethodNotAllowed(): MatchResult
    {
        return $this->adapter->match($this->request);
    }
}
