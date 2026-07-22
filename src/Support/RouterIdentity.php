<?php

declare(strict_types=1);

namespace RouterBenchmarks\Support;

final readonly class RouterIdentity
{
    public function __construct(
        public string $name,
        public string $package,
        public string $version,
        public string $commit,
    ) {
    }
}
