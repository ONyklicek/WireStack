<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/**
 * A misspelt Blade directive is not an error — it is text. `@elseunless(...)`
 * (no such directive) compiled to a literal string in the rendered output while
 * its `@endunless` closed the preceding `@if`, so the action modal's submit
 * button silently moved into the wrong branch and every test stayed green.
 *
 * Compiling every shipped view and looking for directive-shaped tokens that
 * survived into the *output* catches that whole class of typo at once, for
 * every package, without anyone having to think of the case first.
 *
 * Only `resources/views/` is scanned: those are the views the packages render.
 * wire-boost's `resources/boost/` files are LLM guideline documents that quote
 * literal Blade on purpose, so they are deliberately out of scope.
 *
 * CSS at-rules (`@media (...)`) legitimately reach the browser and are the one
 * directive-shaped token that belongs in the output.
 */
const ALLOWED_AT_RULES = [
    'media', 'supports', 'container', 'keyframes', 'layer', 'apply',
    'tailwind', 'import', 'charset', 'font-face', 'page', 'property',
];

/**
 * Every Blade view the packages ship and render.
 *
 * @return list<string>
 */
function shippedBladeViews(): array
{
    $found = [];

    foreach (glob(dirname(__DIR__, 2).'/packages/*/resources/views') ?: [] as $viewRoot) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($viewRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $found[] = $file->getPathname();
            }
        }
    }

    sort($found);

    return $found;
}

/**
 * Directive-shaped tokens left in a compiled view's markup.
 *
 * The compiled PHP segments are dropped first: a directive typo always lands in
 * the emitted markup, never inside PHP, so everything `<?php … ?>` holds is
 * legitimate code — `@filemtime(...)` error suppression, a `//` comment quoting
 * `@if($a && $b->c())`, and so on.
 *
 * @return list<string>
 */
function survivingDirectives(string $compiled): array
{
    $markup = preg_replace('/<\?php.*?\?>/s', '', $compiled) ?? '';

    preg_match_all('/@([a-zA-Z][\w-]*)\s*\(/', $markup, $matches, PREG_SET_ORDER);

    $surviving = [];

    foreach ($matches as [$token, $name]) {
        if (! in_array(strtolower($name), ALLOWED_AT_RULES, true)) {
            $surviving[] = $token;
        }
    }

    return $surviving;
}

it('compiles every shipped Blade view without leaving a directive in the markup', function () {
    $views = shippedBladeViews();

    // Guard the guard: a broken glob would make this pass vacuously.
    expect($views)->toHaveCount(count($views))
        ->and(count($views))->toBeGreaterThan(100);

    $offenders = [];

    foreach ($views as $view) {
        $surviving = survivingDirectives(Blade::compileString((string) file_get_contents($view)));

        foreach ($surviving as $token) {
            $offenders[] = str_replace(dirname(__DIR__, 2).'/', '', $view).': '.$token;
        }
    }

    // A hit here is almost always a typo — `@elseunless`, `@endforeach` spelt
    // `@endforach`, a directive a package forgot to register. The name in the
    // message is the one Blade did not recognise.
    expect($offenders)->toBe([]);
});

it('detects a directive typo that Blade itself accepts silently', function () {
    // The exact 1.17.2 regression, in miniature: `@elseunless` passes straight
    // through as text and `@endunless` closes the `@if`, so the second button
    // renders inside the first branch instead of opposite it.
    $typo = <<<'BLADE'
        @if($isWizard)
            <button data-testid="next">Next</button>
        @elseunless($hasInfolist)
            <button data-testid="submit">Save</button>
        @endunless
        BLADE;

    expect(survivingDirectives(Blade::compileString($typo)))->toBe(['@elseunless(']);

    // The same view spelt correctly leaves nothing behind.
    $correct = str_replace(
        ['@elseunless($hasInfolist)', '@endunless'],
        ['@elseif(! $hasInfolist)', '@endif'],
        $typo,
    );

    expect(survivingDirectives(Blade::compileString($correct)))->toBe([]);
});

it('keeps CSS at-rules and PHP error suppression out of the results', function () {
    $view = <<<'BLADE'
        @php $stamp = @filemtime(__FILE__) ?: null; @endphp
        <style>@media (min-width: 640px) { .x { color: red } }</style>
        BLADE;

    expect(survivingDirectives(Blade::compileString($view)))->toBe([]);
});
