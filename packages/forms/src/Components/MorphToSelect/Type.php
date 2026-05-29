<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components\MorphToSelect;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Morph type configuration for MorphToSelect.
 *
 * Usage:
 *   Type::make(Post::class)
 *       ->titleAttribute('title')
 *       ->label('Posts')
 *       ->modifyOptionsQueryUsing(fn ($query) => $query->where('published', true))
 */
final class Type
{
    /** @var class-string<Model> */
    private string $modelClass;

    private ?string $titleAttribute = null;

    private ?string $label = null;

    /** @var Closure|null fn(\Illuminate\Database\Eloquent\Builder) => Builder */
    private ?Closure $modifyOptionsQueryUsing = null;

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function make(string $modelClass): self
    {
        return new self($modelClass);
    }

    public function titleAttribute(string $attribute): self
    {
        $this->titleAttribute = $attribute;

        return $this;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function modifyOptionsQueryUsing(?Closure $callback): self
    {
        $this->modifyOptionsQueryUsing = $callback;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    /**
     * @return class-string<Model>
     */
    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    public function getTitleAttribute(): ?string
    {
        return $this->titleAttribute;
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::plural(class_basename($this->modelClass));
    }

    /**
     * Get options for this morph type (ID => title).
     *
     * @return array<string|int, string>
     */
    public function getOptions(): array
    {
        $titleAttribute = $this->titleAttribute;
        if ($titleAttribute === null) {
            return [];
        }

        $model = new $this->modelClass;
        $query = $model::query();

        if ($this->modifyOptionsQueryUsing) {
            $query = ($this->modifyOptionsQueryUsing)($query) ?? $query;
        }

        return $query->pluck($titleAttribute, $model->getKeyName())->all();
    }
}
