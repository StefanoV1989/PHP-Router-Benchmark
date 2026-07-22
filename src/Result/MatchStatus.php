<?php

declare(strict_types=1);

namespace RouterBenchmarks\Result;

enum MatchStatus: string
{
    case Found = 'found';
    case NotFound = 'not_found';
    case MethodNotAllowed = 'method_not_allowed';
}
