<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithStateColor;
use NyonCode\WireTable\Concerns\RendersBadgeSurface;

/**
 * Tags column — renders a multi-value state (array, JSON cast, relation
 * collection, or a separated string) as a row of chips.
 *
 * The chip chrome is the canonical badge surface shared with BadgeColumn, so a
 * tag and a badge cannot drift apart visually; per-value coloring comes from the
 * same `colors()` / `colorUsing()` vocabulary.
 */
class TagsColumn extends Column
{
    // colors()/colorUsing()/getColorForState() come from InteractsWithStateColor.
    use InteractsWithStateColor;

    // getColorClasses()/getSizeClasses() come from RendersBadgeSurface.
    use RendersBadgeSurface;

    protected ?string $separator = null;

    protected ?int $limitList = null;

    /** Split a plain string state into tags on this separator (e.g. ","). */
    public function separator(?string $separator = ','): static
    {
        $this->separator = $separator;

        return $this;
    }

    /** Show at most this many chips, collapsing the rest into a "+N" chip. */
    public function limitList(?int $limit): static
    {
        $this->limitList = $limit;

        return $this;
    }

    public function getSeparator(): ?string
    {
        return $this->separator;
    }

    public function getLimitList(): ?int
    {
        return $this->limitList;
    }

    public function renderCell(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $tags = $this->resolveTags($this->getState($record));

        if ($tags === []) {
            return $this->getEmptyCellText();
        }

        $overflow = 0;

        if ($this->limitList !== null && count($tags) > $this->limitList) {
            $overflow = count($tags) - $this->limitList;
            $tags = array_slice($tags, 0, $this->limitList);
        }

        $chips = [];

        foreach ($tags as $tag) {
            $chips[] = [
                'label' => (string) $tag,
                'colorClasses' => $this->getColorClasses($this->getColorForState($tag)),
            ];
        }

        // §7: a tag vocabulary is low-cardinality — memoise the view render by its
        // data so rows carrying the same tags reuse one render.
        return $this->renderViewCached('tables.columns.tags', [
            'chips' => $chips,
            'sizeClasses' => $this->getSizeClasses(),
            'overflow' => $overflow,
            'overflowClasses' => $this->getColorClasses(null),
        ]);
    }

    /**
     * Normalise any multi-value state into a list of scalar tags.
     *
     * Accepts arrays and JSON casts, anything Arrayable (an Eloquent relation
     * collection), and — when `separator()` is set — a plain delimited string.
     * Blank entries are dropped so a trailing separator does not emit an empty
     * chip.
     *
     * @return array<int, string>
     */
    protected function resolveTags(mixed $state): array
    {
        if ($state instanceof Arrayable) {
            $state = $state->toArray();
        }

        if (is_string($state) && $this->separator !== null && $this->separator !== '') {
            $state = explode($this->separator, $state);
        }

        if (! is_array($state)) {
            $state = ($state === null || $state === '') ? [] : [$state];
        }

        $tags = [];

        foreach ($state as $tag) {
            // A relation collection arrives as rows; a scalar column as values.
            if (is_array($tag)) {
                continue;
            }

            $tag = trim((string) $tag);

            if ($tag !== '') {
                $tags[] = $tag;
            }
        }

        return $tags;
    }
}
