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
     * Set the selectable options as a value => label map, a closure, or an enum class-string.
     *
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
     * Implicit `in:` constraint when the valid value set is known (Filament-style).
     *
     * Known means the options are an enum class or a literal array: a forged
     * request could otherwise store any value in a field that only ever offered
     * a few. A closure is left alone (its set may depend on state the owner
     * did not mean to freeze), as is a field that accepts values beyond its
     * list ({@see acceptsValuesOutsideOptions()}: a remote search, a "create
     * option" form) and a multi-value field, whose array state cannot be
     * expressed as a single `in:` rule here. Satisfies
     * {@see ProvidesImplicitValidationRules}; an owner rule of its own (`in:`,
     * `Rule::in()`, `Rule::enum()`) replaces it.
     *
     * An optional field is paired with `nullable`, because clearing the canonical combobox
     * writes `null` (not `''`) and Laravel fails a bare `in:` on null — an owner who never
     * asked for the constraint must not have to hand-patch it back to optional.
     *
     * @return array<int, mixed>
     */
    public function implicitValidationRules(): array
    {
        $known = is_array($this->options) || EnumResolver::isEnumClass($this->evaluate($this->options));

        if (! $known || $this->acceptsValuesOutsideOptions()) {
            return [];
        }

        if ($this->getStateType() === 'array') {
            return [];
        }

        $keys = array_keys($this->getOptions());

        if ($keys === []) {
            return [];
        }

        return $this->isRequired()
            ? [Rule::in($keys)]
            : ['nullable', Rule::in($keys)];
    }

    /**
     * Whether a value outside the declared options is legitimate — a choice
     * field that searches the server or creates options says so, and keeps no
     * implicit `in:` rule.
     */
    protected function acceptsValuesOutsideOptions(): bool
    {
        return false;
    }
}
