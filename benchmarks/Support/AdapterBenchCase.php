<?php

declare(strict_types=1);

namespace RouterBenchmarks\Benchmarks\Support;

use RouterBenchmarks\Contract\RouterAdapterInterface;
use RouterBenchmarks\Dataset\DatasetFactory;
use RouterBenchmarks\Dataset\GeneratedRoute;
use RouterBenchmarks\Dataset\RouteDataset;
use RouterBenchmarks\Support\AdapterRegistry;
use RouterBenchmarks\Support\BenchmarkHandler;
use RouterBenchmarks\Support\PreparedRequest;

abstract class AdapterBenchCase
{
    protected RouterAdapterInterface $adapter;
    protected RouteDataset $dataset;
    protected PreparedRequest $request;

    /** @param array{router: string, size: int} $params */
    protected function createAdapter(array $params): void
    {
        $this->adapter = AdapterRegistry::byName($params['router']);
        $this->dataset = DatasetFactory::create($params['size']);
    }

    protected function registerDataset(): void
    {
        foreach ($this->dataset->routes as $route) {
            $this->adapter->addRoute($route, BenchmarkHandler::forRoute($route->id));
        }
    }

    protected function prepare(GeneratedRoute $route): void
    {
        $this->request = $this->adapter->prepareRequest($route->methods[0], $route->samplePath);
    }
}
