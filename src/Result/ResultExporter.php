<?php

declare(strict_types=1);

namespace RouterBenchmarks\Result;

/**
 * @phpstan-type ResultRow array{
 *     scenario: string,
 *     detail: string,
 *     router: string,
 *     size: int|null,
 *     iterations: int,
 *     revolutions: int,
 *     median_ns: float,
 *     mean_ns: float,
 *     min_ns: float,
 *     max_ns: float,
 *     stddev_ns: float,
 *     rsd_percent: float,
 *     ops_per_second: float|null,
 *     requests_per_second: float|null,
 *     requests_per_minute: float|null,
 *     relative: float|null,
 *     source: string
 * }
 */
final class ResultExporter
{
    /** @return list<ResultRow> */
    public function readPhpBench(string $file): array
    {
        $xml = simplexml_load_file($file);
        if (!$xml instanceof \SimpleXMLElement) {
            throw new \RuntimeException('Unable to read PHPBench XML: ' . $file);
        }
        $rows = [];
        foreach ($xml->suite->benchmark as $benchmark) {
            $class = (string) $benchmark['class'];
            $benchmarkName = substr($class, (int) strrpos($class, '\\') + 1);
            foreach ($benchmark->subject as $subject) {
                $subjectName = (string) $subject['name'];
                foreach ($subject->variant as $variant) {
                    $parameters = [];
                    foreach ($variant->{'parameter-set'}->parameter as $parameter) {
                        $parameters[(string) $parameter['name']] = (string) $parameter['value'];
                    }
                    $values = [];
                    foreach ($variant->iteration as $iteration) {
                        $values[] = (float) $iteration['time-avg'];
                    }
                    sort($values);
                    $unit = (string) $variant['output-time-unit'];
                    $factor = self::nanosecondFactor($unit);
                    $stats = $variant->stats;
                    $meanNs = (float) $stats['mean'] * $factor;
                    $detail = self::detail($parameters);
                    $scenario = self::scenario($benchmarkName, $subjectName, $parameters);
                    $operationsPerSecond = $meanNs > 0.0 ? 1_000_000_000 / $meanNs : null;
                    $requestsPerSecond = self::isRequestScenario($scenario) ? $operationsPerSecond : null;
                    $rows[] = [
                        'scenario' => $scenario,
                        'detail' => $detail,
                        'router' => $parameters['router'] ?? 'unknown',
                        'size' => isset($parameters['size']) ? (int) $parameters['size'] : null,
                        'iterations' => \count($values),
                        'revolutions' => (int) $variant['revs'],
                        'median_ns' => self::median($values) * $factor,
                        'mean_ns' => $meanNs,
                        'min_ns' => (float) $stats['min'] * $factor,
                        'max_ns' => (float) $stats['max'] * $factor,
                        'stddev_ns' => (float) $stats['stdev'] * $factor,
                        'rsd_percent' => (float) $stats['rstdev'],
                        'ops_per_second' => $operationsPerSecond,
                        'requests_per_second' => $requestsPerSecond,
                        'requests_per_minute' => $requestsPerSecond === null ? null : $requestsPerSecond * 60,
                        'relative' => null,
                        'source' => basename($file),
                    ];
                }
            }
        }

        return self::sortRows(self::addRelative($rows));
    }

    /**
     * @param list<ResultRow> $rows
     * @param array<string, mixed>|null $environment
     */
    public function writeAll(array $rows, string $prefix, ?array $environment): void
    {
        file_put_contents(
            $prefix . '.json',
            json_encode(
                ['environment' => $environment, 'results' => $rows],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ) . PHP_EOL,
            LOCK_EX,
        );
        $this->writeCsv($rows, $prefix . '.csv', $environment);
        file_put_contents($prefix . '.md', $this->markdown($rows, $environment), LOCK_EX);
    }

    /**
     * @param list<ResultRow> $rows
     * @param array<string, mixed>|null $environment
     */
    public function console(array $rows, ?array $environment = null): string
    {
        $lines = [\sprintf(
            'PHP %s | runtime=%s | execution=%s | opcache.enable=%s | opcache.enable_cli=%s | '
            . 'opcache.jit=%s | opcache.jit_buffer_size=%s | opcache.memory_consumption=%s | '
            . 'opcache.validate_timestamps=%s | error_reporting=%s',
            $environment['php']['version'] ?? 'unknown',
            $environment['run']['runtime_profile'] ?? 'unknown',
            $environment['execution_environment'] ?? 'unknown',
            $environment['runtime_target']['opcache.enable'] ?? 'unknown',
            $environment['runtime_target']['opcache.enable_cli'] ?? 'unknown',
            $environment['runtime_target']['opcache.jit'] ?? 'unknown',
            $environment['runtime_target']['opcache.jit_buffer_size'] ?? 'unknown',
            $environment['runtime_target']['opcache.memory_consumption'] ?? 'unknown',
            $environment['runtime_target']['opcache.validate_timestamps'] ?? 'unknown',
            $environment['runtime_target']['error_reporting'] ?? 'unknown',
        ), \sprintf(
            '%-24s | %-22s | %7s | %12s | %12s | %12s | %14s | %10s',
            'Scenario',
            'Router',
            'Routes',
            'Median ns',
            'Ops/s',
            'Requests/s',
            'Requests/min',
            'Relative',
        )];
        $lines[] = str_repeat('-', 132);
        foreach ($rows as $row) {
            $lines[] = \sprintf(
                '%-24s | %-22s | %7s | %12.1f | %12.0f | %12s | %14s | %9.2fx',
                substr((string) $row['scenario'], 0, 24),
                substr((string) $row['router'], 0, 22),
                (string) ($row['size'] ?? '-'),
                (float) $row['median_ns'],
                (float) ($row['ops_per_second'] ?? 0),
                $row['requests_per_second'] === null ? 'n/a' : \sprintf('%.0f', $row['requests_per_second']),
                $row['requests_per_minute'] === null ? 'n/a' : \sprintf('%.0f', $row['requests_per_minute']),
                (float) ($row['relative'] ?? 0),
            );
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param list<ResultRow> $rows
     * @param array<string, mixed>|null $environment
     */
    private function writeCsv(array $rows, string $file, ?array $environment): void
    {
        $stream = fopen($file, 'wb');
        if ($stream === false) {
            throw new \RuntimeException('Unable to write CSV file.');
        }
        try {
            if ($rows === []) {
                return;
            }
            $runtime = [
                'php_version' => $environment['php']['version'] ?? 'unknown',
                'runtime_profile' => $environment['run']['runtime_profile'] ?? 'unknown',
                'execution_environment' => $environment['execution_environment'] ?? 'unknown',
                'opcache_enable' => $environment['runtime_target']['opcache.enable'] ?? 'unknown',
                'opcache_enable_cli' => $environment['runtime_target']['opcache.enable_cli'] ?? 'unknown',
                'opcache_jit' => $environment['runtime_target']['opcache.jit'] ?? 'unknown',
                'opcache_jit_buffer_size' => $environment['runtime_target']['opcache.jit_buffer_size'] ?? 'unknown',
                'opcache_memory_consumption' => $environment['runtime_target']['opcache.memory_consumption'] ?? 'unknown',
                'opcache_validate_timestamps' => $environment['runtime_target']['opcache.validate_timestamps'] ?? 'unknown',
                'error_reporting' => $environment['runtime_target']['error_reporting'] ?? 'unknown',
            ];
            fputcsv($stream, [...array_keys($runtime), ...array_keys($rows[0])]);
            foreach ($rows as $row) {
                fputcsv($stream, [...array_values($runtime), ...array_values($row)]);
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param list<ResultRow> $rows
     * @param array<string, mixed>|null $environment
     */
    private function markdown(array $rows, ?array $environment): string
    {
        $runtime = $environment['run']['runtime_profile'] ?? 'unknown';
        $execution = $environment['execution_environment'] ?? 'unknown';
        $php = $environment['php']['version'] ?? 'unknown';
        $output = "# PHP router benchmark report\n\n";
        $output .= \sprintf(
            "Runtime: PHP %s, profile `%s`, execution `%s`. Dataset seed: `%d`.\n\n",
            $php,
            $runtime,
            $execution,
            \RouterBenchmarks\Dataset\DatasetFactory::DEFAULT_SEED,
        );
        $output .= \sprintf(
            'Settings: `opcache.enable=%s`, `opcache.enable_cli=%s`, `opcache.jit=%s`, '
            . '`opcache.jit_buffer_size=%s`, `opcache.memory_consumption=%s`, '
            . "`opcache.validate_timestamps=%s`, `error_reporting=%s`.\n\n",
            $environment['runtime_target']['opcache.enable'] ?? 'unknown',
            $environment['runtime_target']['opcache.enable_cli'] ?? 'unknown',
            $environment['runtime_target']['opcache.jit'] ?? 'unknown',
            $environment['runtime_target']['opcache.jit_buffer_size'] ?? 'unknown',
            $environment['runtime_target']['opcache.memory_consumption'] ?? 'unknown',
            $environment['runtime_target']['opcache.validate_timestamps'] ?? 'unknown',
            $environment['runtime_target']['error_reporting'] ?? 'unknown',
        );
        $output .= 'Relative values are calculated only within the same scenario, detail and route count; 1.00x is fastest. '
            . 'Rows are ordered by mean time inside each comparable group. A green value on a later row is better than '
            . "the 1.00x row for that individual column. No overall winner is calculated.\n\n";

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row['scenario']][] = $row;
        }
        foreach ($grouped as $scenario => $scenarioRows) {
            $output .= '## ' . ucwords(str_replace('_', ' ', $scenario)) . "\n\n";
            $output .= "| Router | Routes | Detail | Median ns/op | Mean ns/op | Min | Max | Stddev | RSD | Ops/s | Requests/s | Requests/min | Relative |\n";
            $output .= "|---|---:|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|\n";
            $baseline = null;
            $cohort = null;
            foreach ($scenarioRows as $row) {
                $rowCohort = self::cohortKey($row);
                if ($rowCohort !== $cohort) {
                    $cohort = $rowCohort;
                    $baseline = $row;
                }
                if ($baseline === null) {
                    throw new \LogicException('Missing benchmark cohort baseline.');
                }
                $winner = $row['router'] === $baseline['router'];
                $output .= \sprintf(
                    "| %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s |\n",
                    $winner ? '**' . $row['router'] . '**' : $row['router'],
                    $row['size'] ?? 'n/a',
                    $row['detail'] === '' ? 'default' : $row['detail'],
                    self::metric('%.1f', $row['median_ns'], $baseline['median_ns'], false, $winner),
                    self::metric('%.1f', $row['mean_ns'], $baseline['mean_ns'], false, $winner),
                    self::metric('%.1f', $row['min_ns'], $baseline['min_ns'], false, $winner),
                    self::metric('%.1f', $row['max_ns'], $baseline['max_ns'], false, $winner),
                    self::metric('%.1f', $row['stddev_ns'], $baseline['stddev_ns'], false, $winner),
                    self::metric('%.2f%%', $row['rsd_percent'], $baseline['rsd_percent'], false, $winner),
                    self::metric(
                        '%.0f',
                        (float) $row['ops_per_second'],
                        (float) $baseline['ops_per_second'],
                        true,
                        $winner,
                    ),
                    $row['requests_per_second'] === null
                        ? 'n/a'
                        : self::metric(
                            '%.0f',
                            $row['requests_per_second'],
                            (float) $baseline['requests_per_second'],
                            true,
                            $winner,
                        ),
                    $row['requests_per_minute'] === null
                        ? 'n/a'
                        : self::metric(
                            '%.0f',
                            $row['requests_per_minute'],
                            (float) $baseline['requests_per_minute'],
                            true,
                            $winner,
                        ),
                    \sprintf('%.2fx', $row['relative']),
                );
            }
            $output .= "\n";
        }

        return $output;
    }

    /** @param array<string, string> $parameters */
    private static function scenario(string $benchmark, string $subject, array $parameters): string
    {
        return match ($benchmark) {
            'RegistrationBench' => 'registration',
            'CompilationBench' => 'compilation',
            'FinalizationBench' => 'finalization',
            'ColdStartBench' => 'cold_start',
            'StaticRoutesBench' => 'warm_static_match',
            'OverlappingRoutesBench' => 'overlapping_static_dynamic_match',
            'DynamicRoutesBench' => ($parameters['kind'] ?? '') === 'multiple_parameters'
                ? 'warm_multiple_parameter_match'
                : 'warm_dynamic_match',
            'ConstrainedRoutesBench' => 'constrained_match',
            'MixedRoutesBench' => 'full_dispatch',
            'NotFoundBench' => 'route_miss',
            'MethodNotAllowedBench' => 'method_not_allowed',
            'CacheLoadBench' => str_contains($subject, 'LoadCache') ? 'cache_load' : 'cached_dispatch',
            'MemoryBench' => 'memory_diagnostic',
            'NativeDirectCallBench' => str_contains($subject, 'FullDispatch')
                ? 'native_full_dispatch'
                : 'native_match',
            default => strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $benchmark) ?? $benchmark),
        };
    }

    /** @param array<string, string> $parameters */
    private static function detail(array $parameters): string
    {
        unset($parameters['router'], $parameters['size']);
        ksort($parameters);

        return implode(', ', array_map(
            static fn (string $key, string $value): string => $key . '=' . $value,
            array_keys($parameters),
            array_values($parameters),
        ));
    }

    /** @param list<float> $values */
    private static function median(array $values): float
    {
        $count = \count($values);
        if ($count === 0) {
            return 0.0;
        }
        $middle = intdiv($count, 2);

        return $count % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    private static function nanosecondFactor(string $unit): float
    {
        return match ($unit) {
            'seconds' => 1_000_000_000,
            'milliseconds' => 1_000_000,
            'microseconds' => 1_000,
            'nanoseconds' => 1,
            default => 1_000,
        };
    }

    private static function isRequestScenario(string $scenario): bool
    {
        return !\in_array($scenario, [
            'registration',
            'compilation',
            'finalization',
            'memory_diagnostic',
            'cache_load',
        ], true);
    }

    /**
     * @param list<ResultRow> $rows
     * @return list<ResultRow>
     */
    private static function addRelative(array $rows): array
    {
        $fastest = [];
        foreach ($rows as $row) {
            $key = $row['scenario'] . '|' . $row['size'] . '|' . $row['detail'];
            $mean = (float) $row['mean_ns'];
            $fastest[$key] = isset($fastest[$key]) ? min($fastest[$key], $mean) : $mean;
        }
        foreach ($rows as &$row) {
            $key = $row['scenario'] . '|' . $row['size'] . '|' . $row['detail'];
            $row['relative'] = $fastest[$key] > 0.0 ? (float) $row['mean_ns'] / $fastest[$key] : null;
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<ResultRow> $rows
     * @return list<ResultRow>
     */
    private static function sortRows(array $rows): array
    {
        usort($rows, static function (array $left, array $right): int {
            return [$left['scenario'], $left['size'] ?? -1, $left['detail'], $left['mean_ns'], $left['router']]
                <=> [$right['scenario'], $right['size'] ?? -1, $right['detail'], $right['mean_ns'], $right['router']];
        });

        return $rows;
    }

    /** @param ResultRow $row */
    private static function cohortKey(array $row): string
    {
        return $row['scenario'] . '|' . ($row['size'] ?? 'n/a') . '|' . $row['detail'];
    }

    private static function metric(
        string $format,
        float $value,
        float $baseline,
        bool $higherIsBetter,
        bool $isBaseline,
    ): string {
        $formatted = \sprintf($format, $value);
        $isBetter = $higherIsBetter ? $value > $baseline : $value < $baseline;

        return !$isBaseline && $isBetter
            ? '<span style="color: #16833b;">' . $formatted . '</span>'
            : $formatted;
    }
}
