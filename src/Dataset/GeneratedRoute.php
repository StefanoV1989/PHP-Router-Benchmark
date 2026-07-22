<?php

declare(strict_types=1);

namespace RouterBenchmarks\Dataset;

final readonly class GeneratedRoute
{
    /**
     * @param list<string> $methods
     * @param array<string, string> $constraints
     * @param array<string, string> $expectedParameters
     */
    public function __construct(
        public string $id,
        public array $methods,
        public string $path,
        public string $samplePath,
        public RouteKind $kind,
        public array $constraints = [],
        public array $expectedParameters = [],
    ) {
    }

    /** @return list<string> */
    public function parameterNames(): array
    {
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\??\}/', $this->path, $matches);

        return $matches[1];
    }
}
