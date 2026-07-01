<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use Livewire\Component;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireForms\Concerns\HasOptions;
use NyonCode\WireForms\Concerns\InteractsWithSelectCreation;
use NyonCode\WireForms\Contracts\ProvidesImplicitValidationRules;
use NyonCode\WireForms\Forms\Form;

/**
 * Select / dropdown field with search, multiple selection, native mode, and relationship support.
 *
 * Beyond client-side filtering of a preloaded option list, the field supports
 * Filament-style server-driven behavior:
 *  - {@see getSearchResultsUsing()} turns on *remote* search — the dropdown asks
 *    the server for matches as the user types instead of filtering in the browser.
 *  - {@see getOptionLabelUsing()} / {@see getOptionLabelsUsing()} resolve the
 *    label(s) for the currently selected value(s) so the trigger stays readable
 *    even when the matching option was never preloaded.
 *  - {@see preload()} eagerly seeds the remote option list on render.
 *  - {@see createOptionForm()} / {@see createOptionUsing()} let the user create a
 *    new option from a modal form and have it selected immediately.
 */
class Select extends Field implements ProvidesImplicitValidationRules
{
    use HasOptions;

    protected bool $searchable = false;

    protected bool $multiple = false;

    protected bool $native = false;

    protected bool $preload = false;

    protected ?Closure $searchResultsCallback = null;

    protected ?Closure $optionLabelCallback = null;

    protected ?Closure $optionLabelsCallback = null;

    /** @var array<int, mixed>|Closure|null Create-option modal form schema. */
    protected array|Closure|null $createOptionSchema = null;

    protected ?Closure $createOptionCallback = null;

    protected ?string $createOptionModalHeading = null;

    /** @var array<int, mixed>|Closure|null Edit-option modal form schema. */
    protected array|Closure|null $editOptionSchema = null;

    protected ?Closure $fillEditOptionCallback = null;

    protected ?Closure $updateOptionCallback = null;

    protected ?string $editOptionModalHeading = null;

    protected ?int $maxItems = null;

    protected ?int $minItems = null;

    protected ?string $noSearchResultsMessage = null;

    protected ?string $loadingMessage = null;

    protected ?string $searchPrompt = null;

    protected bool $allowHtml = false;

    /** @var array<string|int>|Closure */
    protected array|Closure $disabledOptionValues = [];

    protected ?string $relationship = null;

    protected ?string $titleAttribute = null;

    public function searchable(bool $condition = true): static
    {
        $this->searchable = $condition;

        return $this;
    }

    public function multiple(bool $condition = true): static
    {
        $this->multiple = $condition;

        return $this;
    }

    public function native(bool $condition = true): static
    {
        $this->native = $condition;

        return $this;
    }

    /**
     * Resolve matching options on the server as the user types (remote search).
     *
     * The callback receives the current `$search` term (plus the usual reactive
     * `$get` / `$set` accessors) and returns a `[value => label]` map. Enabling
     * it implies {@see searchable()} — a native `<select>` cannot search remotely.
     */
    public function getSearchResultsUsing(Closure $callback): static
    {
        $this->searchResultsCallback = $callback;
        $this->searchable = true;

        return $this;
    }

    /**
     * Resolve the display label for a single selected value that may not be in
     * the preloaded option list (single-select remote search).
     *
     * The callback receives the selected `$value` and returns its label.
     */
    public function getOptionLabelUsing(Closure $callback): static
    {
        $this->optionLabelCallback = $callback;

        return $this;
    }

    /**
     * Resolve display labels for the selected values of a multi-select that may
     * not be in the preloaded option list (multiple remote search).
     *
     * The callback receives the selected `$values` array and returns a
     * `[value => label]` map.
     */
    public function getOptionLabelsUsing(Closure $callback): static
    {
        $this->optionLabelsCallback = $callback;

        return $this;
    }

    /**
     * Eagerly seed the remote option list on render instead of waiting for the
     * first search. No-op unless {@see getSearchResultsUsing()} is set.
     */
    public function preload(bool $condition = true): static
    {
        $this->preload = $condition;

        return $this;
    }

    /**
     * Let the user create a new option from a modal form.
     *
     * Accepts the same schema shapes as an action modal form: an array of field
     * components, or a Closure returning one. Pair with {@see createOptionUsing()}
     * to persist the submitted data. So the newly-created value renders a label,
     * combine with {@see getOptionLabelUsing()} (or a preloaded option list).
     *
     * @param  array<int, mixed>|Closure  $schema
     */
    public function createOptionForm(array|Closure $schema): static
    {
        $this->createOptionSchema = $schema;
        // The "+ Create" affordance lives in the searchable combobox panel; a
        // native <select> has nowhere to host it.
        $this->searchable = true;

        return $this;
    }

    /**
     * Persist a new option created through {@see createOptionForm()}.
     *
     * The callback receives the validated form `$data` and returns the new
     * option's value — a scalar key, or a model whose key is used.
     */
    public function createOptionUsing(Closure $callback): static
    {
        $this->createOptionCallback = $callback;

        return $this;
    }

    public function createOptionModalHeading(?string $heading): static
    {
        $this->createOptionModalHeading = $heading;

        return $this;
    }

    /**
     * Let the user edit the currently selected option from a modal form.
     *
     * Accepts the same schema shapes as {@see createOptionForm()}. Pair with
     * {@see fillEditOptionUsing()} to load the existing record into the form and
     * {@see updateOptionUsing()} to persist the change. Single-select only.
     *
     * @param  array<int, mixed>|Closure  $schema
     */
    public function editOptionForm(array|Closure $schema): static
    {
        $this->editOptionSchema = $schema;
        // The "Edit" affordance lives in the searchable combobox panel.
        $this->searchable = true;

        return $this;
    }

    /**
     * Load the existing record into the edit-option form.
     *
     * The callback receives the selected `$value` and returns a `[field => value]`
     * array to fill the modal form.
     */
    public function fillEditOptionUsing(Closure $callback): static
    {
        $this->fillEditOptionCallback = $callback;

        return $this;
    }

    /**
     * Persist an option edited through {@see editOptionForm()}.
     *
     * The callback receives the selected `$value` and the validated form `$data`.
     */
    public function updateOptionUsing(Closure $callback): static
    {
        $this->updateOptionCallback = $callback;

        return $this;
    }

    public function editOptionModalHeading(?string $heading): static
    {
        $this->editOptionModalHeading = $heading;

        return $this;
    }

    public function maxItems(?int $count): static
    {
        $this->maxItems = $count;

        return $this;
    }

    public function minItems(?int $count): static
    {
        $this->minItems = $count;

        return $this;
    }

    public function noSearchResultsMessage(?string $message): static
    {
        $this->noSearchResultsMessage = $message;

        return $this;
    }

    public function loadingMessage(?string $message): static
    {
        $this->loadingMessage = $message;

        return $this;
    }

    public function searchPrompt(?string $prompt): static
    {
        $this->searchPrompt = $prompt;

        return $this;
    }

    public function allowHtml(bool $condition = true): static
    {
        $this->allowHtml = $condition;

        return $this;
    }

    /**
     * @param  array<string|int>|Closure  $values  Option keys that should be rendered as disabled.
     */
    public function disabledOptions(array|Closure $values): static
    {
        $this->disabledOptionValues = $values;

        return $this;
    }

    public function relationship(?string $name, ?string $titleAttribute = null): static
    {
        $this->relationship = $name;
        $this->titleAttribute = $titleAttribute;

        return $this;
    }

    public function boolean(): static
    {
        $this->options(function () {
            try {
                $yes = trans('wire-forms::fields.yes');
                $no = trans('wire-forms::fields.no');
            } catch (\Throwable) {
                $yes = 'Yes';
                $no = 'No';
            }

            return [
                true => $yes,
                false => $no,
            ];
        });

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function isNative(): bool
    {
        return $this->native;
    }

    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    public function getMinItems(): ?int
    {
        return $this->minItems;
    }

    public function getNoSearchResultsMessage(): string
    {
        return $this->noSearchResultsMessage ?? trans('wire-forms::fields.no_results');
    }

    public function getLoadingMessage(): string
    {
        return $this->loadingMessage ?? trans('wire-forms::fields.loading');
    }

    public function getSearchPrompt(): string
    {
        return $this->searchPrompt ?? trans('wire-forms::fields.search');
    }

    public function isAllowHtml(): bool
    {
        return $this->allowHtml;
    }

    /**
     * @return array<string|int>
     */
    public function getDisabledOptionValues(): array
    {
        return $this->evaluate($this->disabledOptionValues);
    }

    public function getRelationship(): ?string
    {
        return $this->relationship;
    }

    public function getTitleAttribute(): ?string
    {
        return $this->titleAttribute;
    }

    public function isPreloaded(): bool
    {
        return $this->preload;
    }

    public function hasSearchResultsCallback(): bool
    {
        return $this->searchResultsCallback !== null;
    }

    /**
     * Whether the dropdown searches on the server rather than filtering a
     * preloaded list client-side. Native selects never search remotely.
     */
    public function isRemoteSearch(): bool
    {
        return $this->hasSearchResultsCallback() && $this->isSearchable() && ! $this->isNative();
    }

    /**
     * Run the remote-search callback for the given term.
     *
     * @return array<string|int, string>
     */
    public function getSearchResults(string $search): array
    {
        if ($this->searchResultsCallback === null) {
            return [];
        }

        // Normalize first so an enum class-string result expands to a label map,
        // then guard: anything that is not a [value => label] array becomes empty.
        $results = EnumResolver::normalizeOptions($this->evaluate($this->searchResultsCallback, ['search' => $search]));

        return is_array($results) ? $results : [];
    }

    /**
     * Option map handed to the client on render: the full list for a client-side
     * select, or (for remote search) only the eager seed when preloading.
     *
     * @return array<string|int, string>
     */
    public function getPreloadedOptions(): array
    {
        if (! $this->isRemoteSearch()) {
            return $this->getOptions();
        }

        return $this->isPreloaded() ? $this->getSearchResults('') : [];
    }

    /**
     * Resolve the display label for a single selected value, falling back to the
     * preloaded option map when no dedicated callback is set.
     */
    public function getOptionLabel(string|int|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($this->optionLabelCallback !== null) {
            $label = $this->evaluate($this->optionLabelCallback, ['value' => $value]);

            return $label === null ? null : (string) $label;
        }

        return $this->getOptions()[$value] ?? null;
    }

    /**
     * Resolve display labels for the selected values of a multi-select.
     *
     * @param  array<int, string|int>  $values
     * @return array<string|int, string>
     */
    public function getOptionLabels(array $values): array
    {
        if ($this->optionLabelsCallback !== null) {
            $labels = $this->evaluate($this->optionLabelsCallback, ['values' => $values]);

            return EnumResolver::normalizeOptions(is_array($labels) ? $labels : []);
        }

        $labels = [];

        foreach ($values as $value) {
            $label = $this->getOptionLabel($value);

            if ($label !== null) {
                $labels[$value] = $label;
            }
        }

        return $labels;
    }

    /**
     * Label map for the field's current selection, used to keep the trigger
     * readable when the option was never preloaded.
     *
     * @param  mixed  $value  The field's current bound state.
     * @return array<string|int, string>
     */
    public function getSelectedOptionLabels(mixed $value): array
    {
        if ($this->isMultiple()) {
            $values = array_values(array_filter(
                is_array($value) ? $value : [],
                static fn ($item): bool => $item !== null && $item !== '',
            ));

            return $values === [] ? [] : $this->getOptionLabels($values);
        }

        if (is_array($value)) {
            return [];
        }

        $label = $this->getOptionLabel($value);

        return $label === null ? [] : [$value => $label];
    }

    // ─── Create option modal ─────────────────────────────────────────

    /**
     * State-path prefix for the create-option modal form, shared with the host
     * {@see InteractsWithSelectCreation} trait.
     */
    public const CREATE_OPTION_STATE_PATH = 'createOptionFormData';

    public function hasCreateOptionForm(): bool
    {
        return $this->createOptionSchema !== null;
    }

    public function getCreateOptionModalHeading(): string
    {
        return $this->createOptionModalHeading ?? trans('wire-forms::fields.create_option');
    }

    /**
     * Build the create-option modal form, bound to the host component and the
     * shared create-option state bag. Returns null when no schema is configured.
     */
    public function getCreateOptionForm(?Component $livewire = null): ?Form
    {
        if ($this->createOptionSchema === null) {
            return null;
        }

        $schema = $this->createOptionSchema instanceof Closure
            ? $this->evaluate($this->createOptionSchema)
            : $this->createOptionSchema;

        if (! is_array($schema)) {
            return null;
        }

        $form = Form::make()->schema($schema)->statePath(self::CREATE_OPTION_STATE_PATH);

        if ($livewire !== null) {
            $form->livewire($livewire);
        }

        return $form;
    }

    /**
     * Persist a new option from validated modal data, returning the raw creation
     * result (a value, or a model). The host normalizes it to a selectable key
     * via {@see normalizeOptionValue()}. Returns `mixed` so relationship variants
     * (e.g. BelongsToSelect returning a Model) can override without breaking LSP.
     *
     * @param  array<string, mixed>  $data
     */
    public function createOption(array $data): mixed
    {
        if ($this->createOptionCallback === null) {
            return null;
        }

        return $this->evaluate($this->createOptionCallback, ['data' => $data]);
    }

    /**
     * Reduce a creation result to a selectable option key: a scalar key as-is, a
     * model's primary key via getKey(), or null for anything else.
     */
    public static function normalizeOptionValue(mixed $result): string|int|null
    {
        if (is_object($result) && method_exists($result, 'getKey')) {
            $result = $result->getKey();
        }

        return is_string($result) || is_int($result) ? $result : null;
    }

    // ─── Edit option modal ───────────────────────────────────────────

    /**
     * State-path prefix for the edit-option modal form, shared with the host
     * {@see InteractsWithSelectCreation} trait.
     */
    public const EDIT_OPTION_STATE_PATH = 'editOptionFormData';

    public function hasEditOptionForm(): bool
    {
        return $this->editOptionSchema !== null;
    }

    public function getEditOptionModalHeading(): string
    {
        return $this->editOptionModalHeading ?? trans('wire-forms::fields.edit_option');
    }

    /**
     * Build the edit-option modal form, bound to the host component and the shared
     * edit-option state bag. Returns null when no schema is configured.
     */
    public function getEditOptionForm(?Component $livewire = null): ?Form
    {
        if ($this->editOptionSchema === null) {
            return null;
        }

        $schema = $this->editOptionSchema instanceof Closure
            ? $this->evaluate($this->editOptionSchema)
            : $this->editOptionSchema;

        if (! is_array($schema)) {
            return null;
        }

        $form = Form::make()->schema($schema)->statePath(self::EDIT_OPTION_STATE_PATH);

        if ($livewire !== null) {
            $form->livewire($livewire);
        }

        return $form;
    }

    /**
     * Load the existing record for the given selected value into a `[field => value]`
     * array used to fill the edit-option form.
     *
     * @return array<string, mixed>
     */
    public function getEditOptionFormData(string|int $value): array
    {
        if ($this->fillEditOptionCallback === null) {
            return [];
        }

        $data = $this->evaluate($this->fillEditOptionCallback, ['value' => $value]);

        return is_array($data) ? $data : [];
    }

    /**
     * Persist an edit to the option identified by $value using validated data.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateOption(string|int $value, array $data): void
    {
        if ($this->updateOptionCallback === null) {
            return;
        }

        $this->evaluate($this->updateOptionCallback, ['value' => $value, 'data' => $data]);
    }

    public function getStateType(): string
    {
        // Multi-selects bind an array; normalize state so a stray scalar is
        // wrapped rather than left as a string the <select multiple> can't use.
        return $this->isMultiple() ? 'array' : 'string';
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.select';
    }
}
