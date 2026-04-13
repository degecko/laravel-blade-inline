<?php

namespace DeGecko\BladeInline\Tests;

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
