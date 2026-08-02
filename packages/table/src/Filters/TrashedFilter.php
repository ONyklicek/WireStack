<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireTable\Exceptions\TableConfigurationException;

/**
 * Soft-delete filter — switches the query between live, trashed and all records.
 *
 * Unlike every other filter this one constrains no column: it changes which
 * global scope applies, so the state maps to `withTrashed()` / `onlyTrashed()`
 * rather than to a where clause. Clearing the filter restores the default
 * (live records only).
 */
class TrashedFilter extends SelectFilter
{
    /** Include soft-deleted records alongside live ones. */
    public const WITH = 'with';

    /** Show only soft-deleted records. */
    public const ONLY = 'only';

    protected ?string $withTrashedLabel = null;

    protected ?string $onlyTrashedLabel = null;

    /** Set the label for the "with trashed" option. */
    public function withTrashedLabel(?string $label): static
    {
        $this->withTrashedLabel = $label;

        return $this;
    }

    /** Set the label for the "only trashed" option. */
    public function onlyTrashedLabel(?string $label): static
    {
        $this->onlyTrashedLabel = $label;

        return $this;
    }

    /**
     * The state selects a global scope, not a column/operator/value, so it can
     * never be expressed as a planner definition — always route through apply().
     */
    public function bypassesPlanner(): bool
    {
        return true;
    }

    /**
     * Only the two explicit states are meaningful; anything else (null, '', an
     * unknown key seeded from the URL) means "live records only", i.e. the
     * filter is inactive and the default scope stands.
     */
    public function normalizeValue(mixed $value): mixed
    {
        return in_array($value, [self::WITH, self::ONLY], true) ? $value : null;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, mixed $value): Builder
    {
        $state = $this->normalizeValue($value);

        if ($state === null) {
            return $query;
        }

        $this->assertSoftDeletable($query);

        if ($this->queryCallback) {
            return $this->applyQueryCallback($query, $state, $value);
        }

        // The soft-delete scope macros exist only on a SoftDeletes builder, which
        // assertSoftDeletable() has just proven this model to be — static analysis
        // sees the generic Builder<Model> and cannot know that.
        // @phpstan-ignore method.notFound, method.notFound
        return $state === self::ONLY ? $query->onlyTrashed() : $query->withTrashed();
    }

    /**
     * A table whose model has no soft deletes would fail deep inside the query
     * builder with "Call to undefined method …::onlyTrashed()". Fail here with
     * the filter and model named instead.
     *
     * @param  Builder<Model>  $query
     */
    protected function assertSoftDeletable(Builder $query): void
    {
        $model = $query->getModel();

        if (! in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            throw TableConfigurationException::modelIsNotSoftDeletable(
                $this->getName(),
                $model::class,
            );
        }
    }

    /**
     * The two scopes are the filter's whole vocabulary, so `options()` has
     * nothing to set: this filter switches a global scope rather than matching
     * values, and an arbitrary option could not be applied. Inherited from
     * SelectFilter, it would otherwise accept a value and silently ignore it.
     *
     * @param  array<string, string>|string|Closure  $options
     */
    public function options(array|string|Closure $options): static
    {
        throw TableConfigurationException::fixedFilterOptions($this->getName());
    }

    /**
     * "Live records only" is deliberately not an option: it is the placeholder,
     * i.e. clearing the filter — matching how TernaryFilter models its "All".
     *
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return [
            self::WITH => $this->getWithTrashedLabel(),
            self::ONLY => $this->getOnlyTrashedLabel(),
        ];
    }

    /**
     * "Live records only" is the placeholder rather than an option, so the
     * inherited select surface renders it as the empty choice.
     *
     * Reads the configured value directly rather than deferring to the parent:
     * `Filter::getPlaceholder()` already substitutes the generic "Select…"
     * translation, so it never returns null to fall back from.
     */
    public function getPlaceholder(): ?string
    {
        return $this->placeholder ?? $this->getWithoutTrashedLabel();
    }

    /** Show the selected scope in the indicator chip. */
    protected function getIndicatorValueLabel(mixed $value): ?string
    {
        return match ($this->normalizeValue($value)) {
            self::WITH => $this->getWithTrashedLabel(),
            self::ONLY => $this->getOnlyTrashedLabel(),
            default => null,
        };
    }

    public function getWithoutTrashedLabel(): string
    {
        return Trans::get('wire-table::messages.filter_without_trashed');
    }

    public function getWithTrashedLabel(): string
    {
        return $this->withTrashedLabel ?? Trans::get('wire-table::messages.filter_with_trashed');
    }

    public function getOnlyTrashedLabel(): string
    {
        return $this->onlyTrashedLabel ?? Trans::get('wire-table::messages.filter_only_trashed');
    }
}
