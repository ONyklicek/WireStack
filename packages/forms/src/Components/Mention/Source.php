<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components\Mention;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NyonCode\WireCore\Foundation\Mentions\Contracts\Mentionable;
use NyonCode\WireForms\Components\MorphToSelect\Type;

/**
 * One model a mention trigger can offer, mirroring {@see Type}
 * — the vocabulary this repository already uses for "one field, several models".
 *
 * A source is an *edit-time* declaration: which records this editor offers, how
 * they are found, and how the list scopes. What a mention reads as once it is
 * stored is a different question with a different owner
 * ({@see Mentionable}), because
 * rendering happens where no form exists.
 *
 * Usage:
 *   Source::make(Article::class)
 *       ->titleAttribute('title')
 *       ->label('Články')
 *       ->modifyOptionsQueryUsing(fn (Builder $query) => $query->published())
 */
final class Source
{
    /** Rows this one source contributes before the trigger's own cap applies. */
    public const DEFAULT_LIMIT = 5;

    private ?string $titleAttribute = null;

    private ?string $searchAttribute = null;

    private ?string $label = null;

    private int $limit = self::DEFAULT_LIMIT;

    /** @var Closure|null fn(Builder<Model>): Builder<Model> */
    private ?Closure $modifyOptionsQueryUsing = null;

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function __construct(private readonly string $modelClass) {}

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function make(string $modelClass): self
    {
        return new self($modelClass);
    }

    /** The column shown in the suggestion list. */
    public function titleAttribute(string $attribute): self
    {
        $this->titleAttribute = $attribute;

        return $this;
    }

    /**
     * The column matched against what the person typed, when it is not the one
     * being displayed — a user picked by e-mail but shown by name.
     */
    public function searchAttribute(string $attribute): self
    {
        $this->searchAttribute = $attribute;

        return $this;
    }

    /** The group heading this source's rows sit under. */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /** How many rows this source contributes to one suggestion list. */
    public function limit(int $limit): self
    {
        $this->limit = max(1, $limit);

        return $this;
    }

    /**
     * Scope the suggestion query — published articles, the current team's users.
     *
     * This is the author's view of the world, not the reader's: it decides what
     * can be *inserted*. What a stored mention resolves to later is scoped again
     * at render time, where the viewer may be somebody else entirely.
     *
     * @param  Closure(Builder<Model>): Builder<Model>  $callback
     */
    public function modifyOptionsQueryUsing(Closure $callback): self
    {
        $this->modifyOptionsQueryUsing = $callback;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    public function getTitleAttribute(): ?string
    {
        return $this->titleAttribute;
    }

    public function getSearchAttribute(): string
    {
        return $this->searchAttribute ?? (string) $this->titleAttribute;
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::plural(class_basename($this->modelClass));
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * The type written into the document. `getMorphClass()`, never `::class` —
     * an application with a `Relation::morphMap()` stores `article`, and a
     * mention has to read back the same way its audit trail does.
     */
    public function getMorphAlias(): string
    {
        return (new $this->modelClass)->getMorphClass();
    }

    /**
     * Matches for one search term, shaped for the suggestion list.
     *
     * @return array<int, array{type: string, id: string, label: string, group: string}>
     */
    public function search(string $search): array
    {
        $titleAttribute = $this->getTitleAttribute();

        if ($titleAttribute === null) {
            return [];
        }

        $model = new $this->modelClass;
        $query = $model::query();

        if ($this->modifyOptionsQueryUsing !== null) {
            $query = ($this->modifyOptionsQueryUsing)($query) ?? $query;
        }

        // `%` and `_` typed by a person are literals, not wildcards — unescaped,
        // a lone `%` matches the entire table. The escape character is `!` rather
        // than a backslash because a backslash is not portable here: SQLite takes
        // string literals verbatim while MySQL unescapes them, so the same
        // `ESCAPE '\\'` clause means two different things. `!` means itself
        // everywhere.
        $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);

        $query->whereRaw(
            $query->getQuery()->getGrammar()->wrap($this->getSearchAttribute())." LIKE ? ESCAPE '!'",
            ['%'.$term.'%'],
        );

        $alias = $this->getMorphAlias();
        $group = $this->getLabel();

        return $query
            ->limit($this->getLimit())
            ->get()
            ->map(fn (Model $record): array => [
                'type' => $alias,
                'id' => (string) $record->getKey(),
                'label' => (string) $record->getAttribute($titleAttribute),
                'group' => $group,
            ])
            ->all();
    }
}
