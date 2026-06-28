<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Support;

use Symfony\Component\Finder\Finder;

/**
 * A lightweight, embedding-free search index over the wireStack knowledge
 * corpus (the shipped guideline/skill files plus any extra Markdown directories
 * configured by the host application). Documents are scored by term frequency so
 * the "search-wire-docs" tool can answer questions without a hosted service.
 */
class DocsIndex
{
    /**
     * @param  array<int, string>  $paths
     */
    public function __construct(private array $paths) {}

    public static function default(): self
    {
        $resources = dirname(__DIR__, 2).'/resources/boost';

        $paths = array_merge(
            [$resources.'/guidelines', $resources.'/skills'],
            array_map('strval', (array) config('wire-boost.docs.paths', [])),
        );

        return new self($paths);
    }

    /**
     * @return array<int, string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * Map of document path => raw contents across every indexed directory.
     *
     * @return array<string, string>
     */
    public function documents(): array
    {
        $directories = array_values(array_filter($this->paths, 'is_dir'));

        if ($directories === []) {
            return [];
        }

        $documents = [];

        foreach (Finder::create()->files()->in($directories)->name(['*.md', '*.blade.php'])->sortByName() as $file) {
            $documents[$file->getRealPath() ?: $file->getPathname()] = (string) file_get_contents($file->getPathname());
        }

        return $documents;
    }

    /**
     * Rank documents against a free-text query.
     *
     * @return array<int, array{path: string, title: string, score: int, snippet: string}>
     */
    public function search(string $query, ?string $package = null, int $limit = 5): array
    {
        $terms = $this->tokenize($query);

        if ($terms === []) {
            return [];
        }

        $results = [];

        foreach ($this->documents() as $path => $contents) {
            if ($package !== null && ! str_contains($path, $package)) {
                continue;
            }

            $haystack = strtolower($contents);
            $score = 0;

            foreach ($terms as $term) {
                $score += substr_count($haystack, $term);
            }

            if ($score === 0) {
                continue;
            }

            $results[] = [
                'path' => $path,
                'title' => $this->title($contents, $path),
                'score' => $score,
                'snippet' => $this->snippet($contents, $terms),
            ];
        }

        usort($results, static fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?: strcmp($a['path'], $b['path']));

        return array_slice($results, 0, max(1, $limit));
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $query): array
    {
        preg_match_all('/[a-z0-9]+/i', strtolower($query), $matches);

        return array_values(array_unique(array_filter(
            $matches[0],
            static fn (string $term): bool => strlen($term) > 1,
        )));
    }

    private function title(string $contents, string $path): string
    {
        if (preg_match('/^#\s+(.+)$/m', $contents, $matches) === 1) {
            return trim($matches[1]);
        }

        if (preg_match('/^name:\s*(.+)$/m', $contents, $matches) === 1) {
            return trim($matches[1]);
        }

        return basename($path);
    }

    /**
     * @param  array<int, string>  $terms
     */
    private function snippet(string $contents, array $terms): string
    {
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $lower = strtolower($line);

            foreach ($terms as $term) {
                if (str_contains($lower, $term)) {
                    return mb_strlen($line) > 200 ? mb_substr($line, 0, 197).'...' : $line;
                }
            }
        }

        // Only called for scored documents, which always contain a term.
        // @codeCoverageIgnoreStart
        return '';
        // @codeCoverageIgnoreEnd
    }
}
