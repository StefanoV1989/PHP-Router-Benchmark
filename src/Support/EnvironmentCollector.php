<?php

declare(strict_types=1);

namespace RouterBenchmarks\Support;

final class EnvironmentCollector
{
    /**
     * @param array<string, int|string> $targetIni
     * @return array<string, mixed>
     */
    public static function collect(array $targetIni = []): array
    {
        $gitStatus = self::command('git status --porcelain');

        return [
            'captured_at' => gmdate(DATE_ATOM),
            'execution_environment' => is_file('/.dockerenv') ? 'container' : 'native_host',
            'php' => [
                'version' => PHP_VERSION,
                'version_id' => PHP_VERSION_ID,
                'sapi' => PHP_SAPI,
                'binary' => PHP_BINARY,
                'ini_file' => php_ini_loaded_file() ?: null,
                'ini_scanned' => php_ini_scanned_files() ?: null,
                'error_reporting' => error_reporting(),
                'deprecations_suppressed' => (error_reporting() & (E_DEPRECATED | E_USER_DEPRECATED)) === 0,
                'xdebug_loaded' => \extension_loaded('xdebug'),
            ],
            'runtime_target' => $targetIni,
            'opcache' => [
                'extension_loaded' => \extension_loaded('Zend OPcache'),
                'enable' => \ini_get('opcache.enable'),
                'enable_cli' => \ini_get('opcache.enable_cli'),
                'jit' => \ini_get('opcache.jit'),
                'jit_buffer_size' => \ini_get('opcache.jit_buffer_size'),
                'memory_consumption' => \ini_get('opcache.memory_consumption'),
                'validate_timestamps' => \ini_get('opcache.validate_timestamps'),
            ],
            'system' => [
                'os' => PHP_OS_FAMILY,
                'uname' => php_uname(),
                'architecture' => php_uname('m'),
                'cpu' => self::cpu(),
                'logical_cpus' => self::logicalCpus(),
                'ram_bytes' => self::ramBytes(),
            ],
            'project' => [
                'commit' => self::command('git rev-parse HEAD'),
                'dirty' => $gitStatus !== null && $gitStatus !== '',
                'dataset_seed' => \RouterBenchmarks\Dataset\DatasetFactory::DEFAULT_SEED,
            ],
            'routers' => array_map(
                static fn ($adapter): array => (array) $adapter->identity(),
                AdapterRegistry::all(),
            ),
            'php_ini_all' => self::iniValues(),
        ];
    }

    private static function cpu(): ?string
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            return self::command('sysctl -n machdep.cpu.brand_string');
        }
        if (is_readable('/proc/cpuinfo')) {
            $contents = file_get_contents('/proc/cpuinfo');
            if (\is_string($contents) && preg_match('/^model name\s*:\s*(.+)$/m', $contents, $matches) === 1) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    private static function logicalCpus(): ?int
    {
        $value = PHP_OS_FAMILY === 'Darwin'
            ? self::command('sysctl -n hw.logicalcpu')
            : self::command('getconf _NPROCESSORS_ONLN');

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }

    private static function ramBytes(): ?int
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            $value = self::command('sysctl -n hw.memsize');

            return $value !== null && ctype_digit($value) ? (int) $value : null;
        }
        if (is_readable('/proc/meminfo')) {
            $contents = file_get_contents('/proc/meminfo');
            if (\is_string($contents) && preg_match('/^MemTotal:\s+(\d+) kB$/m', $contents, $matches) === 1) {
                return (int) $matches[1] * 1024;
            }
        }

        return null;
    }

    /** @return array<string, string|false> */
    private static function iniValues(): array
    {
        $values = [];
        $settings = ini_get_all(null, false);
        if (!\is_array($settings)) {
            return $values;
        }
        foreach ($settings as $name => $value) {
            if (\is_string($name) && (\is_string($value) || $value === false)) {
                $values[$name] = $value;
            }
        }
        ksort($values);

        return $values;
    }

    private static function command(string $command): ?string
    {
        $output = shell_exec($command . ' 2>/dev/null');
        if (!\is_string($output)) {
            return null;
        }
        $output = trim($output);

        return $output === '' ? null : $output;
    }
}
