<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use NyonCode\WireForms\Components\Mention\Source;

/**
 * One mention trigger on a {@see TiptapEditor}: the character that opens the
 * suggestion list, and the models it offers.
 *
 * A trigger is not a model. `@` naming people and `#` naming *anything the site
 * publishes* are the same feature, and the second one only works if a trigger
 * can hold several sources at once:
 *
 *   Mention::make('#')->sources([
 *       Source::make(Article::class)->titleAttribute('title')->modifyOptionsQueryUsing(
 *           fn (Builder $query) => $query->published(),
 *       ),
 *       Source::make(Page::class)->titleAttribute('title'),
 *   ])
 *
 * Which is why the document stores a morph type beside the id: under one `#`,
 * `12` alone says nothing.
 */
final class Mention
{
    /** Rows one suggestion list shows, across every source under the trigger. */
    public const DEFAULT_LIMIT = 15;

    /** @var array<int, Source> */
    private array $sources = [];

    private bool $allowSpaces = false;

    private int $limit = self::DEFAULT_LIMIT;

    private function __construct(private readonly string $trigger) {}

    /**
     * @param  string  $trigger  The character that opens the list — `@`, `#`.
     */
    public static function make(string $trigger): self
    {
        return new self($trigger);
    }

    /**
     * @param  array<int, Source>  $sources
     */
    public function sources(array $sources): self
    {
        $this->sources = array_values($sources);

        return $this;
    }

    /** A trigger with exactly one model behind it. */
    public function source(Source $source): self
    {
        return $this->sources([$source]);
    }

    /**
     * Keep matching after a space, so `#Ceník 2026` can be typed out in full.
     *
     * Off by default, and worth leaving off: with spaces allowed the suggestion
     * has no way to know where the mention ended, so it keeps swallowing the
     * sentence after it until something dismisses the list. Titles are usually
     * findable from their first word anyway — `#cenik` finds `Ceník 2026`,
     * because the matching happens on the server against the whole column.
     */
    public function allowSpaces(bool $condition = true): self
    {
        $this->allowSpaces = $condition;

        return $this;
    }

    /** Cap the whole suggestion list, however many sources feed it. */
    public function limit(int $limit): self
    {
        $this->limit = max(1, $limit);

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    public function getTrigger(): string
    {
        return $this->trigger;
    }

    /**
     * @return array<int, Source>
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    public function isAllowingSpaces(): bool
    {
        return $this->allowSpaces;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Matches from every source under this trigger, grouped in declaration order
     * and capped as a whole.
     *
     * Each source is queried on its own — a `UNION` would cost the per-source
     * scoping, which is the reason several sources share a trigger in the first
     * place. Rows are not ranked against each other across sources: the list
     * says which group a row came from rather than pretending to know that an
     * article beats a page.
     *
     * @return array<int, array{type: string, id: string, label: string, group: string}>
     */
    public function search(string $search): array
    {
        // An empty term would turn the suggestion box into "list every user we
        // have". One character is a search; none is an export.
        if ($search === '') {
            return [];
        }

        $results = [];

        foreach ($this->getSources() as $source) {
            foreach ($source->search($search) as $row) {
                $results[] = $row;
            }
        }

        return array_slice($results, 0, $this->getLimit());
    }

    /**
     * @return array{trigger: string, allowSpaces: bool, limit: int}
     */
    public function toAlpineConfig(): array
    {
        return [
            'trigger' => $this->getTrigger(),
            'allowSpaces' => $this->isAllowingSpaces(),
            'limit' => $this->getLimit(),
        ];
    }
}
