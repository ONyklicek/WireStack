<?php

declare(strict_types=1);
use NyonCode\WireCore\Foundation\Support\TextControl;

/**
 * iOS Safari zooms the page into any focused control whose text is under 16px,
 * and does not zoom back out. On a phone that reads as the form overflowing its
 * screen, and every field, filter and search box in the stack had `text-sm`.
 *
 * The fix is one vocabulary — `text-base sm:text-sm` (or `sm:text-xs`): 16px
 * below `sm`, unchanged from it up — and this test holds every shipped view to
 * it. A view that hands its controls {@see TextControl}
 * already complies; this is for the ones that spell their classes out.
 *
 * ## What counts
 *
 * A text-entry tag — `<input>` of a typing type, `<textarea>`, `<select>`, or an
 * element with `contenteditable` — whose own attributes carry a bare `text-sm`
 * or `text-xs` and no `text-base`. Font size inherited from an ancestor is out
 * of reach of a source scan; the preview sweep in a browser is what sees that.
 */

/**
 * @return list<array{0: int, 1: string}> offset and source of each text-entry tag
 */
function zoomTextControlTags(string $source): array
{
    $tags = [];

    preg_match_all('/<(?:input|textarea|select)\b|<[a-z][\w-]*\b(?=[^>]*contenteditable)/', $source, $matches, PREG_OFFSET_CAPTURE);

    foreach ($matches[0] as [$opening, $start]) {
        // Walk to the tag's own `>`, past any inside quotes or Blade brackets
        // (`@class([... => ...])`, `{{ $a > $b }}`).
        $depth = 0;
        $quote = null;
        $length = strlen($source);

        for ($i = $start + strlen($opening); $i < $length; $i++) {
            $char = $source[$i];

            if ($quote !== null) {
                $quote = $char === $quote ? null : $quote;
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif (str_contains('([{', $char)) {
                $depth++;
            } elseif (str_contains(')]}', $char)) {
                $depth--;
            } elseif ($char === '>' && $depth <= 0) {
                break;
            }
        }

        $tags[] = [$start, substr($source, $start, $i - $start + 1)];
    }

    return $tags;
}

it('keeps every shipped text control at 16px on a phone', function () {
    $nonTyping = ['checkbox', 'radio', 'range', 'hidden', 'file', 'color', 'submit', 'button', 'reset', 'image'];
    $offenders = [];

    $root = dirname(__DIR__, 2);
    $views = new RegexIterator(
        new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/packages', FilesystemIterator::SKIP_DOTS)),
        '#/packages/[^/]+/resources/views/.+\.blade\.php$#',
    );

    foreach ($views as $view) {
        $source = (string) file_get_contents($view->getPathname());

        foreach (zoomTextControlTags($source) as [$offset, $tag]) {
            if (preg_match('/\btype="([^"]+)"/', $tag, $type) && in_array($type[1], $nonTyping, true)) {
                continue;
            }

            if (preg_match('/(?<![\w:-])text-(?:xs|sm)\b/', $tag) && ! preg_match('/(?<![\w:-])text-base\b|max-sm:text-base/', $tag)) {
                $offenders[] = substr($view->getPathname(), strlen($root) + 1).':'.(substr_count($source, "\n", 0, $offset) + 1);
            }
        }
    }

    expect($offenders)->toBe([], "Under 16px on a phone, so iOS zooms on focus — use `text-base sm:text-sm`:\n".implode("\n", $offenders));
});

it('reads a tag through Blade brackets to its own closing bracket', function () {
    $tags = zoomTextControlTags('<input @class([\'text-sm\' => $a > 1]) type="text"><span>');

    expect($tags)->toHaveCount(1)
        ->and($tags[0][1])->toEndWith('type="text">');
});
