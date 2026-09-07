<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Mentions;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * How one model answers a mention when it cannot answer for itself.
 *
 * {@see Contracts\Mentionable} is the first choice — a model that knows its own
 * name and URL needs nothing here. This is the escape hatch for the models an
 * application does not own (a package's `User`, a vendor's `Page`), registered
 * once at boot through {@see MentionRegistry}.
 */
final class RegisteredMention
{
    private ?string $titleAttribute = null;

    /** @var Closure|null fn(Model): ?string */
    private ?Closure $urlUsing = null;

    /** @var Closure|null fn(Builder<Model>): Builder<Model> */
    private ?Closure $modifyQueryUsing = null;

    /** The column the fresh label is read from on every render. */
    public function titleAttribute(string $attribute): self
    {
        $this->titleAttribute = $attribute;

        return $this;
    }

    /**
     * Where a mention of this model links to.
     *
     * @param  Closure(Model): ?string  $callback
     */
    public function url(Closure $callback): self
    {
        $this->urlUsing = $callback;

        return $this;
    }

    /**
     * Scope the lookup every render runs.
     *
     * This is also where viewer-scoped visibility belongs. A record the query
     * excludes is simply not found, and the renderer already has one honest
     * answer for that: plain text, no link. Deleted and not-allowed-to-see
     * therefore take the same path, which is the one that leaks nothing — a
     * fresh title pulled straight from the database is the leak.
     *
     * @param  Closure(Builder<Model>): Builder<Model>  $callback
     */
    public function modifyQueryUsing(Closure $callback): self
    {
        $this->modifyQueryUsing = $callback;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    public function resolveLabel(Model $record): ?string
    {
        if ($this->titleAttribute === null) {
            return null;
        }

        $value = $record->getAttribute($this->titleAttribute);

        return is_scalar($value) ? (string) $value : null;
    }

    public function resolveUrl(Model $record): ?string
    {
        return $this->urlUsing === null ? null : ($this->urlUsing)($record);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyQuery(Builder $query): Builder
    {
        return $this->modifyQueryUsing === null
            ? $query
            : (($this->modifyQueryUsing)($query) ?? $query);
    }
}
