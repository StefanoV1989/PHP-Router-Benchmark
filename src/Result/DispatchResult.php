<?php

declare(strict_types=1);

namespace RouterBenchmarks\Result;

final readonly class DispatchResult
{
    public function __construct(
        public MatchStatus $status,
        public DispatchMode $mode,
        public mixed $value = null,
    ) {
    }
}
