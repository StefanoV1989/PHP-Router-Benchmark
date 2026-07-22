<?php

declare(strict_types=1);

namespace RouterBenchmarks\Result;

final readonly class MatchResult
{
    /**
     * @param array<string, string> $parameters
     * @param list<string> $allowedMethods
     */
    public function __construct(
        public MatchStatus $status,
        public ?string $routeId = null,
        public array $parameters = [],
        public array $allowedMethods = [],
    ) {
    }

    /** @param array<string, string> $parameters */
    public static function found(string $routeId, array $parameters = []): self
    {
        return new self(MatchStatus::Found, $routeId, $parameters);
    }

    public static function notFound(): self
    {
        return new self(MatchStatus::NotFound);
    }

    /** @param list<string> $allowedMethods */
    public static function methodNotAllowed(array $allowedMethods = []): self
    {
        return new self(MatchStatus::MethodNotAllowed, allowedMethods: $allowedMethods);
    }
}
