<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * A floating panel closes on a right click outside it, not only a left one.
 *
 * Every panel in the stack closes on `@click.outside`, and a right click fires
 * no `click` — only `contextmenu`. So a SelectColumn's list stayed open while a
 * right click opened the row's context menu beside it. The fix is a twin on the
 * same element: `@contextmenu.outside` with the same expression, so both guards
 * (`$clickedInside()`, the trigger check, the touch-sheet check) hold for both.
 *
 * Pest sees the attribute; `workbench/scripts/verify-contextmenu-outside.mjs` is
 * what sees Alpine act on it.
 *
 * ## Not panels
 *
 * - `forms/…/tags.blade.php` — `focused`, a focus ring on the input, not a
 *   floating panel.
 * - `table/…/cell-filter-menu.blade.php` — opened by a right click itself, and
 *   handles `@contextmenu.window` on its own.
 */
const RIGHT_CLICK_EXEMPT = [
    'forms/resources/views/components/tags.blade.php',
    'table/resources/views/tables/partials/cell-filter-menu.blade.php',
];

it('gives every click-outside close a right-click twin with the same expression', function () {
    $packages = dirname(__DIR__, 2).'/packages';
    $missing = [];
    $scanned = 0;

    foreach ((new Finder)->files()->in($packages)->path('#/resources/views/#')->name('*.blade.php') as $file) {
        $relative = str_replace('\\', '/', $file->getRelativePathname());

        if (in_array($relative, RIGHT_CLICK_EXEMPT, true)) {
            continue;
        }

        $source = $file->getContents();

        preg_match_all('/(?:@|x-on:)click\.outside="([^"]*)"/', $source, $matches);

        foreach ($matches[1] as $expression) {
            $scanned++;

            $twin = '/(?:@|x-on:)contextmenu\.outside="'.preg_quote($expression, '/').'"/';

            if (preg_match($twin, $source) !== 1) {
                $missing[] = $relative.': '.$expression;
            }
        }
    }

    expect($scanned)->toBeGreaterThan(10)
        ->and($missing)->toBe([]);
});
