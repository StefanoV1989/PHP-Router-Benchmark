<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/benchmarks', __DIR__ . '/tests'])
    ->append([
        __DIR__ . '/.php-cs-fixer.dist.php',
        __DIR__ . '/bin/benchmark',
        __DIR__ . '/bin/verify',
        __DIR__ . '/bin/export-results',
        __DIR__ . '/bin/export-diagnostics',
        __DIR__ . '/bin/summarize-results',
        __DIR__ . '/bin/memory-worker',
        __DIR__ . '/bin/cache-worker',
    ]);

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'declare_strict_types' => true,
        'native_function_invocation' => ['include' => ['@compiler_optimized']],
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'single_quote' => true,
    ])
    ->setFinder($finder);
