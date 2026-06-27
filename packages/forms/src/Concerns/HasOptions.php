<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use Closure;
use Illuminate\Validation\Rule;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireForms\Components\Field;
use NyonCode\WireForms\Contracts\ProvidesImplicitValidationRules;

/**
 * Canonical option-list support for choice fields (Select, Radio, CheckboxList).
 *
 * Owners may pass a literal `[value => label]` array, a {@see Closure} that
 * returns one, or — Filament-style — the class-string of a backed/unit enum
 * (`->options(Status::class)`). Enum class-strings expand to a `[value => label]`
 * map through the canonical {@see EnumResolver::options()} owner, using each
 * case's `HasLabel` label when available.
 *
 * @phpstan-require-extends Field
 */
trait HasOptions
{
    /** @var array<string|int, string>|string|Closure */
    protected array|string|Closure $options = [];

    /**
     * @param  array<string|int, string>|class-string|Closure  $options
     */
    public function options(array|string|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array<string|int, string>
     */
    public function getOptions(): array
    {
        return EnumResolver::normalizeOptions($this->evaluate($this->options));
    }

    /**
     * Implicit `in:` constraint derived from enum-sourced options (Filament-style).
     *
     * When the options come from a PHP enum class the valid value set is known, so the field
     * is constrained to those keys automatically. Skipped for non-enum options (the set may be
     * dynamic and the owner may not want a strict constraint) and for multi-value fields, whose
     * array state cannot be expressed as a single `in:` rule here. Satisfies
     * {@see ProvidesImplicitValidationRules}.
     *
     * @return array<int, mixed>
     */
    public function implicitValidationRules(): array
    {
        if (! EnumResolver::isEnumClass($this->evaluate($this->options))) {
            return [];
        }

        if ($this->getStateType() === 'array') {
            return [];
        }

        $keys = array_keys($this->getOptions());

        return $keys === [] ? [] : [Rule::in($keys)];
    }
}
