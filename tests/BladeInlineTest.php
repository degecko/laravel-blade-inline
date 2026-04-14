<?php

namespace DeGecko\BladeInline\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\View;
use Orchestra\Testbench\TestCase;

class BladeInlineTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [\DeGecko\BladeInline\BladeInlineServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app->setBasePath(__DIR__);
        $app['config']->set('view.paths', [__DIR__ . '/resources/views']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('view:clear');
    }

    // ─── Basic Inlining ──────────────────────────────────────────

    public function test_basic_inline(): void
    {
        $this->assertView('basic', [], 'Hello, World!');
    }

    public function test_inline_with_variables(): void
    {
        $this->assertView('with-variables', ['name' => 'Taylor'], 'Hello, Taylor!');
    }

    public function test_inline_with_multiple_variables(): void
    {
        $this->assertView('with-multiple-vars', [
            'first' => 'Jane',
            'last' => 'Doe',
        ], 'Jane Doe');
    }

    public function test_props_are_stripped(): void
    {
        $this->assertView('props-stripped', ['name' => 'World'], 'Hello, World!');
    }

    // ─── Blade Directives Inside Inlined Partials ────────────────

    public function test_if_directive(): void
    {
        $this->assertView('directive-if', ['show' => true], 'Visible');
        $this->assertView('directive-if', ['show' => false], 'Hidden');
    }

    public function test_unless_directive(): void
    {
        $this->assertView('directive-unless', ['hidden' => false], 'Shown');
        $this->assertView('directive-unless', ['hidden' => true], '');
    }

    public function test_foreach_directive(): void
    {
        $this->assertView('directive-foreach', [
            'items' => ['a', 'b', 'c'],
        ], 'a b c');
    }

    public function test_forelse_directive(): void
    {
        $this->assertView('directive-forelse', ['items' => ['x']], 'x');
        $this->assertView('directive-forelse', ['items' => []], 'Empty');
    }

    public function test_switch_directive(): void
    {
        $this->assertView('directive-switch', ['val' => 'a'], 'Alpha');
        $this->assertView('directive-switch', ['val' => 'b'], 'Beta');
        $this->assertView('directive-switch', ['val' => 'z'], 'Other');
    }

    public function test_isset_directive(): void
    {
        $this->assertView('directive-isset', ['name' => 'Hi'], 'Hi');
        $this->assertView('directive-isset', [], '');
    }

    public function test_empty_directive(): void
    {
        $this->assertView('directive-empty', ['items' => []], 'None');
        $this->assertView('directive-empty', ['items' => [1]], '');
    }

    public function test_raw_php_directive(): void
    {
        $this->assertView('directive-php', [], '42');
    }

    public function test_comments_are_stripped(): void
    {
        $this->assertView('directive-comment', [], 'Visible');
    }

    public function test_escaped_output(): void
    {
        $this->assertView('directive-escaped', ['html' => '<b>bold</b>'], '&lt;b&gt;bold&lt;/b&gt;');
    }

    public function test_unescaped_output(): void
    {
        $this->assertView('directive-unescaped', ['html' => '<b>bold</b>'], '<b>bold</b>');
    }

    public function test_once_directive(): void
    {
        $this->assertView('directive-once', [], 'Once');
    }

    public function test_class_directive(): void
    {
        $this->assertViewContains('directive-class', ['active' => true], 'class="item active"');
        $this->assertViewContains('directive-class', ['active' => false], 'class="item"');
    }

    public function test_checked_directive(): void
    {
        $this->assertViewContains('directive-checked', ['on' => true], 'checked');
        $this->assertViewNotContains('directive-checked', ['on' => false], 'checked');
    }

    // ─── @inline Inside Loops ────────────────────────────────────

    public function test_inline_inside_foreach(): void
    {
        $this->assertView('loop-foreach', [
            'names' => ['Alice', 'Bob'],
        ], 'Hello, Alice! Hello, Bob!');
    }

    public function test_inline_with_loop_variable(): void
    {
        $this->assertView('loop-variable', [
            'items' => ['x', 'y', 'z'],
        ], '1 2 3');
    }

    // ─── Multiple Variables ──────────────────────────────────────

    public function test_inline_with_three_variables(): void
    {
        $this->assertView('multi-vars-three', [
            'name' => 'Alice',
            'age' => 30,
            'role' => 'admin',
        ], 'Alice (30) - admin');
    }

    public function test_inline_with_mixed_type_variables(): void
    {
        $this->assertView('multi-vars-mixed-types', [
            'label' => 'Widgets',
            'count' => 5,
            'active' => true,
        ], 'Widgets: 5 items active');

        $this->assertView('multi-vars-mixed-types', [
            'label' => 'Gadgets',
            'count' => 0,
            'active' => false,
        ], 'Gadgets: 0 items inactive');
    }

    public function test_inline_variables_from_parent_scope(): void
    {
        $this->assertView('multi-vars-from-parent', [
            'first' => 'Jane',
            'last' => 'Doe',
        ], 'Jane Doe');
    }

    public function test_inline_passed_variable_overrides_parent(): void
    {
        $this->assertView('multi-vars-partial-override', [
            'name' => 'Original',
            'override' => 'Overridden',
        ], 'Hello, Overridden!');
    }

    // ─── Caching ─────────────────────────────────────────────────

    public function test_view_clear_invalidates_compiled_inline(): void
    {
        $mutablePath = __DIR__ . '/resources/views/partials/mutable.blade.php';
        $original = file_get_contents($mutablePath);

        try {
            // Render once to compile and cache
            $this->assertView('cache-test', [], 'Original');

            // Modify the partial source
            file_put_contents($mutablePath, 'Modified');

            // Without clearing, the compiled cache is stale — since @inline
            // embeds at compile-time, the old compiled output persists
            $compiledPath = $this->app['config']['view.compiled'];
            $cachedFiles = glob($compiledPath . '/*.php');
            $this->assertNotEmpty($cachedFiles, 'Compiled view cache should exist');

            // Clear the view cache
            Artisan::call('view:clear');

            // Verify cache directory is empty
            $cachedFiles = glob($compiledPath . '/*.php');
            $this->assertEmpty($cachedFiles, 'view:clear should remove compiled views');

            // Re-render picks up the modified partial
            $this->assertView('cache-test', [], 'Modified');
        } finally {
            file_put_contents($mutablePath, $original);
        }
    }

    public function test_dev_recompile_picks_up_partial_changes(): void
    {
        $mutablePath = __DIR__ . '/resources/views/partials/mutable.blade.php';
        $original = file_get_contents($mutablePath);

        try {
            // Render once to compile and cache
            $this->assertView('cache-test', [], 'Original');

            // Modify the partial source
            file_put_contents($mutablePath, 'Updated');

            // In dev, Laravel only recompiles when the parent view file is newer
            // than its compiled version. Since @inline embeds at compile-time,
            // partial-only changes are invisible until the parent is recompiled.
            // Clearing the cache forces recompilation on next render.
            Artisan::call('view:clear');

            // The Blade compiler caches compiled paths in-process, so we need
            // a fresh compiler engine to simulate a new request in dev.
            $this->app['view.engine.resolver']->forget('blade');
            $this->app['view.engine.resolver']->register('blade', function () {
                $compiler = $this->app['blade.compiler'];
                return new \Illuminate\View\Engines\CompilerEngine($compiler, $this->app['files']);
            });

            $this->assertView('cache-test', [], 'Updated');
        } finally {
            file_put_contents($mutablePath, $original);
        }
    }

    public function test_view_cache_compiles_inlined_content_directly(): void
    {
        $compiledPath = $this->app['config']['view.compiled'];

        // Render a view using @inline to populate the cache
        $this->assertView('with-variables', ['name' => 'Cached'], 'Hello, Cached!');

        // Compiled views should exist
        $cachedFiles = glob($compiledPath . '/*.php');
        $this->assertNotEmpty($cachedFiles, 'Compiled views should be cached');

        // Verify the compiled output contains the inlined partial content
        // directly (not a view factory call like @include would produce)
        $cacheContent = '';
        foreach ($cachedFiles as $file) {
            $cacheContent .= file_get_contents($file);
        }

        // The inlined greeting partial compiles {{ $name }} to escaped echo.
        // It should NOT contain __env->make for the partial.
        $this->assertStringNotContainsString(
            "\$__env->make('partials.greeting'",
            $cacheContent,
            'Inlined partials should be compiled inline, not as view factory calls'
        );

        // The compiled output should contain the direct PHP echo for {{ $name }}
        $this->assertStringContainsString(
            'e($name)',
            $cacheContent,
            'Compiled cache should contain the inlined partial code directly'
        );
    }

    public function test_cached_view_serves_stale_until_cleared(): void
    {
        $mutablePath = __DIR__ . '/resources/views/partials/mutable.blade.php';
        $original = file_get_contents($mutablePath);

        try {
            // Compile and cache
            $this->assertView('cache-test', [], 'Original');

            // Modify partial — but compiled cache still has old content
            file_put_contents($mutablePath, 'Changed');

            // Same process: engine serves from compiled cache (stale)
            $this->assertView('cache-test', [], 'Original');
        } finally {
            file_put_contents($mutablePath, $original);
        }
    }

    // ─── Edge Cases ──────────────────────────────────────────────

    public function test_inline_with_nested_blade(): void
    {
        $this->assertView('nested-blade', [
            'items' => ['a', 'b'],
            'show' => true,
        ], 'a b');
    }

    public function test_inline_preserves_surrounding_content(): void
    {
        $this->assertView('surrounding', [], 'Before Hello, World! After');
    }

    public function test_inline_with_whitespace_in_expression(): void
    {
        $this->assertView('whitespace-expr', ['name' => 'Test'], 'Hello, Test!');
    }

    // ─── Helper ──────────────────────────────────────────────────

    private function assertView(string $view, array $data, string $expected): void
    {
        $rendered = trim(View::make($view, $data)->render());
        // Normalize whitespace for easier assertions.
        $rendered = preg_replace('/\s+/', ' ', $rendered);
        $this->assertSame($expected, trim($rendered));
    }

    private function assertViewContains(string $view, array $data, string $needle): void
    {
        $rendered = trim(View::make($view, $data)->render());
        $this->assertStringContainsString($needle, $rendered);
    }

    private function assertViewNotContains(string $view, array $data, string $needle): void
    {
        $rendered = trim(View::make($view, $data)->render());
        $this->assertStringNotContainsString($needle, $rendered);
    }
}
