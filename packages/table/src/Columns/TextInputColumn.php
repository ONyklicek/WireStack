<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use NyonCode\WireTable\Concerns\HasView;

class TextInputColumn extends Column
{
    use HasView;

    protected string $inputType = 'text';

    protected ?int $maxLength = null;

    protected ?int $minLength = null;

    protected ?string $pattern = null;

    protected bool $disabled = false;

    protected ?Closure $disabledCallback = null;

    protected bool $autofocus = false;

    protected ?string $step = null;

    protected ?string $min = null;

    protected ?string $max = null;

    protected bool $readonly = false;

    protected ?Closure $readonlyCallback = null;

    protected ?string $editPermission = null;

    /** @var array<int|string, mixed>|Closure|null */
    protected array|Closure|null $rules = null;

    /** @var array<string, string> */
    protected array $validationMessages = [];

    /** @var array<string, string> */
    protected array $validationAttributes = [];

    protected bool $saveOnBlur = true;

    protected bool $saveOnEnter = true;

    protected bool $liveValidation = false;

    protected int $liveDebounce = 500;

    protected ?Closure $displayFormatter = null;

    protected ?Closure $beforeSaveFormatter = null;

    protected ?Closure $afterLoadFormatter = null;

    protected bool $trim = true;

    protected bool $nullable = false;

    protected ?string $inputPrefix = null;

    protected ?string $inputSuffix = null;

    protected ?string $helperText = null;

    protected ?string $autocomplete = null;

    protected bool $uppercase = false;

    protected bool $lowercase = false;

    protected ?string $inputClass = null;

    protected ?Closure $afterStateUpdated = null;

    protected ?Closure $saveUsing = null;

    /** @var int|null Decimal places for number formatting */
    protected ?int $decimals = null;

    /** @var string Decimal separator for display */
    protected string $decimalSeparator = ',';

    /** @var string Thousands separator for display */
    protected string $thousandsSeparator = ' ';

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->editable = true;
        $this->editableType = 'text';
    }

    // ==========================================
    // Input Type Methods
    // ==========================================

    public function type(string $type): static
    {
        $this->inputType = $type;

        return $this;
    }

    public function getInputType(): string
    {
        return $this->inputType;
    }

    public function numeric(): static
    {
        $this->inputType = 'number';

        return $this;
    }

    public function integer(): static
    {
        $this->inputType = 'number';
        $this->step = '1';

        return $this;
    }

    public function decimal(int $places = 2): static
    {
        $this->inputType = 'number';
        $this->step = '0.'.str_repeat('0', max(0, $places - 1)).'1';

        return $this;
    }

    /**
     * Alias for money() with common Czech format.
     */
    public function czk(int $decimals = 0): static
    {
        return $this->money($decimals);
    }

    /**
     * Format number for display with thousands separator.
     * Input will be text type to allow formatted input.
     */
    public function money(int $decimals = 0, string $thousandsSeparator = ' ', string $decimalSeparator = ','): static
    {
        $this->inputType = 'text';
        $this->decimals = $decimals;
        $this->thousandsSeparator = $thousandsSeparator;
        $this->decimalSeparator = $decimalSeparator;

        return $this;
    }

    public function email(): static
    {
        $this->inputType = 'email';

        return $this;
    }

    public function tel(): static
    {
        $this->inputType = 'tel';

        return $this;
    }

    public function url(): static
    {
        $this->inputType = 'url';

        return $this;
    }

    public function password(): static
    {
        $this->inputType = 'password';

        return $this;
    }

    // ==========================================
    // Validation & Constraints
    // ==========================================

    public function maxLength(?int $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    public function minLength(?int $minLength): static
    {
        $this->minLength = $minLength;

        return $this;
    }

    public function pattern(?string $pattern): static
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function step(?string $step): static
    {
        $this->step = $step;

        return $this;
    }

    public function min(?string $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function max(?string $max): static
    {
        $this->max = $max;

        return $this;
    }

    /**
     * @param  array<int|string, mixed>|Closure  $rules
     */
    public function rules(array|Closure $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    public function required(bool $required = true): static
    {
        if ($required) {
            return $this->rule('required');
        }

        return $this;
    }

    public function rule(string $rule): static
    {
        if ($this->rules === null) {
            $this->rules = [];
        }

        if (is_array($this->rules)) {
            $this->rules[] = $rule;
        }

        return $this;
    }

    /**
     * @param  array<string, string>  $messages
     */
    public function validationMessages(array $messages): static
    {
        $this->validationMessages = $messages;

        return $this;
    }

    public function validationAttribute(string $attribute): static
    {
        $this->validationAttributes[$this->getName()] = $attribute;

        return $this;
    }

    /**
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validate(mixed $value, Model $record): array
    {
        $rules = $this->getRules($record);

        if (empty($rules)) {
            return ['valid' => true, 'errors' => []];
        }

        // Use label as validation attribute if not explicitly set
        $attributes = $this->validationAttributes;
        if (empty($attributes[$this->getName()]) && $this->label) {
            $attributes[$this->getName()] = $this->label;
        }

        $validator = Validator::make(
            [$this->getName() => $value],
            [$this->getName() => $rules],
            $this->validationMessages,
            $attributes,
        );

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->get($this->getName()),
            ];
        }

        return ['valid' => true, 'errors' => []];
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getRules(Model $record): array
    {
        if ($this->rules instanceof Closure) {
            return call_user_func($this->rules, $record);
        }

        return $this->rules ?? [];
    }

    // ==========================================
    // Permissions & Disabled State
    // ==========================================

    public function disabled(bool|Closure $disabled = true): static
    {
        if ($disabled instanceof Closure) {
            $this->disabledCallback = $disabled;
        } else {
            $this->disabled = $disabled;
        }

        return $this;
    }

    public function readonly(bool|Closure $readonly = true): static
    {
        if ($readonly instanceof Closure) {
            $this->readonlyCallback = $readonly;
        } else {
            $this->readonly = $readonly;
        }

        return $this;
    }

    public function editPermission(?string $permission): static
    {
        $this->editPermission = $permission;

        return $this;
    }

    public function saveOnBlur(bool $save = true): static
    {
        $this->saveOnBlur = $save;

        return $this;
    }

    public function saveOnEnter(bool $save = true): static
    {
        $this->saveOnEnter = $save;

        return $this;
    }

    public function liveValidation(bool $live = true, int $debounce = 500): static
    {
        $this->liveValidation = $live;
        $this->liveDebounce = $debounce;

        return $this;
    }

    // ==========================================
    // Save Behavior
    // ==========================================

    public function afterStateUpdated(?Closure $callback): static
    {
        $this->afterStateUpdated = $callback;

        return $this;
    }

    public function getAfterStateUpdatedCallback(): ?Closure
    {
        return $this->afterStateUpdated;
    }

    public function saveUsing(?Closure $callback): static
    {
        $this->saveUsing = $callback;

        return $this;
    }

    public function getSaveCallback(): ?Closure
    {
        return $this->saveUsing;
    }

    public function displayFormat(Closure $formatter): static
    {
        $this->displayFormatter = $formatter;

        return $this;
    }

    public function beforeSave(Closure $formatter): static
    {
        $this->beforeSaveFormatter = $formatter;

        return $this;
    }

    public function getBeforeSaveFormatter(): ?Closure
    {
        return $this->beforeSaveFormatter;
    }

    // ==========================================
    // Formatting
    // ==========================================

    public function afterLoad(Closure $formatter): static
    {
        $this->afterLoadFormatter = $formatter;

        return $this;
    }

    public function formatForSave(mixed $value, Model|null $record): mixed
    {
        if ($this->trim && is_string($value)) {
            $value = trim($value);
        }

        if ($this->nullable && $value === '') {
            $value = null;
        }

        // Parse formatted number back to numeric value
        if ($this->decimals !== null && is_string($value) && $value !== '') {
            // Remove thousands separator and convert decimal separator to dot
            $value = str_replace($this->thousandsSeparator, '', $value);
            $value = str_replace($this->decimalSeparator, '.', $value);
            // Remove any remaining non-numeric characters except dot and minus
            $value = preg_replace("/[^0-9.\-]/", '', $value);
            $value = $value !== '' ? (float) $value : null;
        }

        if ($this->uppercase && is_string($value)) {
            $value = mb_strtoupper($value);
        }

        if ($this->lowercase && is_string($value)) {
            $value = mb_strtolower($value);
        }

        if ($this->beforeSaveFormatter) {
            $value = call_user_func($this->beforeSaveFormatter, $value, $record);
        }

        return $value;
    }

    public function trim(bool $trim = true): static
    {
        $this->trim = $trim;

        return $this;
    }

    public function nullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function uppercase(bool $uppercase = true): static
    {
        $this->uppercase = $uppercase;

        return $this;
    }

    public function lowercase(bool $lowercase = true): static
    {
        $this->lowercase = $lowercase;

        return $this;
    }

    public function inputPrefix(?string $prefix): static
    {
        $this->inputPrefix = $prefix;

        return $this;
    }

    public function inputSuffix(?string $suffix): static
    {
        $this->inputSuffix = $suffix;

        return $this;
    }

    public function helperText(?string $text): static
    {
        $this->helperText = $text;

        return $this;
    }

    public function autocomplete(?string $autocomplete): static
    {
        $this->autocomplete = $autocomplete;

        return $this;
    }

    public function autofocus(bool $autofocus = true): static
    {
        $this->autofocus = $autofocus;

        return $this;
    }

    // ==========================================
    // Input Appearance
    // ==========================================

    public function inputClass(?string $class): static
    {
        $this->inputClass = $class;

        return $this;
    }

    public function renderCell(Model $record): string
    {
        if (! $this->canView()) {
            return '';
        }

        $state = $this->getState($record);
        $state = $this->formatAfterLoad($state, $record);

        if (! $this->canEdit($record)) {
            return $this->renderReadonlyCell($state, $record);
        }

        return $this->renderEditableCell($state, $record);
    }

    public function formatAfterLoad(mixed $value, Model $record): mixed
    {
        // Format number for display
        if ($this->decimals !== null && $value !== null && $value !== '') {
            $value = number_format((float) $value, $this->decimals, $this->decimalSeparator, $this->thousandsSeparator);
        }

        if ($this->afterLoadFormatter) {
            $value = call_user_func($this->afterLoadFormatter, $value, $record);
        }

        return $value;
    }

    public function canEdit(Model $record): bool
    {
        if ($this->isDisabled($record) || $this->isReadonly($record)) {
            return false;
        }

        if ($this->editPermission) {
            /** @var Authenticatable|null $user */
            $user = auth()->guard()->user();

            if (! $user) {
                return false;
            }

            // Super Admin persistence
            if (method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) {
                return true;
            }

            if (method_exists($user, 'hasPermissionTo')) {
                return $user->hasPermissionTo($this->editPermission);
            }

            if (method_exists($user, 'can')) {
                return $user->can($this->editPermission);
            }

            return false;
        }

        return $this->isEditable();
    }

    public function isDisabled(Model $record): bool
    {
        if ($this->disabledCallback) {
            return call_user_func($this->disabledCallback, $record);
        }

        return $this->disabled;
    }

    public function isReadonly(Model $record): bool
    {
        if ($this->readonlyCallback) {
            return call_user_func($this->readonlyCallback, $record);
        }

        return $this->readonly;
    }

    // ==========================================
    // Rendering
    // ==========================================

    protected function renderReadonlyCell(mixed $state, Model $record): string
    {
        return $this->renderView('tables.columns.text-input-readonly', [
            'column' => $this,
            'record' => $record,
            'state' => $this->formatForDisplay($state, $record),
        ]);
    }

    public function formatForDisplay(mixed $value, Model $record): string
    {
        if ($this->displayFormatter) {
            return (string) call_user_func($this->displayFormatter, $value, $record);
        }

        // Format number for readonly display
        if ($this->decimals !== null && $value !== null && $value !== '') {
            return number_format((float) $value, $this->decimals, $this->decimalSeparator, $this->thousandsSeparator);
        }

        return (string) ($value ?? '');
    }

    protected function renderEditableCell(mixed $state, Model $record): string
    {
        return $this->renderView('tables.columns.text-input-editable', [
            'column' => $this,
            'record' => $record,
            'state' => $state,
        ]);
    }

    // ─── Getters for Blade templates ──────────────────────────

    public function getInputPrefix(): ?string
    {
        return $this->inputPrefix;
    }

    public function getInputSuffix(): ?string
    {
        return $this->inputSuffix;
    }

    public function getHelperText(): ?string
    {
        return $this->helperText;
    }

    public function getSaveOnBlur(): bool
    {
        return $this->saveOnBlur;
    }

    public function getSaveOnEnter(): bool
    {
        return $this->saveOnEnter;
    }

    public function getLiveValidation(): bool
    {
        return $this->liveValidation;
    }

    public function getLiveDebounce(): int
    {
        return $this->liveDebounce;
    }

    // ─── Build methods ──────────────────────────────────────────

    public function buildInputClasses(bool $hasPrefix, bool $hasSuffix): string
    {
        $classes = [
            'block w-full rounded-md border-gray-300 shadow-sm',
            'focus:border-primary-500 focus:ring-primary-500',
            'text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white',
            'disabled:opacity-50 disabled:cursor-not-allowed',
            'transition-colors',
        ];

        if ($hasPrefix) {
            $classes[] = 'pl-7';
        }

        if ($hasSuffix) {
            $classes[] = 'pr-8';
        }

        if ($this->inputClass) {
            $classes[] = $this->inputClass;
        }

        return implode(' ', $classes);
    }

    public function buildInputAttributes(): string
    {
        $attrs = [];

        $attrs['type'] = $this->inputType;

        if ($this->placeholder) {
            $attrs['placeholder'] = $this->placeholder;
        }

        if ($this->maxLength) {
            $attrs['maxlength'] = $this->maxLength;
        }

        if ($this->minLength) {
            $attrs['minlength'] = $this->minLength;
        }

        if ($this->pattern) {
            $attrs['pattern'] = $this->pattern;
        }

        if ($this->step) {
            $attrs['step'] = $this->step;
        }

        if ($this->min) {
            $attrs['min'] = $this->min;
        }

        if ($this->max) {
            $attrs['max'] = $this->max;
        }

        if ($this->autocomplete) {
            $attrs['autocomplete'] = $this->autocomplete;
        }

        if ($this->autofocus) {
            $attrs['autofocus'] = 'autofocus';
        }

        $parts = [];
        foreach ($attrs as $key => $value) {
            $parts[] = e($key).'="'.e($value).'"';
        }

        return implode(' ', $parts);
    }

    // ==========================================
    // Configuration Export
    // ==========================================

    /**
     * @return array<string, mixed>
     */
    public function getConfig(Model $record): array
    {
        return [
            'name' => $this->getName(),
            'type' => $this->inputType,
            'canEdit' => $this->canEdit($record),
            'isDisabled' => $this->isDisabled($record),
            'isReadonly' => $this->isReadonly($record),
            'maxLength' => $this->maxLength,
            'minLength' => $this->minLength,
            'pattern' => $this->pattern,
            'step' => $this->step,
            'min' => $this->min,
            'max' => $this->max,
            'placeholder' => $this->getPlaceholder(),
            'saveOnBlur' => $this->saveOnBlur,
            'saveOnEnter' => $this->saveOnEnter,
            'liveValidation' => $this->liveValidation,
            'prefix' => $this->inputPrefix,
            'suffix' => $this->inputSuffix,
            'helperText' => $this->helperText,
            'hasRules' => ! empty($this->rules),
            'decimals' => $this->decimals,
            'decimalSeparator' => $this->decimalSeparator,
            'thousandsSeparator' => $this->thousandsSeparator,
        ];
    }
}
