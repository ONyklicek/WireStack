<?php

declare(strict_types=1);

/*
 * Boost docs corpus sync — mirrors the English Markdown docs into wire-boost.
 *
 * The `search-wire-docs` / `fetch-wire-doc` MCP tools have to answer questions
 * inside a *host application*, which only ever installs `nyoncode/wire-boost`
 * from Packagist. Repo-root `docs/` is not part of that package, so the corpus
 * is mirrored into `packages/boost/resources/boost/docs/` and committed — the
 * same "ship the built artifact inside the package" trade the precompiled
 * wire-forms field JS makes.
 *
 * Czech docs (`docs/cs/`) are deliberately excluded: the fluent API is English,
 * so a bilingual index would only ever return duplicate hits at double the
 * package size. Assets (previews/screenshots) are excluded for the same reason —
 * an agent cannot read a PNG.
 *
 * Usage:
 *   php scripts/sync-boost-docs.php [repo-root]           # write the mirror
 *   php scripts/sync-boost-docs.php [repo-root] --check   # fail on drift (CI)
 */

$args = array_values(array_filter(
    array_slice($argv, 1),
    static fn (string $arg): bool => $arg !== '--check',
));

$check = in_array('--check', $argv, true);
$repo = rtrim($args[0] ?? getcwd(), '/');

$source = $repo.'/docs';
$target = $repo.'/packages/boost/resources/boost/docs';

if (! is_dir($source)) {
    fwrite(STDERR, "sync-boost-docs FAILED — no docs directory at {$source}.\n");
    exit(1);
}

/**
 * Every English Markdown doc, keyed by its path relative to `docs/`.
 *
 * @return array<string, string>
 */
function corpus(string $source): array
{
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || ! $file->isFile()) {
            continue;
        }

        if ($file->getExtension() !== 'md') {
            continue;
        }

        $relative = ltrim(str_replace($source, '', $file->getPathname()), '/');

        // Czech mirror and binary assets carry no value for an English-speaking
        // agent reading an English fluent API.
        if (str_starts_with($relative, 'cs/') || str_starts_with($relative, 'assets/')) {
            continue;
        }

        $files[$relative] = (string) file_get_contents($file->getPathname());
    }

    ksort($files);

    return $files;
}

/**
 * The mirror as it exists on disk right now.
 *
 * @return array<string, string>
 */
function mirror(string $target): array
{
    if (! is_dir($target)) {
        return [];
    }

    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'md') {
            continue;
        }

        $relative = ltrim(str_replace($target, '', $file->getPathname()), '/');
        $files[$relative] = (string) file_get_contents($file->getPathname());
    }

    ksort($files);

    return $files;
}

$expected = corpus($source);
$actual = mirror($target);

if ($expected === []) {
    fwrite(STDERR, "sync-boost-docs FAILED — docs/ contains no English Markdown.\n");
    exit(1);
}

$added = array_diff_key($expected, $actual);
$removed = array_diff_key($actual, $expected);
$changed = [];

foreach (array_intersect_key($expected, $actual) as $relative => $contents) {
    if ($contents !== $actual[$relative]) {
        $changed[$relative] = $contents;
    }
}

$drift = count($added) + count($removed) + count($changed);

if ($check) {
    if ($drift === 0) {
        echo 'sync-boost-docs OK — '.count($expected)." docs mirrored into wire-boost.\n";
        exit(0);
    }

    fwrite(STDERR, "sync-boost-docs FAILED — the bundled corpus has drifted from docs/:\n");

    foreach (array_keys($added) as $relative) {
        fwrite(STDERR, "  + {$relative} (missing from the bundle)\n");
    }

    foreach (array_keys($removed) as $relative) {
        fwrite(STDERR, "  - {$relative} (stale, no longer in docs/)\n");
    }

    foreach (array_keys($changed) as $relative) {
        fwrite(STDERR, "  ~ {$relative} (out of date)\n");
    }

    fwrite(STDERR, "\nRun: composer boost:sync-docs\n");
    exit(1);
}

foreach (array_keys($removed) as $relative) {
    @unlink($target.'/'.$relative);
}

foreach ($expected as $relative => $contents) {
    $destination = $target.'/'.$relative;
    $directory = dirname($destination);

    if (! is_dir($directory) && ! @mkdir($directory, 0o755, true) && ! is_dir($directory)) {
        fwrite(STDERR, "sync-boost-docs FAILED — could not create {$directory}.\n");
        exit(1);
    }

    file_put_contents($destination, $contents);
}

// Prune directories the removals emptied, so a deleted docs subtree does not
// leave a husk behind in the package.
$directories = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST,
);

foreach ($directories as $directory) {
    if ($directory instanceof SplFileInfo && $directory->isDir()) {
        @rmdir($directory->getPathname());
    }
}

echo 'sync-boost-docs OK — '.count($expected).' docs mirrored into wire-boost';
echo $drift === 0
    ? " (already up to date).\n"
    : ' ('.count($added).' added, '.count($changed).' updated, '.count($removed)." removed).\n";
