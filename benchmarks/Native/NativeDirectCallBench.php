<?php

declare(strict_types=1);

namespace RouterBenchmarks\Benchmarks\Native;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Subject;
use PhpBench\Attributes\Warmup;
use RouterBenchmarks\Benchmarks\Support\BenchmarkParameters;
use RouterBenchmarks\Contract\Feature;
use RouterBenchmarks\Dataset\DatasetFactory;
use RouterBenchmarks\Dataset\RouteKind;

final class NativeDirectCallBench
{
    private \Closure $operation;

    /** @return iterable<string, array{router: string, size: int}> */
    public function provideDispatchCases(): iterable
    {
        return BenchmarkParameters::combinations();
    }

    /** @return iterable<string, array{router: string, size: int}> */
    public function provideMatchCases(): iterable
    {
        return BenchmarkParameters::combinations(Feature::MatchWithoutDispatch);
    }

    /** @param array{router: string, size: int} $params */
    public function setUpDispatch(array $params): void
    {
        $dataset = DatasetFactory::create($params['size']);
        $this->operation = NativeDirectFactory::fullDispatch(
            $params['router'],
            $dataset,
            $dataset->first(RouteKind::MultipleParameters),
        );
    }

    /** @param array{router: string, size: int} $params */
    public function setUpMatch(array $params): void
    {
        $dataset = DatasetFactory::create($params['size']);
        $this->operation = NativeDirectFactory::match(
            $params['router'],
            $dataset,
            $dataset->first(RouteKind::MultipleParameters),
        );
    }

    #[Subject, BeforeMethods('setUpDispatch'), ParamProviders('provideDispatchCases'), Revs(500), Iterations(9), Warmup(2)]
    public function benchNativeFullDispatch(): mixed
    {
        return ($this->operation)();
    }

    #[Subject, BeforeMethods('setUpMatch'), ParamProviders('provideMatchCases'), Revs(1_000), Iterations(9), Warmup(2)]
    public function benchNativeMatch(): mixed
    {
        return ($this->operation)();
    }
}
