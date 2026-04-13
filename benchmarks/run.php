<?php

/**
 * Benchmark: @include vs @inline in loops.
 *
 * Run from a Laravel project that has this package installed:
 *   php vendor/degecko/laravel-blade-inline/benchmarks/run.php
 *
 * Or from the package root during development:
 *   php benchmarks/run.php
 */

// ─── Bootstrap ───────────────────────────────────────────────────

// Try to find the Laravel app — either from a project or from the package root.
$autoloadPaths = [
    __DIR__ . '/../../../autoload.php',  // vendor/degecko/laravel-blade-inline/benchmarks
    __DIR__ . '/../vendor/autoload.php', // package root
];

foreach ($autoloadPaths as $autoload) {
    if (file_exists($autoload)) {
        require $autoload;
        break;
    }
}

$baseDir = sys_get_temp_dir() . '/blade-inline-bench';
$viewsDir = "$baseDir/resources/views";
$compiledDir = "$baseDir/compiled";

@mkdir($viewsDir . '/partials', 0755, true);
@mkdir($compiledDir, 0755, true);
@mkdir("$baseDir/bootstrap/cache", 0755, true);

$app = (new Orchestra\Testbench\Foundation\Application($baseDir))->createApplication();
$app->setBasePath($baseDir);
$app['config']->set('view.paths', [$viewsDir]);
$app['config']->set('view.compiled', $compiledDir);
$app->register(DeGecko\BladeInline\BladeInlineServiceProvider::class);

// ─── Setup view files ────────────────────────────────────────────

file_put_contents("$viewsDir/partials/card.blade.php", <<<'BLADE'
<div class="card">
    <h3>{{ $item['title'] }}</h3>
    <p>{{ $item['body'] }}</p>
    <span>{{ $item['author'] }}</span>
</div>
BLADE);

// ─── Generate data ──────────────────────────────────────────────

$items = [];
for ($i = 1; $i <= 1000; $i++) {
    $items[] = [
        'title' => "Item $i",
        'body' => "Description for item $i with enough text to be realistic.",
        'author' => "Author $i",
    ];
}

// ─── Benchmark function ─────────────────────────────────────────

function benchmark(string $label, string $viewName, array $data, int $iterations): array
{
    $view = Illuminate\Support\Facades\View::getFacadeRoot();

    // Warm up: compile and render once.
    $view->make($viewName, $data)->render();

    $times = [];
    for ($i = 0; $i < $iterations; $i++) {
        $start = hrtime(true);
        $view->make($viewName, $data)->render();
        $times[] = (hrtime(true) - $start) / 1e6; // ms
    }

    sort($times);
    $count = count($times);

    return [
        'label'  => $label,
        'min'    => $times[0],
        'max'    => $times[$count - 1],
        'median' => $times[(int) ($count / 2)],
        'avg'    => array_sum($times) / $count,
    ];
}

// ─── Run benchmarks ─────────────────────────────────────────────

$iterations = 50;
$loopSizes = [10, 50, 100, 500, 1000];

echo "Benchmark: @include vs @inline\n";
echo str_repeat('=', 70) . "\n";
echo "PHP " . PHP_VERSION . " | " . $iterations . " iterations per test\n";
echo str_repeat('=', 70) . "\n\n";

printf("%-12s  %-10s  %10s  %10s  %10s  %8s\n",
    'Loop Size', 'Method', 'Avg (ms)', 'Med (ms)', 'Min (ms)', 'Diff');
echo str_repeat('-', 70) . "\n";

foreach ($loopSizes as $size) {
    $subset = array_slice($items, 0, $size);

    // Generate views dynamically for this loop size.
    file_put_contents("$viewsDir/bench-include-$size.blade.php",
        "@foreach(\$items as \$item)\n    @include('partials.card')\n@endforeach"
    );
    file_put_contents("$viewsDir/bench-inline-$size.blade.php",
        "@foreach(\$items as \$item)\n    @inline('partials.card')\n@endforeach"
    );

    // Clear compiled views before each benchmark pair.
    array_map('unlink', glob("$compiledDir/*.php"));

    $include = benchmark('@include', "bench-include-$size", ['items' => $subset], $iterations);
    $inline  = benchmark('@inline',  "bench-inline-$size",  ['items' => $subset], $iterations);

    $diff = (($include['avg'] - $inline['avg']) / $include['avg']) * 100;
    $sign = $diff > 0 ? '+' : '';

    printf("%-12d  %-10s  %10.2f  %10.2f  %10.2f  %8s\n",
        $size, '@include', $include['avg'], $include['median'], $include['min'], '');
    printf("%-12s  %-10s  %10.2f  %10.2f  %10.2f  %s%s\n",
        '', '@inline', $inline['avg'], $inline['median'], $inline['min'],
        $sign . number_format($diff, 1) . '%',
        $diff > 0 ? ' faster' : ' slower'
    );
    echo str_repeat('-', 70) . "\n";
}

echo "\nPositive % = @inline is faster than @include.\n";

// ─── Cleanup ────────────────────────────────────────────────────

$cleanup = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST,
);

foreach ($cleanup as $file) {
    $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
}

@rmdir($baseDir);
