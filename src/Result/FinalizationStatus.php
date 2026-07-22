<?php

declare(strict_types=1);

namespace RouterBenchmarks\Result;

enum FinalizationStatus: string
{
    case Compiled = 'compiled';
    case Finalized = 'finalized';
    case NotApplicable = 'not_applicable';
}
