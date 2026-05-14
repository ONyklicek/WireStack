<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NyonCode\WireCore\Core\Support\Trans;

/** @phpstan-consistent-constructor */
class Filter implements Htmlable
{
    public string $name;

    public ?string $label = null;

    public ?string $column = null;

    public ?Closure $queryCallback = null;

    public mixed $default = null;

    public bool $hidden = false;

    public ?Closure $hiddenCallback = null;

    public ?string $permission = null;

    public ?string $placeholder = null;

    public bool $multiple = false;

    /** @var string|null Relationship name for related model attributes */
    protected ?string $relation = null;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->parseRelation($name);
    }

    private function parseRelation(string $name): void
    {
        if (Str::contains($name, '.')) {
            $parts = explode('.', $name);
            $this->relation = implode('.', array_slice($parts, 0, -1));
        }
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function column(?string $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function query(Closure $callback): static
    {
        $this->queryCallback = $callback;

        return $this;
    }

    /**
     * Get the relationship name.
     */
    public function getRelation(): ?string
    {
        return $this->relation;
    }

    public function getQueryCallback(): ?Closure
    {
        return $this->queryCallback;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function visible(bool|Closure $visible = true): static
    {
        return $this->hidden(! $visible);
    }

    public function hidden(bool|Closure $hidden = true): static
    {
        if ($hidden instanceof Closure) {
            $this->hiddenCallback = $hidden;
        } else {
            $this->hidden = $hidden;
        }

        return $this;
    }

    public function permission(?string $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, mixed $value): Builder
    {
        if ($value === null || $value === '' || $value === []) {
            return $query;
        }

        if ($this->queryCallback) {
            return call_user_func($this->queryCallback, $query, $value);
        }

        // If filter has a relation, it should be handled by WithTable::applyFilters
        // This is kept for backwards compatibility when used directly
        if ($this->relation) {
            $relation = $this->relation;
            $attribute = $this->getRelationshipAttribute();

            return $query->whereHas($relation, function ($q) use ($attribute, $value) {
                if ($this->multiple && is_array($value)) {
                    $q->whereIn($attribute, $value);
                } else {
                    $q->where($attribute, $value);
                }
            });
        }

        $column = $this->getColumn();

        if ($this->multiple && is_array($value)) {
            /** @var Builder<Model> */
            return $query->whereIn($column, $value);
        }

        /** @var Builder<Model> */
        return $query->where($column, $value);
    }

    /**
     * Get the relationship attribute of the column.
     */
    public function getRelationshipAttribute(): ?string
    {
        if (! $this->relation) {
            return null;
        }

        $parts = explode('.', $this->name);

        return end($parts);
    }

    public function getColumn(): string
    {
        return $this->column ?? $this->name;
    }

    public function toHtml(): string
    {
        return $this->render();
    }

    public function render(mixed $value = null): string
    {
        if (! $this->canView()) {
            return '';
        }

        return view($this->resolveFilterView('tables.filters.text'), [
            'filter' => $this,
            'value' => $value,
        ])->render();
    }

    /**
     * Resolve filter view name with package namespace support.
     */
    protected function resolveFilterView(string $defaultView): string
    {
        $namespacedView = "wire-table::{$defaultView}";

        if (view()->exists($namespacedView)) {
            return $namespacedView;
        }

        return $defaultView;
    }

    public function canView(): bool
    {
        if ($this->isHidden()) {
            return false;
        }

        if (! $this->permission) {
            return true;
        }

        /** @var Authenticatable|null $user */
        $user = auth()->guard()->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasPermissionTo')) {
            return $user->hasPermissionTo($this->permission);
        }

        return true;
    }

    public function isHidden(): bool
    {
        if ($this->hiddenCallback) {
            return call_user_func($this->hiddenCallback);
        }

        return $this->hidden;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::headline($this->name);
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder ?? Trans::get('wire-table::messages.select_placeholder');
    }
}
