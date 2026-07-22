<?php

declare(strict_types=1);

namespace RouterBenchmarks\Contract;

enum Feature: string
{
    case MatchWithoutDispatch = 'match_without_dispatch';
    case NativeHandlerDispatch = 'native_handler_dispatch';
    case MethodNotAllowed = 'method_not_allowed';
    case ConstrainedParameters = 'constrained_parameters';
    case OptionalParameters = 'optional_parameters';
    case Finalization = 'finalization';
    case Compilation = 'compilation';
    case CompiledCache = 'compiled_cache';
}
