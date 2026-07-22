<?php

declare(strict_types=1);

namespace RouterBenchmarks\Support;

final readonly class PreparedRequest
{
    public function __construct(
        public string $method,
        public string $path,
        public mixed $native,
    ) {
    }
}
