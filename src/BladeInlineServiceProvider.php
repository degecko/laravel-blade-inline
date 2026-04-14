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

            $source = file_get_contents(resource_path("views/$view.blade.php"));

            // Strip @props — not needed for inlined partials since variables
            // come from the parent scope. Avoids ComponentAttributeBag overhead.
            $source = preg_replace('/@props\s*\([\s\S]*?\)\s*\n?/', '', $source);

            $compiled = Blade::compileString($source);
            $data and $compiled = "<?php $data ?>\n" . $compiled;

            return $compiled;
        });
    }
}
