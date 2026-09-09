<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Support\Docs;

use Symfony\Component\Finder\Finder;

/**
 * The knowledge the boost tools can answer from, addressed by stable ids.
 *
 * Documents are identified as "<root>/<relative path>" — "docs/table/columns/index.md",
 * "guidelines/wire-table.blade.php" — never by absolute path. An absolute path is
 * useless to the caller: it points inside the host app's `vendor/`, which the
 * agent has no reason to read and every reason not to.
 */
class DocsCorpus
{
    /**
     * Documentation subtree => the wire package that owns it.
     */
    private const PACKAGE_BY_PREFIX = [
        'table' => 'wire-table',
        'forms' => 'wire-forms',
        'core' => 'wire-core',
        'sortable' => 'wire-sortable',
        'boost' => 'wire-boost',
    ];

    /**
     * @param  array<string, string>  $roots  Root label => absolute directory.
     */
    public function __construct(private array $roots) {}

    /**
     * The corpus as shipped: the bundled English docs mirror, the curated
     * guidelines and skills, plus any extra Markdown the host app configures.
     */
    public static function default(): self
    {
        $resources = dirname(__DIR__, 3).'/resources/boost';

        $roots = [
            'docs' => $resources.'/docs',
            'guidelines' => $resources.'/guidelines',
            'skills' => $resources.'/skills',
        ];

        foreach ((array) config('wire-boost.docs.paths', []) as $path) {
            $path = rtrim(trim((string) $path), '/');

            if ($path === '') {
                continue;
            }

            $roots[self::label(basename($path), $roots)] = $path;
        }

        return new self($roots);
    }

    /**
     * @return array<string, string>
     */
    public function roots(): array
    {
        return $this->roots;
    }

    /**
     * Every indexed document, keyed by id.
     *
     * @return array<string, string>
     */
    public function documents(): array
    {
        $documents = [];

        foreach ($this->roots as $label => $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $files = Finder::create()
                ->files()
                ->in($directory)
                ->name(['*.md', '*.blade.php'])
                ->sortByName();

            foreach ($files as $file) {
                $relative = str_replace('\\', '/', $file->getRelativePathname());
                $documents[$label.'/'.$relative] = (string) file_get_contents($file->getPathname());
            }
        }

        return $documents;
    }

    /**
     * Read one document by id, or null when it is not part of the corpus.
     */
    public function read(string $id): ?string
    {
        $path = $this->resolve($id);

        return $path === null ? null : (string) file_get_contents($path);
    }

    /**
     * Map an id back to a readable absolute path, refusing anything that escapes
     * its root — an id is caller-supplied, so "docs/../../../.env" must not read.
     */
    public function resolve(string $id): ?string
    {
        $id = str_replace('\\', '/', trim($id));
        [$label, $relative] = array_pad(explode('/', $id, 2), 2, null);

        if ($label === null || $relative === null || ! isset($this->roots[$label])) {
            return null;
        }

        $root = realpath($this->roots[$label]);
        $path = realpath($this->roots[$label].'/'.$relative);

        if ($root === false || $path === false) {
            return null;
        }

        return str_starts_with($path, $root.DIRECTORY_SEPARATOR) && is_file($path) ? $path : null;
    }

    /**
     * The wire package a document belongs to, inferred from its id.
     *
     * Path matching used to be a bare `str_contains($path, $package)`, so a
     * filter of "wire-table" silently excluded every document under `docs/table/`
     * — the bulk of the table documentation.
     */
    public function packageFor(string $id): string
    {
        [$label, $relative] = array_pad(explode('/', $id, 2), 2, '');

        if ($label === 'docs') {
            $prefix = explode('/', (string) $relative)[0];

            return self::PACKAGE_BY_PREFIX[$prefix] ?? 'wire';
        }

        foreach (self::PACKAGE_BY_PREFIX as $package) {
            if (str_contains((string) $relative, $package)) {
                return $package;
            }
        }

        return 'wire';
    }

    /**
     * Normalise a caller's package filter: "table" and "wire-table" mean the same.
     */
    public function normalisePackage(string $package): string
    {
        $package = strtolower(trim($package));

        return str_starts_with($package, 'wire-') ? $package : 'wire-'.$package;
    }

    /**
     * A unique root label for a configured directory.
     *
     * @param  array<string, string>  $taken
     */
    private static function label(string $basename, array $taken): string
    {
        $label = $basename === '' ? 'project' : $basename;

        if (! isset($taken[$label])) {
            return $label;
        }

        $suffix = 2;

        while (isset($taken[$label.'-'.$suffix])) {
            $suffix++;
        }

        return $label.'-'.$suffix;
    }
}
