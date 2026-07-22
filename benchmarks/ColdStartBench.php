<?php

declare(strict_types=1);

namespace RouterBenchmarks\Benchmarks;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Subject;
use RouterBenchmarks\Benchmarks\Support\BenchmarkParameters;
use RouterBenchmarks\Dataset\DatasetFactory;
use RouterBenchmarks\Dataset\RouteDataset;
use RouterBenchmarks\Dataset\RouteKind;
use RouterBenchmarks\Result\DispatchResult;
use RouterBenchmarks\Support\AdapterRegistry;
use RouterBenchmarks\Support\BenchmarkHandler;

final class ColdStartBench
{
    private RouteDataset $dataset;

    /** @var array<string, \Closure> */
    private array $handlers;

    /** @return iterable<string, array{router: string, size: int}> */
    public function provideCases(): iterable
    {
        return BenchmarkParameters::combinations();
    }

    /** @param array{router: string, size: int} $params */
    public function setUp(array $params): void
    {
        $this->dataset = DatasetFactory::create($params['size']);
        $this->handlers = [];
        foreach ($this->dataset->routes as $route) {
            $this->handlers[$route->id] = BenchmarkHandler::forRoute($route->id);
        }
    }

    /** @param array{router: string, size: int} $params */
    #[Subject, BeforeMethods('setUp'), ParamProviders('provideCases'), Revs(1), Iterations(9)]
    public function benchColdStart(array $params): DispatchResult
    {
        $adapter = AdapterRegistry::byName($params['router']);
        foreach ($this->dataset->routes as $route) {
            $adapter->addRoute($route, $this->handlers[$route->id]);
        }
        $target = $this->dataset->first(RouteKind::MultipleParameters);
        $request = $adapter->prepareRequest($target->methods[0], $target->samplePath);
        $adapter->finalize();

        return $adapter->dispatch($request);
    }
}
