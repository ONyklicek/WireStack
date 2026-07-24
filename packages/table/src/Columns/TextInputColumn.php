<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Foundation\Contracts\DehydratesState;
use NyonCode\WireCore\Foundation\Contracts\HydratesState;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireCore\Foundation\View\Primitives;
use NyonCode\WireTable\Concerns\HasRecordVersion;
use NyonCode\WireTable\Concerns\HasView;
use NyonCode\WireTable\Concerns\InteractsWithRecordDisabledState;

class TextInputColumn extends Column implements DehydratesState, HydratesState
{
    use HasRecordVersion;
    use HasView;
    use InteractsWithRecordDisabledState;

    protected string $inputType = 'text';

    protected ?int $maxLength = null;

    protected ?int $minLength = null;

    protected ?string $pattern = null;

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
        $this->capabilities = $this->capabilities->add(Capability::Editable);
        $this->editableType = 'text';
    }

    // ==========================================
    // Input Type Methods
    // ==========================================

    /** Set the HTML input type (e.g. "text", "number", "email"). */
    public function type(string $type): static
    {
        $this->inputType = $type;

        return $this;
    }

    public function getInputType(): string
    {
        return $this->inputType;
    }

    /** Use a numeric input. */
    public function numeric(): static
    {
        $this->inputType = 'number';

        return $this;
    }

    /** Use a numeric input restricted to whole numbers. */
    public function integer(): static
    {
        $this->inputType = 'number';
        $this->step = '1';

        return $this;
    }

    /** Use a numeric input with the given number of decimal places. */
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

    /** Use an email input. */
    public function email(): static
    {
        $this->inputType = 'email';

        return $this;
    }

    /** Use a telephone input. */
    public function tel(): static
    {
        $this->inputType = 'tel';

        return $this;
    }

    /** Use a URL input. */
    public function url(): static
    {
        $this->inputType = 'url';

        return $this;
    }

    /** Use a password (masked) input. */
    public function password(): static
    {
        $this->inputType = 'password';

        return $this;
    }

    // ==========================================
    // Validation & Constraints
    // ==========================================

    /** Set the maximum input length (HTML maxlength). */
    public function maxLength(?int $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    /** Set the minimum input length (HTML minlength). */
    public function minLength(?int $minLength): static
    {
        $this->minLength = $minLength;

        return $this;
    }

    /** Set the HTML pattern the input value must match. */
    public function pattern(?string $pattern): static
    {
        $this->pattern = $pattern;

        return $this;
    }

    /** Set the numeric step increment (HTML step). */
    public function step(?string $step): static
    {
        $this->step = $step;

        return $this;
    }

    /** Set the minimum numeric value (HTML min). */
    public function min(?string $min): static
    {
        $this->min = $min;

        return $this;
    }

    /** Set the maximum numeric value (HTML max). */
    public function max(?string $max): static
    {
        $this->max = $max;

        return $this;
    }

    /**
     * Set the Laravel validation rules applied before saving an edit.
     *
     * @param  array<int|string, mixed>|Closure  $rules
     */
    public function rules(array|Closure $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    /** Add the "required" validation rule. */
    public function required(bool $required = true): static
    {
        if ($required) {
            return $this->rule('required');
        }

        return $this;
    }

    /** Append a single validation rule to the rule set. */
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
     * Set custom validation messages for this column's rules.
     *
     * @param  array<string, string>  $messages
     */
    public function validationMessages(array $messages): static
    {
        $this->validationMessages = $messages;

        return $this;
    }

    /** Set the human-readable attribute name used in validation messages. */
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
            return ($this->rules)($record);
        }

        return $this->rules ?? [];
    }

    // ==========================================
    // Permissions & Disabled State
    // ==========================================

    /** Render the input read-only; a Closure receives the record per row. */
    public function readonly(bool|Closure $readonly = true): static
    {
        if ($readonly instanceof Closure) {
            $this->readonlyCallback = $readonly;
        } else {
            $this->readonly = $readonly;
        }

        return $this;
    }

    /** Require this ability before an inline edit is allowed to save. */
    public function editPermission(?string $permission): static
    {
        $this->editPermission = $permission;

        return $this;
    }

    /** Save the edit when the input loses focus (default true). */
    public function saveOnBlur(bool $save = true): static
    {
        $this->saveOnBlur = $save;

        return $this;
    }

    /** Save the edit when the user presses Enter (default true). */
    public function saveOnEnter(bool $save = true): static
    {
        $this->saveOnEnter = $save;

        return $this;
    }

    /** Validate as the user types, debounced by the given milliseconds. */
    public function liveValidation(bool $live = true, int $debounce = 500): static
    {
        $this->liveValidation = $live;
        $this->liveDebounce = $debounce;

        return $this;
    }

    // ==========================================
    // Save Behavior
    // ==========================================

    /** Run a callback after the edited value is applied to the record. */
    public function afterStateUpdated(?Closure $callback): static
    {
        $this->afterStateUpdated = $callback;

        return $this;
    }

    public function getAfterStateUpdatedCallback(): ?Closure
    {
        return $this->afterStateUpdated;
    }

    /** Persist the edited value with a custom callback instead of the default save. */
    public function saveUsing(?Closure $callback): static
    {
        $this->saveUsing = $callback;

        return $this;
    }

    public function getSaveCallback(): ?Closure
    {
        return $this->saveUsing;
    }

    /** Transform the value for display in the cell (view only). */
    public function displayFormat(Closure $formatter): static
    {
        $this->displayFormatter = $formatter;

        return $this;
    }

    /** Transform the edited value just before it is saved. */
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

    /** Transform the stored value into the editable input value on load. */
    public function afterLoad(Closure $formatter): static
    {
        $this->afterLoadFormatter = $formatter;

        return $this;
    }

    /**
     * @deprecated Renamed to dehydrateState() when ADR 0021 gave the save path a
     *             named seam. Kept because, unlike native(), this method works —
     *             removing it would break real behaviour, not a fiction.
     */
    public function formatForSave(mixed $value, ?Model $record): mixed
    {
        return $this->dehydrateState($value, $record);
    }

    public function dehydrateState(mixed $value, ?Model $record = null): mixed
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
            $value = ($this->beforeSaveFormatter)($value, $record);
        }

        return $value;
    }

    /** Trim surrounding whitespace from the value before saving. */
    public function trim(bool $trim = true): static
    {
        $this->trim = $trim;

        return $this;
    }

    /** Save an empty value as null instead of an empty string. */
    public function nullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;

        return $this;
    }

    /** Uppercase the value before saving. */
    public function uppercase(bool $uppercase = true): static
    {
        $this->uppercase = $uppercase;

        return $this;
    }

    /** Lowercase the value before saving. */
    public function lowercase(bool $lowercase = true): static
    {
        $this->lowercase = $lowercase;

        return $this;
    }

    /** Set static text shown inside the input before the value. */
    public function inputPrefix(?string $prefix): static
    {
        $this->inputPrefix = $prefix;

        return $this;
    }

    /** Set static text shown inside the input after the value. */
    public function inputSuffix(?string $suffix): static
    {
        $this->inputSuffix = $suffix;

        return $this;
    }

    /** Set helper text shown beneath the input. */
    public function helperText(?string $text): static
    {
        $this->helperText = $text;

        return $this;
    }

    /** Set the input's HTML autocomplete attribute. */
    public function autocomplete(?string $autocomplete): static
    {
        $this->autocomplete = $autocomplete;

        return $this;
    }

    /** Focus the input automatically when the cell enters edit mode. */
    public function autofocus(bool $autofocus = true): static
    {
        $this->autofocus = $autofocus;

        return $this;
    }

    // ==========================================
    // Input Appearance
    // ==========================================

    /** Add extra CSS classes to the input element. */
    public function inputClass(?string $class): static
    {
        $this->inputClass = $class;

        return $this;
    }

    public function renderCell(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $state = $this->getState($record);
        $state = $this->formatAfterLoad($state, $record);

        if (! $this->canEdit($record)) {
            return $this->renderReadonlyCell($state, $record);
        }

        return $this->renderEditableCell($state, $record);
    }

    /**
     * @deprecated Renamed to hydrateState() — see formatForSave().
     */
    public function formatAfterLoad(mixed $value, Model $record): mixed
    {
        return $this->hydrateState($value, $record);
    }

    public function hydrateState(mixed $value, ?Model $record = null): mixed
    {
        // Format number for display
        if ($this->decimals !== null && $value !== null && $value !== '') {
            $value = number_format((float) $value, $this->decimals, $this->decimalSeparator, $this->thousandsSeparator);
        }

        if ($this->afterLoadFormatter) {
            $value = ($this->afterLoadFormatter)($value, $record);
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

    public function isReadonly(Model $record): bool
    {
        if ($this->readonlyCallback) {
            return ($this->readonlyCallback)($record);
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
            return (string) ($this->displayFormatter)($value, $record);
        }

        // Format number for readonly display
        if ($this->decimals !== null && $value !== null && $value !== '') {
            return number_format((float) $value, $this->decimals, $this->decimalSeparator, $this->thousandsSeparator);
        }

        return (string) (EnumResolver::display($value) ?? '');
    }

    // §7 boundary evaluation — inline-edit multi-token skeleton (prototype).
    // The editable cell's per-record variation is exactly three values: the record
    // key, the state value, and the row version. `value` appears in TWO encodings
    // (JSON in the Alpine config, HTML attr in data-server-value) from one variable,
    // so distinct control-char sentinels tag each position and are spliced with the
    // matching encoding. Structure is otherwise column-static.
    private const EDIT_VAL = "\x01\x01__WCVAL__\x01\x01";

    // The record key is int-cast by the model, so it cannot be a string sentinel —
    // use a distinctive number. e() is context-free (char-by-char), so splicing
    // e($key) into "tic-<sentinel>-<col>" reproduces e("tic-<key>-<col>") exactly.
    private const EDIT_KEY = '1717171718';

    private const EDIT_VER = '1717171717';

    private ?string $editableSkeleton = null;

    public function renderEditableCellFast(Model $record): string
    {
        $state = $this->getState($record);
        $key = (string) $record->getKey();
        $value = (string) ($state ?? '');
        $version = $this->recordVersion($record);

        return strtr($this->editableSkeleton ??= $this->buildEditableSkeleton(), [
            e(json_encode(self::EDIT_VAL)) => e(json_encode($value)),  // Alpine config
            e(self::EDIT_VAL) => e($value),                           // data-server-value
            self::EDIT_KEY => e($key),                                // wire:key + data-record-key
            self::EDIT_VER => $version,                               // version (numeric)
        ]);
    }

    private function buildEditableSkeleton(): string
    {
        $sentinel = new class extends Model
        {
            protected $guarded = [];
        };
        $sentinel->forceFill([
            'id' => self::EDIT_KEY,
            'updated_at' => Carbon::createFromTimestamp((int) self::EDIT_VER),
        ]);

        return $this->renderEditableCell(self::EDIT_VAL, $sentinel);
    }

    protected function renderEditableCell(mixed $state, Model $record): string
    {
        return $this->renderView('tables.columns.text-input-editable', [
            'column' => $this,
            'record' => $record,
            'state' => $state,
            // Record-invariant primitives resolved once per request (not @included per row).
            'spinnerHtml' => app(Primitives::class)->spinner(),
            'checkHtml' => app(Primitives::class)->successCheck(),
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

    /** @var array<string, string> */
    private array $inputClassesCache = [];

    private ?string $inputAttributesCache = null;

    /**
     * Tier-2g: the input classes/attributes are a pure function of column config, so
     * they are memoised per column instead of rebuilt for every editable cell. (The
     * instance is rebuilt each Livewire render, so no cross-render staleness.)
     */
    public function buildInputClasses(bool $hasPrefix, bool $hasSuffix): string
    {
        return $this->inputClassesCache[((int) $hasPrefix).((int) $hasSuffix)]
            ??= $this->computeInputClasses($hasPrefix, $hasSuffix);
    }

    private function computeInputClasses(bool $hasPrefix, bool $hasSuffix): string
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
        return $this->inputAttributesCache ??= $this->computeInputAttributes();
    }

    private function computeInputAttributes(): string
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

        if ($this->step !== null) {
            $attrs['step'] = $this->step;
        }

        if ($this->min !== null) {
            $attrs['min'] = $this->min;
        }

        if ($this->max !== null) {
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
