<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Support\Docs;

/**
 * One heading-delimited slice of a document — the unit this package retrieves.
 *
 * Whole-file results are close to useless to an agent: `docs/table/columns/index.md`
 * documents twelve column types and the entire shared column API, so "the file
 * that mentions badges" is not an answer. A section is small enough to hand back
 * verbatim and addressable enough to fetch again by id.
 */
class DocSection
{
    /**
     * @param  string  $id  Addressable id, e.g. "docs/table/columns/index.md#shared-column-api".
     * @param  string  $document  Owning document id, e.g. "docs/table/columns/index.md".
     * @param  string  $title  Document title, e.g. "Columns".
     * @param  string  $heading  This section's heading, empty for a document preamble.
     * @param  string  $anchor  Slugified heading, empty for a document preamble.
     * @param  int  $level  Heading depth (1-6); 0 for a preamble.
     * @param  array<int, string>  $breadcrumb  Heading trail, e.g. ["Columns", "Shared Column API"].
     * @param  string  $package  Owning wire package, e.g. "wire-table".
     */
    public function __construct(
        public readonly string $id,
        public readonly string $document,
        public readonly string $title,
        public readonly string $heading,
        public readonly string $anchor,
        public readonly int $level,
        public readonly array $breadcrumb,
        public readonly string $content,
        public readonly string $package,
    ) {}

    /**
     * Human-readable trail, e.g. "Columns > Shared Column API > Factory & Identity".
     */
    public function path(): string
    {
        return implode(' > ', $this->breadcrumb);
    }

    /**
     * The heading line plus the body, i.e. the section as it reads in the file.
     */
    public function markdown(): string
    {
        if ($this->heading === '') {
            return $this->content;
        }

        return str_repeat('#', max(1, $this->level)).' '.$this->heading."\n\n".$this->content;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'document' => $this->document,
            'title' => $this->title,
            'heading' => $this->heading,
            'breadcrumb' => $this->path(),
            'package' => $this->package,
        ];
    }
}
