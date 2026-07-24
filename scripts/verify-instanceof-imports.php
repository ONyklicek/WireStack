<?php

declare(strict_types=1);

/*
 * instanceof gate — finds an `instanceof Foo` whose Foo resolves to nothing.
 *
 * PHP does not fall back to the global namespace for *class* names, and
 * `instanceof` against a class that does not exist neither autoloads nor throws:
 * it quietly returns false. So a missing `use` turns a type check into a branch
 * that is never taken, with no error anywhere.
 *
 * One of these shipped into an unreleased version (ADR 0021). WithTable, in
 * namespace NyonCode\WireTable\Concerns, checked `$column instanceof
 * DehydratesState` without importing the contract; it resolved to
 * NyonCode\WireTable\Concerns\DehydratesState, which does not exist, so every
 * editable cell silently stopped trimming, casing, parsing numbers and running
 * beforeSave() — the raw client string went straight to the column. The code it
 * replaced used method_exists(), which has no namespace to get wrong.
 *
 * Nothing else could see it: PHPStan and Pint pass on the broken code, and the
 * suite was green because the tests asserted the column's method in isolation
 * rather than the transform happening through the host.
 *
 * Blade views are skipped: they compile to the global namespace, a different
 * resolution model, and carry their imports in @php blocks.
 *
 * Usage: php scripts/verify-instanceof-imports.php [repo-root]
 */

$repo = rtrim($argv[1] ?? getcwd(), '/');
require $repo.'/vendor/autoload.php';

/**
 * The `use` map for a file: alias => fully-qualified name.
 *
 * @return array<string, string>
 */
function importMap(string $src): array
{
    preg_match_all('/^use\s+(?!function\s|const\s)([^;]+);/m', $src, $matches);

    $imports = [];

    foreach ($matches[1] as $statement) {
        $statement = trim($statement);

        if (preg_match('/\s+as\s+/i', $statement)) {
            [$fqn, $alias] = preg_split('/\s+as\s+/i', $statement);
        } else {
            $fqn = $statement;
            $separator = strrpos($statement, '\\');
            // `use Closure;` has no separator — the alias is the whole name.
            $alias = $separator === false ? $statement : substr($statement, $separator + 1);
        }

        $imports[trim($alias)] = ltrim(trim($fqn), '\\');
    }

    return $imports;
}

/**
 * Every class name used as an `instanceof` operand, as [name, isFullyQualified].
 *
 * Tokenised rather than pattern-matched. A regex over the raw source also finds
 * `instanceof` inside comments and strings, and one comment explaining an
 * "explicit instanceof list" was enough to fail the gate against a name that was
 * never code — a false positive that makes the gate noise rather than a signal.
 * PHP's own lexer is the only thing that reliably knows which is which.
 *
 * A dynamic operand (`$x instanceof $class`) yields no name and is skipped:
 * there is nothing to resolve at read time.
 *
 * @return array<int, array{string, bool}>
 */
function instanceofTargets(string $src): array
{
    $tokens = token_get_all($src);
    $count = count($tokens);
    $targets = [];

    for ($i = 0; $i < $count; $i++) {
        if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_INSTANCEOF) {
            continue;
        }

        // Step over whitespace and comments to reach the operand itself.
        for ($j = $i + 1; $j < $count; $j++) {
            $token = $tokens[$j];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $targets[] = [ltrim($token[1], '\\'), $token[0] === T_NAME_FULLY_QUALIFIED];
            }

            // Anything else is a variable, a parenthesis or `static` — not a name.
            break;
        }
    }

    return $targets;
}

/** Resolve an instanceof operand the way PHP will at runtime. */
function resolveTarget(string $name, bool $fullyQualified, string $namespace, array $imports): string
{
    if ($fullyQualified) {
        return $name;
    }

    if (isset($imports[$name])) {
        return $imports[$name];
    }

    // A qualified name (Foo\Bar) may hang off an imported prefix.
    if (str_contains($name, '\\')) {
        $head = strstr($name, '\\', true);

        if (isset($imports[$head])) {
            return $imports[$head].strstr($name, '\\');
        }
    }

    // Unqualified: the current namespace. No global fallback for class names.
    return $namespace === '' ? $name : $namespace.'\\'.$name;
}

$failures = [];
$checked = 0;

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($repo.'/packages'));

foreach ($rii as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();

    if (str_contains($path, '/vendor/') || str_ends_with($path, '.blade.php')) {
        continue;
    }

    // Only production code: a test may name a class it defines inline.
    if (str_contains($path, '/tests/') || str_contains($path, '/workbench/')) {
        continue;
    }

    $src = file_get_contents($path);

    if ($src === false || ! str_contains($src, 'instanceof')) {
        continue;
    }

    preg_match('/^namespace\s+([^;]+);/m', $src, $nsMatch);
    $namespace = trim($nsMatch[1] ?? '');
    $imports = importMap($src);

    foreach (instanceofTargets($src) as [$name, $fullyQualified]) {
        if (in_array($name, ['self', 'static', 'parent'], true)) {
            continue;
        }

        $checked++;
        $resolved = resolveTarget($name, $fullyQualified, $namespace, $imports);

        if (class_exists($resolved) || interface_exists($resolved) || trait_exists($resolved)) {
            continue;
        }

        $relative = str_replace($repo.'/', '', $path);
        $failures[] = "  ✗ {$relative}\n      instanceof {$name}  →  {$resolved}  (does not exist — the check is always false)";
    }
}

if ($failures !== []) {
    echo 'instanceof gate FAILED — '.count($failures)." unresolvable target(s):\n\n";
    echo implode("\n", $failures)."\n\n";
    echo "Add the missing `use` import, or fully qualify the name.\n";

    exit(1);
}

echo "instanceof gate OK — {$checked} target(s) across packages/*/src all resolve.\n";

exit(0);
