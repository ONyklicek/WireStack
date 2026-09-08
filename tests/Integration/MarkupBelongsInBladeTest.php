<?php

declare(strict_types=1);
use NyonCode\WireCore\Foundation\View\ComponentRenderer;

/**
 * The one rule in AI_CODING_STANDARD.md § Rendering that had nothing enforcing it.
 *
 * "Always Htmlable, always Blade — no exceptions. Markup lives in a `.blade.php`
 * template, and PHP produces it only through an Htmlable owner. Raw HTML
 * concatenated from PHP strings is never acceptable — not for speed, not for a
 * single tag, not when the output is byte-identical and the suite is green."
 *
 * It was violated in eight places, and every one of them shipped green: a
 * concatenated `<a>` in the row renderer, three in the mobile card, the star row,
 * the stacked lines, the whole sortable toolbar button, the four `Html::` display
 * factories, and two Blade files that built their markup in a `@php` preamble —
 * which is the same violation wearing a `.blade.php` extension.
 *
 * Nobody was ignoring the rule. There was simply nothing to notice the drift, so
 * this is that thing. It reads the source rather than the output, deliberately:
 * the whole point of the rule is that the *output* is identical either way.
 *
 * ## What counts
 *
 * An opening or closing HTML tag inside a PHP string literal, in a package's
 * `src/` or inside a `@php` block in a shipped view. Not: a tag inside a comment,
 * a selector or a matcher (`str_contains($html, '<svg')`), or icon body data,
 * which is the icon sets' own format and reaches the page through IconManager.
 *
 * The exception list may shrink and may not grow silently — same ratchet as
 * `ModuleLayersTest`, and for the same reason.
 */

/**
 * Files allowed to hold markup in PHP, and why.
 *
 * @return array<string, string>
 */
function markupExceptions(): array
{
    return [
        // The icon pipeline's own two ends. The Icons section of the standard
        // makes this explicit — "PHP produces the markup, Blade only consumes
        // it" — because an icon is data plus one wrapper, and a Blade template
        // per icon would be a view render per icon.
        'packages/core/src/Foundation/Icons/ResolvedIcon.php' => 'builds the one <svg> wrapper every icon is drawn in',
        'packages/core/src/Foundation/Icons/IconManager.php' => 'the fallback icon body, for a name no set answers',

        // Not markup being authored: string surgery over markup a partial
        // already rendered, to address each subtotal row on its own.
        'packages/table/src/Support/SummaryRenderer.php' => 'splits a rendered partial back into its rows on the anchor that opens them',

        // Neither is this: a wrapper for DOMDocument to parse against, which
        // never reaches a page.
        'packages/core/src/Foundation/Mentions/MentionRenderer.php' => 'wraps the input so DOMDocument has a root to parse',

        // The row's closing tag. Everything that carries information about a
        // `<tr>` — its classes, its bindings, its keys — is in
        // `tables.partials.body-row-open`, which is the override point; `</tr>`
        // holds nothing to override and nothing to get wrong, and the row's
        // children are assembled between the two.
        'packages/table/src/Support/RowRenderer.php' => 'closes the <tr> its partial opened',
    ];
}

/**
 * Every PHP file a package ships, plus the `@php` regions of every shipped view.
 *
 * @return array<string, string> relative path => the PHP source to scan
 */
function markupScannableSources(): array
{
    $root = dirname(__DIR__, 2);
    $sources = [];

    foreach (glob($root.'/packages/*/src') ?: [] as $src) {
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)
        ) as $file) {
            if ($file->getExtension() === 'php') {
                $sources[str_replace($root.'/', '', $file->getPathname())] = (string) file_get_contents($file->getPathname());
            }
        }
    }

    foreach (glob($root.'/packages/*/resources/views') ?: [] as $views) {
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($views, FilesystemIterator::SKIP_DOTS)
        ) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace($root.'/', '', $file->getPathname());
            $php = markupPhpRegionsOf((string) file_get_contents($file->getPathname()));

            if ($php !== '') {
                $sources[$relative] = $php;
            }
        }
    }

    ksort($sources);

    return $sources;
}

/**
 * The `@php … @endphp` regions of a view — the only part of a template where PHP
 * can author markup. Blade comments go first: one holding the word `@php` is
 * enough to make the compiler pair it with the real `@endphp`, which is its own
 * kind of trap and not something to scan through.
 */
function markupPhpRegionsOf(string $blade): string
{
    $blade = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $blade);

    preg_match_all('/@php\b(?!\s*\()(.*?)@endphp/s', $blade, $matches);

    return implode("\n", $matches[1]);
}

/**
 * HTML tags authored inside PHP string literals.
 *
 * Tokenised rather than grepped, so a tag inside a comment or a docblock is not
 * a finding and a tag inside a heredoc is.
 *
 * @return array<int, string> the offending literals, trimmed for the message
 */
function markupLiteralsIn(string $php): array
{
    $tokens = @token_get_all(str_starts_with(ltrim($php), '<?php') ? $php : '<?php '.$php);
    $found = [];

    foreach ($tokens as $token) {
        if (! is_array($token)) {
            continue;
        }

        if (! in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML], true)) {
            continue;
        }

        // An opening tag with an attribute or an immediate close, or any closing
        // tag. `<span>`, `<td class=`, `</a>` — but not `a < b`, `=>`, or a
        // regex's named capture group, which is `<name>` by another spelling.
        if (preg_match('#</[a-zA-Z][a-zA-Z0-9]*>|(?<!\?)(?<!\?P)<[a-zA-Z][a-zA-Z0-9]*(\s+[a-zA-Z:@!.-]+=|\s*/?>)#', $token[1]) !== 1) {
            continue;
        }

        $found[] = trim(preg_replace('/\s+/', ' ', $token[1]) ?? '');
    }

    return $found;
}

it('lets no package author HTML in PHP', function () {
    $exceptions = markupExceptions();
    $offenders = [];

    foreach (markupScannableSources() as $path => $php) {
        if (isset($exceptions[$path])) {
            continue;
        }

        $literals = markupLiteralsIn($php);

        if ($literals !== []) {
            $offenders[] = $path.' → '.mb_substr($literals[0], 0, 80);
        }
    }

    expect($offenders)->toBe([], <<<'TXT'
        HTML is being authored in PHP.

        The markup belongs in a `.blade.php` template, and PHP may produce it only
        through an Htmlable owner — `Foundation\View\Skeleton` (compile the
        partial once, fill per record), a component's own toHtml(), or an
        HtmlString over a rendered view. This holds inside a view's own `@php`
        block too: a template that assembles its markup as a string has moved it
        out of Blade just as surely as a class would have.

        Not for speed: the skeleton splice is what makes the Blade version as
        cheap as the concatenation, and it is the documented answer for a per-row
        surface. Not for one tag either.

        If the file genuinely owns an exception — the icon pipeline is the only
        real one — add it to markupExceptions() with the reason, deliberately, in
        review.
        TXT);
});

it('keeps no exception the code has outgrown', function () {
    $stale = [];

    foreach (markupExceptions() as $path => $reason) {
        $file = dirname(__DIR__, 2).'/'.$path;

        if (! is_file($file)) {
            $stale[] = $path.' (gone)';

            continue;
        }

        if (markupLiteralsIn((string) file_get_contents($file)) === []) {
            $stale[] = $path.' ('.$reason.')';
        }
    }

    expect($stale)->toBe([], <<<'TXT'
        An entry in markupExceptions() no longer authors any markup in PHP.

        Good news — drop it. The list is a ratchet: it may shrink and may not
        grow.
        TXT);
});

/**
 * Views allowed to write an `<svg>` of their own, and why.
 *
 * @return array<string, string>
 */
function inlineSvgExceptions(): array
{
    return [
        // The icon pipeline's Blade end: the root every resolved icon is drawn
        // in, for the consumer-facing <x-wire::icon>.
        'packages/core/resources/views/foundation/icon.blade.php' => 'the icon component\'s own root',

        // An animated two-part spinner, not a glyph from a set. Its canonical
        // owner is Foundation\View\Primitives, which renders this once per
        // request and echoes the string.
        'packages/core/resources/views/partials/spinner.blade.php' => 'the canonical loading spinner',

        // Plots, not icons: a <polyline> over points the column computed. There
        // is no icon-set answer to "draw this data".
        'packages/core/resources/views/widgets/stats-overview.blade.php' => 'the stat sparkline',
        'packages/table/resources/views/tables/columns/metric.blade.php' => 'the metric column sparkline',
    ];
}

it('lets no view hand-write an svg the icon owner should have drawn', function () {
    // The other half of the Icons rule: "Core render paths MUST NOT use a
    // hand-written inline <svg> — it breaks theming and the icon-set
    // abstraction." Five templates did, the editors' quote mark three times over
    // in three different files, and every one of them rendered.
    $root = dirname(__DIR__, 2);
    $exceptions = inlineSvgExceptions();
    $offenders = [];

    foreach (glob($root.'/packages/*/resources/views') ?: [] as $views) {
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($views, FilesystemIterator::SKIP_DOTS)
        ) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace($root.'/', '', $file->getPathname());

            if (isset($exceptions[$relative])) {
                continue;
            }

            // Comments talk about `<svg>` on purpose — that is where the rule is
            // explained — so they come out before the file is judged.
            $blade = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($file->getPathname()));

            if (str_contains($blade, '<svg')) {
                $offenders[] = $relative;
            }
        }
    }

    sort($offenders);

    expect($offenders)->toBe([], <<<'TXT'
        A view is hand-writing an <svg>.

        Icons come from the canonical owner: `{!! icon('name', 'w-4 h-4') !!}`.
        A binding that must sit on the <svg> root (x-show, ::class, wire:*) is
        passed as the $attributes argument rather than being a reason to inline
        one.

        If the glyph is not in Heroicons, register a set — wire-forms ships
        `Support\Icons\FormsIconSet` for exactly that, and wire-sortable
        registers its grip from a .svg file. If it is a plot rather than an icon,
        add it to inlineSvgExceptions() with the reason.
        TXT);
});

/**
 * The packages whose views are the render engine, and must draw without a tag.
 *
 * The line is not "framework versus app" — it is *engine versus screen*.
 *
 * `wire-core`, `wire-forms`, `wire-table`, `wire-panels` and `wire-sortable` are
 * what renders a table, a form and a resource page: their partials run inside a
 * row loop, are compiled into skeletons, and must render with `<x-*>` disabled
 * (Rendering rule 5). A Blade component there is a view render the engine cannot
 * account for and a dependency on a tag registration it should not need — the
 * three that existed are objects now, through
 * {@see ComponentRenderer}.
 *
 * `wire-admin` and the `wire-module-*` packages are the other kind: ready-made
 * *screens*, published for a consumer to open and edit. `<x-wire::button>` in a
 * profile card is the component tags being used as what they are — the
 * consumer-facing API, on the surface consumers actually touch. Rewriting those
 * as render calls would make the public surface unreadable to buy nothing: they
 * are page-level views, rendered once, not per row.
 *
 * @return array<int, string>
 */
function renderEnginePackages(): array
{
    return ['core', 'forms', 'table', 'panels', 'sortable'];
}

it('keeps the component tags out of the render engine, and only there', function () {
    $root = dirname(__DIR__, 2);
    $offenders = [];

    foreach (renderEnginePackages() as $package) {
        $views = $root.'/packages/'.$package.'/resources/views';

        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($views, FilesystemIterator::SKIP_DOTS)
        ) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $blade = (string) file_get_contents($file->getPathname());

            // Both kinds of comment come out first: these partials explain at
            // length which tag they are deliberately NOT using, and a rule that
            // punished them for saying so would be a rule against the comment.
            $blade = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $blade);
            $blade = (string) preg_replace('/@php\b(?!\s*\().*?@endphp/s', '', $blade);

            if (preg_match('/<x-wire[a-z-]*::/', $blade) === 1) {
                $offenders[] = str_replace($root.'/', '', $file->getPathname());
            }
        }
    }

    sort($offenders);

    expect($offenders)->toBe([], <<<'TXT'
        A render-engine view is drawing through a `<x-wire::*>` tag.

        The component tags are the consumer-facing API. The engine renders the
        same class as an object instead:

            {!! ComponentRenderer::render(new Button(size: 'md'), __('Save'), ['class' => 'w-full']) !!}

        Same class, same single view, no dependency on the tag being registered
        and no view render the engine cannot account for. Chrome that repeats per
        row wants a Skeleton rather than either.

        The screen packages (wire-admin, wire-module-*) are deliberately not
        checked: those views are published for a consumer to edit, which is where
        a tag is the right API.
        TXT);
});
