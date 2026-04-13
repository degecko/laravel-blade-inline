# Laravel Blade Inline

Inline Blade partials at compile time for faster rendering in loops.

## The problem

When you use `@include` inside a loop, Laravel's view factory resolves the file, creates a new view instance, and compiles the template **on every iteration**. For a page rendering 100+ cards, this overhead adds up.

```blade
{{-- Each iteration pays the full view factory cost --}}
@foreach ($items as $item)
    @include('components.card')
@endforeach
```

## The solution

`@inline` compiles the partial once and embeds the resulting PHP directly into the parent view at compile time. At runtime, the loop body is just plain PHP — no view factory, no file lookups, no `ComponentAttributeBag`.

```blade
{{-- Compiled once, inlined as raw PHP --}}
@foreach ($items as $item)
    @inline('components.card')
@endforeach
```

## Benchmarks

Rendering an escort listing card across different loop sizes (Laravel 13, PHP 8.4):

| Cards | `@include` | `@inline` | Improvement |
|------:|-----------:|----------:|------------:|
| 13    | 1.61ms     | 1.51ms    | 6%          |
| 104   | 13.6ms     | 10.1ms    | **26%**     |
| 260   | 32.6ms     | 27.6ms    | **16%**     |

The improvement grows with template complexity and loop size.

## Installation

```bash
composer require degecko/laravel-blade-inline
```

The service provider is auto-discovered.

## Usage

### Basic usage

`@inline` works like `@include` but the partial shares the parent's variable scope:

```blade
@foreach ($products as $product)
    @inline('components.product-card')
@endforeach
```

The `$product` variable is available inside the partial automatically — no need to pass it explicitly.

### Passing variables

You can set local variables for the partial:

```blade
@foreach ($products as $product)
    @inline('components.product-card', [
        'highlight' => $loop->first,
        'lazy' => $loop->index > 6,
    ])
@endforeach
```

### How it works

1. At **compile time**, `@inline` reads the partial's Blade source
2. Strips any `@props` directive (unnecessary since variables come from the parent scope)
3. Compiles the Blade to PHP via `Blade::compileString()`
4. Embeds the compiled PHP directly into the parent view

The result: the partial's logic becomes part of the parent view's compiled PHP file. At runtime, there's zero overhead from view resolution.

## Important notes

- **Shared scope**: The partial accesses the same variables as the parent view. No isolated scope like `@include`.
- **No `$attributes`**: Since `@props` is stripped, the `$attributes` bag is not available. Use explicit variables instead.
- **Cache**: Changes to the partial require `php artisan view:clear` to take effect (same as any Blade change in production).
- **All Blade directives work**: `@if`, `@foreach`, `@php`/`@endphp`, `{{ }}`, etc. are all fully supported.

## When to use

Use `@inline` when:
- A partial is rendered many times in a loop (listing pages, tables, feeds)
- The partial doesn't need isolated variable scope
- You want to eliminate view factory overhead

Stick with `@include` when:
- The partial is rendered once or twice (no loop overhead to save)
- You need isolated variable scope
- You use `$attributes` or component features

## License

MIT
