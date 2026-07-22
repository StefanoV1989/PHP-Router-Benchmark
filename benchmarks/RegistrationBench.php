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
use RouterBenchmarks\Support\BenchmarkHandler;

final class RegistrationBench extends AdapterBenchCase
{
    /** @var array<string, \Closure> */
    private array $handlers = [];

    /** @return iterable<string, array{router: string, size: int}> */
    public function provideCases(): iterable
    {
        return BenchmarkParameters::combinations();
    }

    /** @param array{router: string, size: int} $params */
    public function setUp(array $params): void
    {
        $this->createAdapter($params);
        $this->handlers = [];
        foreach ($this->dataset->routes as $route) {
            $this->handlers[$route->id] = BenchmarkHandler::forRoute($route->id);
        }
    }

    #[Subject, BeforeMethods('setUp'), ParamProviders('provideCases'), Revs(1), Iterations(9), Warmup(0)]
    public function benchRegisterAllRoutes(): void
    {
        foreach ($this->dataset->routes as $route) {
            $this->adapter->addRoute($route, $this->handlers[$route->id]);
        }
    }
}
