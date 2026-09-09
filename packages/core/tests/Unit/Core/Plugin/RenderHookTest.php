<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Plugin\RenderHook;

/**
 * The third dispatcher: `runHook` and `runTypedHook` steer what happens,
 * this one produces what is on the page.
 *
 * Registration is `PluginManager`'s, so priority and `for:` are not re-tested
 * here beyond proving they reach this path — what is new is the collecting.
 */
function renderHookHtml(string $name, array $scope = []): string
{
    return app(PluginManager::class)->runRenderHook($name, $scope);
}

it('renders nothing, and cheaply, for a position nobody uses', function () {
    // Every shipped position sits in markup that renders on every page. An
    // unused one has to be an empty string and an array lookup, or the feature
    // would cost something everywhere to be used somewhere.
    expect(renderHookHtml('admin.topbar.end'))->toBe('');
});

it('renders what a callback returns', function () {
    RenderHook::add('admin.topbar.end', fn (): HtmlString => new HtmlString('<span id="probe">hi</span>'));

    expect(renderHookHtml('admin.topbar.end'))->toBe('<span id="probe">hi</span>');
});

it('escapes a bare string rather than trusting it', function () {
    // Text goes in as text. Markup has to be a view or an explicit HtmlString,
    // so nobody injects a tag from a value they did not write.
    RenderHook::add('admin.topbar.end', fn (): string => '<script>alert(1)</script>');

    expect(renderHookHtml('admin.topbar.end'))
        ->not->toContain('<script>')
        ->toContain('&lt;script&gt;');
});

it('ignores a callback that decides it has nothing to add', function () {
    RenderHook::add('admin.topbar.end', fn () => null);
    RenderHook::add('admin.topbar.end', fn (): HtmlString => new HtmlString('kept'));

    expect(renderHookHtml('admin.topbar.end'))->toBe('kept');
});

it('concatenates in priority order, so two plugins keep a defined sequence', function () {
    RenderHook::add('admin.topbar.end', fn (): HtmlString => new HtmlString('second'), priority: 10);
    RenderHook::add('admin.topbar.end', fn (): HtmlString => new HtmlString('first'), priority: -10);

    expect(renderHookHtml('admin.topbar.end'))->toBe('firstsecond');
});

it('hands the position what it knows about itself', function () {
    RenderHook::add(
        'panels.page.header.end',
        fn (array $scope): HtmlString => new HtmlString('title='.$scope['title']),
    );

    expect(renderHookHtml('panels.page.header.end', ['title' => 'Invoices']))
        ->toBe('title=Invoices');
});

it('renders a view, which is the shape to use', function () {
    RenderHook::add('admin.sidebar.end', fn () => view('wire-core::partials.entry-label', [
        'text' => 'Beta',
    ]));

    expect(renderHookHtml('admin.sidebar.end'))->toContain('Beta');
});

it('is reachable from a view through the directive', function () {
    RenderHook::add('table.toolbar.end', fn (): HtmlString => new HtmlString('<b>x</b>'));

    expect(Blade::render("<div>@wireRenderHook('table.toolbar.end')</div>"))
        ->toBe('<div><b>x</b></div>');
});

it('names every position it ships, and ships every position it names', function () {
    // The list is what a reader looks at; the markup is what runs. A position
    // documented and not placed renders nothing for ever, and one placed and not
    // documented is a promise nobody knows about.
    foreach (array_keys(RenderHook::POSITIONS) as $position) {
        $placed = shell_exec(sprintf(
            'grep -rl %s %s --include="*.blade.php" | head -1',
            escapeshellarg("wireRenderHook('".$position."'"),
            escapeshellarg(dirname(__DIR__, 5)),
        ));

        expect(trim((string) $placed))->not->toBe('', "[{$position}] is documented and never placed.");
    }
});
