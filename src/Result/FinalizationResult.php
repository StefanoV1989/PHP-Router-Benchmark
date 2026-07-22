<?php

declare(strict_types=1);

namespace RouterBenchmarks\Result;

final readonly class FinalizationResult
{
    public function __construct(public FinalizationStatus $status)
    {
    }
}
