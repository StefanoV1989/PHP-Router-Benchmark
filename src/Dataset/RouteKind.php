<?php

declare(strict_types=1);

namespace RouterBenchmarks\Dataset;

enum RouteKind: string
{
    case StaticRoute = 'static';
    case SingleParameter = 'single_parameter';
    case MultipleParameters = 'multiple_parameters';
    case Constrained = 'constrained';
}
