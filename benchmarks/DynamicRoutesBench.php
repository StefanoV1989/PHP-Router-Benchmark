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
use RouterBenchmarks\Dataset\RouteKind;
use RouterBenchmarks\Result\MatchResult;

final class DynamicRoutesBench extends AdapterBenchCase
{
    /** @return iterable<string, array{router: string, size: int, kind: string}> */
    public function provideCases(): iterable
    {
        foreach (BenchmarkParameters::combinations(Feature::MatchWithoutDispatch) as $key => $params) {
            yield $key . '-one' => [...$params, 'kind' => RouteKind::SingleParameter->value];
            yield $key . '-many' => [...$params, 'kind' => RouteKind::MultipleParameters->value];
        }
    }

    /** @param array{router: string, size: int, kind: string} $params */
    public function setUp(array $params): void
    {
        $this->createAdapter($params);
        $this->registerDataset();
        $this->prepare($this->dataset->first(RouteKind::from($params['kind'])));
        $this->adapter->finalize();
    }

    #[Subject, BeforeMethods('setUp'), ParamProviders('provideCases'), Revs(1_000), Iterations(9), Warmup(2)]
    public function benchWarmDynamicMatch(): MatchResult
    {
        return $this->adapter->match($this->request);
    }
}
