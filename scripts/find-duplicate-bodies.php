<?php

declare(strict_types=1);

/**
 * Find identical method bodies across the packages' `src/`.
 *
 * A duplicate-abstraction sweep that does not depend on anyone reading well.
 * It tokenizes every source file, lifts each method body, drops whitespace and
 * comments, and groups the ones that come out identical. That is cheap enough to
 * run over the whole repo in seconds and repeatable enough to be worth trusting:
 * the 2026-09-03 run found ten groups this way, four of which were worth one
 * owner, and one of them — the asset route four providers each wrote out — had
 * already drifted between the copies.
 *
 *   php scripts/find-duplicate-bodies.php .              # exact bodies
 *   php scripts/find-duplicate-bodies.php . --normalize  # ignore names and literals
 *
 * `--normalize` replaces variables and string literals with placeholders, which
 * catches the copy somebody renamed on the way. It is noisier, so it runs at a
 * higher threshold.
 *
 * Its limit is worth knowing before trusting a clean run: it sees *identical*
 * bodies. The same rule written a second way — a `foreach` where the original
 * used `array_map`, a guard inlined into its caller — is invisible to it. A clean
 * sweep means nothing has been copy-pasted, not that nothing is duplicated.
 *
 * Reports only; it is not a CI gate, because a deliberate pair of surfaces
 * (`Foundation\Schema\Callout` and `Foundation\View\Callout`) is allowed to say
 * the same thing twice.
 */
$root = rtrim($argv[1] ?? '.', '/');
$normalize = in_array('--normalize', $argv, true);

$minStatements = $normalize ? 4 : 3;
$minLength = $normalize ? 220 : 60;

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/packages'));

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && str_contains($file->getPathname(), '/src/')) {
        $files[] = $file->getPathname();
    }
}

sort($files);

/** @var array<string, list<string>> $bodies */
$bodies = [];

foreach ($files as $path) {
    $tokens = token_get_all((string) file_get_contents($path));
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }

        $nameIndex = $i + 1;

        while ($nameIndex < $count && is_array($tokens[$nameIndex]) && $tokens[$nameIndex][0] === T_WHITESPACE) {
            $nameIndex++;
        }

        if (! is_array($tokens[$nameIndex]) || $tokens[$nameIndex][0] !== T_STRING) {
            continue; // a closure or an arrow function, not a named method
        }

        [$open, $close] = locateBody($tokens, $nameIndex, $count);

        if ($open === null || $close === null) {
            continue; // abstract or interface method: a signature with no body
        }

        [$body, $statements] = normalizeBody($tokens, $open, $close, $normalize);

        if ($statements >= $minStatements && strlen($body) >= $minLength) {
            $location = str_replace($root.'/packages/', '', $path).':'.$tokens[$nameIndex][2];
            $bodies[$body][] = $location.' '.$tokens[$nameIndex][1].'()';
        }

        $i = $close;
    }
}

$groups = [];

foreach ($bodies as $body => $locations) {
    $locations = array_values(array_unique($locations));

    if (count($locations) > 1) {
        $groups[] = [strlen($body), $locations, substr($body, 0, 140)];
    }
}

usort($groups, static fn (array $a, array $b): int => $b[0] <=> $a[0]);

foreach ($groups as [$length, $locations, $preview]) {
    printf("── %d chars, %d copies\n", $length, count($locations));

    foreach ($locations as $location) {
        echo "   {$location}\n";
    }

    echo "   | {$preview}\n\n";
}

printf("%d duplicate group(s) over %d file(s)%s.\n", count($groups), count($files), $normalize ? ', normalized' : '');

/**
 * The braces around one method's body, or nulls when it has none.
 *
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 * @return array{0: int|null, 1: int|null}
 */
function locateBody(array $tokens, int $from, int $count): array
{
    $open = null;

    for ($i = $from; $i < $count; $i++) {
        $token = is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];

        if ($token === '{') {
            $open = $i;

            break;
        }

        if ($token === ';') {
            return [null, null];
        }
    }

    if ($open === null) {
        return [null, null];
    }

    $depth = 0;

    for ($i = $open; $i < $count; $i++) {
        $token = $tokens[$i];
        $text = is_array($token) ? $token[1] : $token;

        // Interpolation opens a brace the scanner has to count too, or a body
        // holding "{$this->column}" closes one level early and swallows the rest.
        if ($text === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
            $depth++;
        } elseif ($text === '}') {
            $depth--;

            if ($depth === 0) {
                return [$open, $i];
            }
        }
    }

    return [$open, null];
}

/**
 * One body as a comparable string, plus how many statements it holds.
 *
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 * @return array{0: string, 1: int}
 */
function normalizeBody(array $tokens, int $open, int $close, bool $normalize): array
{
    $body = '';
    $statements = 0;

    for ($i = $open + 1; $i < $close; $i++) {
        $token = $tokens[$i];

        if (is_array($token)) {
            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $body .= match (true) {
                $normalize && $token[0] === T_VARIABLE => 'VAR',
                $normalize && $token[0] === T_CONSTANT_ENCAPSED_STRING => 'STR',
                default => $token[1],
            }.' ';

            continue;
        }

        $body .= $token.' ';

        if ($token === ';') {
            $statements++;
        }
    }

    return [trim($body), $statements];
}
