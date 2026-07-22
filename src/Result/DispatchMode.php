<?php

declare(strict_types=1);

namespace RouterBenchmarks\Result;

enum DispatchMode: string
{
    case Native = 'native';
    case AdapterManaged = 'adapter_managed';
}
