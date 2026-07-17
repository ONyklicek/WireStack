<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Support\Docs;

/**
 * Splits a Markdown document into addressable {@see DocSection}s.
 *
 * Headings are only recognised outside fenced code blocks. This corpus is dense
 * with shell and PHP samples, so a naive line scan would read `# Install the
 * package` inside a ```bash fence as a real heading and shatter the document
 * into fictional sections.
 */
class DocParser
{
    /**
     * Split a document into sections.
     *
     * @param  string  $document  Document id, e.g. "docs/table/columns/index.md".
     * @return array<int, DocSection>
     */
    public function parse(string $document, string $contents, string $package): array
    {
        $body = $this->stripFrontmatter($contents);
        $lines = preg_split('/\R/', $body) ?: [];

        $title = $this->title($lines, $document);

        $sections = [];
        $anchors = [];
        $trail = [];

        // The open section: heading metadata plus the body lines gathered so far.
        $heading = '';
        $level = 0;
        $buffer = [];
        $breadcrumb = [$title];

        $fence = null;

        foreach ($lines as $line) {
            if (($marker = $this->fenceMarker($line)) !== null) {
                // An opening fence latches until the matching marker closes it,
                // so headings inside the sample are invisible to the scan.
                $fence = $fence === null ? $marker : ($fence === $marker ? null : $fence);
                $buffer[] = $line;

                continue;
            }

            if ($fence !== null || preg_match('/^(#{1,6})\s+(.+?)\s*$/', $line, $matches) !== 1) {
                $buffer[] = $line;

                continue;
            }

            $sections[] = $this->section(
                $document, $title, $heading, $level, $breadcrumb, $buffer, $package, $anchors,
            );

            $level = strlen($matches[1]);
            $heading = $this->plainText($matches[2]);
            $buffer = [];

            $trail = $this->descend($trail, $level, $heading);
            $breadcrumb = array_values(array_unique(array_merge([$title], $trail)));
        }

        $sections[] = $this->section(
            $document, $title, $heading, $level, $breadcrumb, $buffer, $package, $anchors,
        );

        return array_values(array_filter(
            $sections,
            static fn (?DocSection $section): bool => $section instanceof DocSection,
        ));
    }

    /**
     * Build a section, or null when there is nothing worth indexing (an empty
     * preamble before the first heading, or a heading with no body of its own).
     *
     * @param  array<int, string>  $breadcrumb
     * @param  array<int, string>  $buffer
     * @param  array<string, int>  $anchors
     */
    private function section(
        string $document,
        string $title,
        string $heading,
        int $level,
        array $breadcrumb,
        array $buffer,
        string $package,
        array &$anchors,
    ): ?DocSection {
        $content = trim(implode("\n", $buffer));

        if ($content === '' && $heading === '') {
            return null;
        }

        $anchor = $heading === '' ? '' : $this->uniqueAnchor($this->slug($heading), $anchors);

        return new DocSection(
            id: $anchor === '' ? $document : $document.'#'.$anchor,
            document: $document,
            title: $title,
            heading: $heading,
            anchor: $anchor,
            level: $level,
            breadcrumb: $breadcrumb,
            content: $content,
            package: $package,
        );
    }

    /**
     * Update the heading trail for a heading at $level, dropping any deeper
     * headings it closes.
     *
     * @param  array<int, string>  $trail
     * @return array<int, string>
     */
    private function descend(array $trail, int $level, string $heading): array
    {
        $trail = array_slice($trail, 0, max(0, $level - 1));
        $trail[$level - 1] = $heading;

        return array_values(array_filter($trail, static fn (?string $part): bool => $part !== null && $part !== ''));
    }

    /**
     * The fence marker opening or closing a code block, or null for prose.
     */
    private function fenceMarker(string $line): ?string
    {
        return preg_match('/^\s*(`{3,}|~{3,})/', $line, $matches) === 1 ? $matches[1][0] : null;
    }

    private function stripFrontmatter(string $contents): string
    {
        return (string) preg_replace('/\A---\R.*?\R---\R/s', '', $contents, 1);
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function title(array $lines, string $document): string
    {
        foreach ($lines as $line) {
            if (preg_match('/^#\s+(.+?)\s*$/', $line, $matches) === 1) {
                return $this->plainText($matches[1]);
            }
        }

        return basename($document);
    }

    /**
     * Strip inline Markdown so a heading reads as plain text: `->color()` loses
     * its backticks, [Label](url) collapses to "Label".
     */
    private function plainText(string $text): string
    {
        $text = (string) preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $text);
        $text = str_replace(['`', '*', '_'], '', $text);

        return trim($text);
    }

    private function slug(string $heading): string
    {
        $slug = strtolower($heading);
        $slug = (string) preg_replace('/[^a-z0-9\s-]+/', '', $slug);
        $slug = (string) preg_replace('/[\s-]+/', '-', trim($slug));

        return trim($slug, '-');
    }

    /**
     * @param  array<string, int>  $anchors
     */
    private function uniqueAnchor(string $slug, array &$anchors): string
    {
        // A heading that slugs to nothing (e.g. "###  🎉") still needs an id.
        $slug = $slug === '' ? 'section' : $slug;

        if (! isset($anchors[$slug])) {
            $anchors[$slug] = 0;

            return $slug;
        }

        return $slug.'-'.(++$anchors[$slug]);
    }
}
