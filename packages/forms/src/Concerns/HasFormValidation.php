<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;
use NyonCode\WireCore\Foundation\Support\StateMatcher;
use NyonCode\WireForms\Contracts\ProvidesImplicitValidationRules;
use NyonCode\WireForms\Exceptions\FormConfigurationException;

/**
 * Validation support for form field components.
 *
 * Provides rules(), required(), validationMessages(), and related API.
 */
trait HasFormValidation
{
    /** @var array<int, mixed>|Closure */
    protected array|Closure $rules = [];

    protected bool|Closure $isRequired = false;

    protected bool $validatesLive = false;

    /** @var Closure|null fn(): mixed — builds the unique rule at validation time */
    protected ?Closure $uniqueRule = null;

    /** @var array<string, string> */
    protected array $validationMessages = [];

    /**
     * Set the field's validation rules.
     *
     * Accepts a plain array/string, a Closure returning either, or an array
     * whose individual entries may be Closures. Closures are resolved lazily in
     * {@see getValidationRules()} with the field's reactive `$get` / `$set`
     * accessors, so rules can depend on live sibling state.
     *
     * @param  array<int, mixed>|string|Closure  $rules
     */
    public function rules(array|string|Closure $rules): static
    {
        $this->rules = $rules instanceof Closure
            ? $rules
            : (is_array($rules) ? $rules : [$rules]);

        return $this;
    }

    /** Mark the field required (a bool or a `$get`-aware Closure); use `requiredIf()` for the reactive sibling-driven case. */
    public function required(bool|Closure $condition = true): static
    {
        $this->isRequired = $condition;

        return $this;
    }

    /**
     * Require this field only when another field equals the given value (or is
     * one of the given values). Resolved reactively against live sibling state.
     */
    public function requiredIf(string $field, mixed $value = true): static
    {
        return $this->required(fn (callable $get): bool => StateMatcher::matches($get($field), $value));
    }

    /**
     * Require this field unless another field equals the given value (or is one
     * of the given values). Resolved reactively against live sibling state.
     */
    public function requiredUnless(string $field, mixed $value = true): static
    {
        return $this->required(fn (callable $get): bool => ! StateMatcher::matches($get($field), $value));
    }

    /**
     * Constrain the value to be unique in a database table.
     *
     * Everything defaults to what the form already knows: the table from the
     * bound model, the column from the field name, and — on an edit form — the
     * row being edited is excluded from the check. That last part is the reason
     * this method exists rather than a documented string: `unique:companies,name`
     * fails its own record on every edit, and the fix, appending the id, is
     * wrong the other way round in create mode, where there is no id to append.
     * Both halves are one question the form can answer for itself.
     *
     * @param  string|null  $table  defaults to the bound model's table
     * @param  string|null  $column  defaults to the field's name
     * @param  bool  $ignoreRecord  exclude the record being edited (a create form has none to exclude)
     * @param  Closure|null  $modifyRuleUsing  receives the `Unique` rule to scope it further
     */
    public function unique(
        ?string $table = null,
        ?string $column = null,
        bool $ignoreRecord = true,
        ?Closure $modifyRuleUsing = null,
    ): static {
        // Built at validation time, not here: the record arrives from the form
        // runtime after the schema is declared, so a rule resolved now would
        // ignore nothing on the very form it was written for.
        $this->uniqueRule = function () use ($table, $column, $ignoreRecord, $modifyRuleUsing): mixed {
            $record = $this->validationRecord();
            $table ??= $record?->getTable();

            if ($table === null) {
                throw FormConfigurationException::uniqueWithoutTable($this->getName());
            }

            $rule = Rule::unique($table, $column ?? $this->getName());

            if ($ignoreRecord && $record !== null && $record->exists) {
                $rule->ignore($record->getKey(), $record->getKeyName());
            }

            return $modifyRuleUsing === null ? $rule : ($modifyRuleUsing($rule) ?? $rule);
        };

        return $this;
    }

    /**
     * The record a rule may need to know about — the row an edit form is editing.
     *
     * Null here because this trait's other host, a Repeater, is not bound to
     * one; a field overrides it with the record the form runtime handed it.
     */
    protected function validationRecord(): ?Model
    {
        return null;
    }

    /**
     * Require this field whenever another field has a non-empty value. Resolved
     * reactively against live sibling state.
     */
    public function requiredWith(string $field): static
    {
        return $this->required(fn (callable $get): bool => filled($get($field)));
    }

    public function validatesLive(): bool
    {
        return $this->validatesLive;
    }

    /**
     * Set custom validation messages for this field's rules.
     *
     * @param  array<string, string>  $messages
     */
    public function validationMessages(array $messages): static
    {
        $this->validationMessages = $messages;

        return $this;
    }

    public function isRequired(): bool
    {
        return (bool) $this->evaluate($this->isRequired);
    }

    /**
     * @return array<int, mixed>
     */
    public function getValidationRules(): array
    {
        $rules = $this->resolveRules();

        if ($this instanceof ProvidesImplicitValidationRules && ! $this->hasOptionConstraint($rules)) {
            foreach ($this->implicitValidationRules() as $rule) {
                $rules[] = $rule;
            }
        }

        if ($this->uniqueRule instanceof Closure) {
            $rules[] = ($this->uniqueRule)();
        }

        if ($this->isRequired() && ! in_array('required', $rules)) {
            array_unshift($rules, 'required');
        }

        return $rules;
    }

    /**
     * Resolve the configured rules to a flat array, evaluating any Closures.
     *
     * The rules property itself may be a Closure (returning an array/string) and
     * individual array entries may also be Closures; both forms are evaluated
     * with the field's reactive accessors so rules can read live sibling state.
     *
     * @return array<int, mixed>
     */
    private function resolveRules(): array
    {
        $rules = $this->rules instanceof Closure ? $this->evaluate($this->rules) : $this->rules;
        $rules = is_array($rules) ? $rules : [$rules];

        $resolved = [];

        foreach ($rules as $rule) {
            $rule = $rule instanceof Closure ? $this->evaluate($rule) : $rule;

            if (is_array($rule)) {
                foreach ($rule as $nested) {
                    $resolved[] = $nested;
                }

                continue;
            }

            $resolved[] = $rule;
        }

        return $resolved;
    }

    /**
     * Whether the field already carries an owner-defined value-set constraint
     * (`in:` / `Rule::in()` / `Rule::enum()`), in which case no implicit one is added.
     *
     * @param  array<int, mixed>  $rules
     */
    private function hasOptionConstraint(array $rules): bool
    {
        foreach ($rules as $rule) {
            if ($rule instanceof In || $rule instanceof Enum) {
                return true;
            }

            if (is_string($rule) && (str_starts_with($rule, 'in:') || str_starts_with($rule, 'enum'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    public function getValidationMessages(): array
    {
        return $this->validationMessages;
    }
}
