<?php

namespace DeGecko\BladeInline;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class BladeInlineServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Blade::directive('inline', function (string $expression) {
            $expression = str($expression);
            $view = $expression->before(',')->trim('\'" ')->replace('.', '/')->toString();
            $data = $expression->contains(',') ? $expression->after(',')->trim('[] ')
                ->replaceMatches('/\s*.(.*?).\s*=>\s*(.*?)(,|\n|$)/', function ($match) {
                    return "$$match[1] = $match[2];\n";
                }) : null;

            $path = resource_path("views/$view.blade.php");
            $compiledPath = storage_path('framework/views/inline_' . md5($path) . '.php');

            // Compile the partial now (at parent compile time).
            self::compile($path, $compiledPath);

            // At runtime: include the compiled file directly (no view factory).
            // If the source changed, recompile on the fly before including.
            $dataBlock = $data ? "<?php $data ?>\n" : '';

            return $dataBlock . "<?php
                clearstatcache(true, '$path');
                if (filemtime('$path') > filemtime('$compiledPath')) {
                    \\DeGecko\\BladeInline\\BladeInlineServiceProvider::compile('$path', '$compiledPath');
                }
                include '$compiledPath';
            ?>";
        });
    }

    public static function compile(string $sourcePath, string $compiledPath): void
    {
        $source = file_get_contents($sourcePath);

        // Strip @props — not needed for inlined partials since variables
        // come from the parent scope. Avoids ComponentAttributeBag overhead.
        $source = preg_replace('/@props\s*\([\s\S]*?\)\s*\n?/', '', $source);

        file_put_contents($compiledPath, Blade::compileString($source));

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($compiledPath, true);
        }
    }
}
