<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Runtime;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use NyonCode\WireCore\Core\State\StateHydrator;
use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Contracts\HydratesState;
use NyonCode\WireCore\Foundation\Schema\Step;
use NyonCode\WireCore\Foundation\Schema\Wizard;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireForms\Components\Field;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Forms\Config\FormConfig;
use NyonCode\WireForms\Validation\FormValidationResolver;

/**
 * Orchestrates runtime operations: validate, save, getState.
 *
 * @internal This class is not part of the public API.
 */
final class FormRuntime
{
    /** @var array<int, Component>|null */
    private ?array $cachedFlatComponents = null;

    private bool $isPrepared = false;

    public function __construct(
        private readonly FormConfig $config,
        private readonly StateManager $stateManager,
    ) {}

    public function getStateManager(): StateManager
    {
        return $this->stateManager;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(): array
    {
        return $this->runValidation(new FormValidationResolver(
            $this->getFlatComponents(),
            $this->config->statePath,
            $this->config->validationMessages,
            $this->getRepeaters(),
        ));
    }

    /**
     * Validate only the fields inside one wizard step, so "Next" can gate on the
     * current step without flagging untouched later steps. A missing wizard or
     * out-of-range step index validates nothing (returns an empty array).
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateWizardStep(int $stepIndex, ?string $wizardName = null): array
    {
        $step = $this->findWizardStep($stepIndex, $wizardName);

        if ($step === null) {
            return [];
        }

        return $this->runValidation(new FormValidationResolver(
            $this->flattenComponents($step->getSchema()),
            $this->config->statePath,
            $this->config->validationMessages,
            $this->collectRepeaters($step->getSchema()),
        ));
    }

    /**
     * Absolute state paths owned by one wizard step's fields (wildcards for
     * repeaters), so the host can clear that step's stale error-bag entries
     * before revalidating.
     *
     * @return array<int, string>
     */
    public function getWizardStepFieldPaths(int $stepIndex, ?string $wizardName = null): array
    {
        return $this->findWizardStep($stepIndex, $wizardName)?->getDescendantFieldStatePaths() ?? [];
    }

    /**
     * Whether the schema contains a wizard — optionally one with the given name.
     */
    public function hasWizard(?string $wizardName = null): bool
    {
        $this->prepare();

        return $this->findWizard($this->config->schema, $wizardName) !== null;
    }

    private function findWizardStep(int $stepIndex, ?string $wizardName): ?Step
    {
        $this->prepare();

        $wizard = $this->findWizard($this->config->schema, $wizardName);

        // getSteps() re-indexes over *visible* steps, matching the client's
        // rendered step order.
        return $wizard?->getSteps()[$stepIndex] ?? null;
    }

    /**
     * Locate a wizard in the schema, descending through nested layouts. With a
     * name, only a wizard of that name matches; without, the first one wins.
     *
     * @param  array<int, mixed>  $components
     */
    private function findWizard(array $components, ?string $wizardName): ?Wizard
    {
        foreach ($components as $component) {
            if ($component instanceof Wizard
                && ($wizardName === null || $component->getName() === $wizardName)
            ) {
                return $component;
            }

            if ($component instanceof LayoutComponent) {
                $found = $this->findWizard($component->getSchema(), $wizardName);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Run a prepared resolver through the host (Livewire) or a standalone
     * validator — the shared tail of full-form and step-scoped validation.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function runValidation(FormValidationResolver $resolver): array
    {
        $rules = $resolver->getRules();
        $messages = $resolver->getMessages();
        $attributes = $resolver->getAttributes();

        if ($this->stateManager->hasLivewire()) {
            return $this->stateManager->getLivewire()->validate(
                $rules,
                $messages,
                $attributes,
            );
        }

        // In standalone mode, nest state under statePath so rules like "data.name" match
        $state = $this->stateManager->getState();

        if ($this->config->statePath) {
            $validationData = [];
            data_set($validationData, $this->config->statePath, $state);
        } else {
            $validationData = $state;
        }

        return app('validator')->make($validationData, $rules, $messages)
            ->setAttributeNames($attributes)
            ->validate();
    }

    public function save(): mixed
    {
        $handler = new SaveHandler($this->config, $this);

        return $handler->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function fill(array $data): void
    {
        $definitions = $this->buildStateDefinitions();

        if (! empty($definitions)) {
            $data = (new StateHydrator)->hydrate($data, $definitions);
        }

        // Then let each field shape the cast value into its own state — the read
        // counterpart of SaveHandler's dehydration step (ADR 0021). Runs after
        // StateHydrator so a field receives the type-correct value, not raw input.
        $data = $this->hydrateFields($data);

        // Seed every schema key the caller did not provide with its field
        // ->default() (create-mode intent) or, failing that, its type-correct
        // blank. The union keeps incoming values, so a record's persisted value —
        // even an intentional null — is never overwritten by a default; only
        // genuinely-absent keys (create mode, or new/virtual fields the record
        // has no column for) fall back. This makes a plain ->fill() self-seed:
        // callers no longer have to list every field just so array inputs get an
        // array instead of collapsing.
        $data += $this->getInitialState();

        // Opt-in only: a field marked ->defaultOnNull() treats an edit-mode null
        // (or empty string) as unset and takes its default too. The union above
        // has already covered absent keys; this covers present-but-empty ones.
        $data = $this->applyDefaultOnNull($data);

        $data = $this->captureOptimisticLockBaseline($data);

        $this->stateManager->fill($data);
    }

    /**
     * Fill the default of every ->defaultOnNull() field whose current value is
     * null or an empty string. Scoped to opted-in fields so no other field's
     * intentional null is ever overwritten.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyDefaultOnNull(array $data): array
    {
        foreach ($this->getFlatComponents() as $component) {
            if (! $component instanceof Field || ! $component->isDefaultOnNull()) {
                continue;
            }

            $name = $component->getName();

            if ($name === '' || ! in_array($data[$name] ?? null, [null, ''], true)) {
                continue;
            }

            $default = EnumResolver::scalarDeep($component->getDefault());

            if ($default !== null) {
                $data[$name] = $default;
            }
        }

        return $data;
    }

    /**
     * The schema-derived initial state: a key for every field (and top-level
     * repeater) mapped to its ->default() when set, otherwise its type-correct
     * blank. This is the single canonical seed both {@see fill()} and modal
     * action hosts layer their fill/record/fillFormUsing data on top of.
     *
     * Read-only: it walks the raw schema without preparing the render-bound
     * component tree, so a host can seed the Livewire state bag at mount without
     * side effects on the instance that later renders.
     *
     * @return array<string, mixed>
     */
    public function getInitialState(): array
    {
        [$blank, $defaults, $types] = $this->collectInitialState($this->config->schema);

        // Defaults win over the structural blank; every field still has a key.
        $seed = $defaults + $blank;

        if ($types !== [] && $seed !== []) {
            $seed = (new StateHydrator)->hydrate($seed, $types);
        }

        return $seed;
    }

    /**
     * Walk the schema collecting each stateful component's blank value, its
     * explicit ->default() (only when non-null — an unset default must not
     * override a field's structural blank), and its hydration type hint.
     *
     * Layouts are descended into; a repeater is treated as a leaf whose own key
     * seeds to its default or an empty array — its per-item children live at
     * wildcard paths and are seeded when rows are added, never at the template
     * path.
     *
     * @param  array<int, mixed>  $components
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, string>}
     */
    private function collectInitialState(array $components): array
    {
        $blank = [];
        $defaults = [];
        $types = [];

        foreach ($components as $component) {
            if ($component instanceof Repeater) {
                $name = $component->getName();

                if ($name !== '') {
                    $blank[$name] = [];
                    $types[$name] = 'array';

                    $default = EnumResolver::scalarDeep($component->getDefault());

                    if ($default !== null) {
                        $defaults[$name] = $default;
                    }
                }

                continue;
            }

            if ($component instanceof LayoutComponent) {
                [$nestedBlank, $nestedDefaults, $nestedTypes] = $this->collectInitialState($component->getSchema());

                $blank += $nestedBlank;
                $defaults += $nestedDefaults;
                $types += $nestedTypes;

                continue;
            }

            if ($component instanceof Field) {
                $name = $component->getName();

                if ($name !== '') {
                    $blank[$name] = $component->getBlankState();

                    // An enum-cast column's ->default(Status::Draft) is an enum instance;
                    // the seed feeds Livewire state directly, so it must be scalar here for
                    // the same reason fill() normalises (see StateManager::fill()).
                    $default = EnumResolver::scalarDeep($component->getDefault());

                    if ($default !== null) {
                        $defaults[$name] = $default;
                    }

                    $type = $component->getStateType();

                    if ($type !== 'string') {
                        $types[$name] = $type;
                    }
                }
            }
        }

        return [$blank, $defaults, $types];
    }

    /**
     * When optimistic locking is enabled and a persisted model is configured,
     * snapshot the lock column's raw database value into form state so the
     * save-time comparison has a format-stable baseline that survives the
     * Livewire round trip. No-op when locking is off or the model is not yet
     * persisted (create mode).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function captureOptimisticLockBaseline(array $data): array
    {
        $column = $this->config->optimisticLockColumn;

        if ($column === null) {
            return $data;
        }

        $model = $this->config->model;

        if ($model instanceof Model && $model->exists) {
            $data[$column] = $model->getRawOriginal($column);
        }

        return $data;
    }

    /**
     * Collect state type hints from all field components in the schema.
     *
     * @return array<string, string>
     */
    /**
     * Apply every field's own hydration to the state about to be filled.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function hydrateFields(array $data): array
    {
        $record = $this->config->model instanceof Model ? $this->config->model : null;

        foreach ($this->getFlatComponents() as $component) {
            if (! $component instanceof HydratesState || ! $component instanceof Field) {
                continue;
            }

            $name = $component->getName();

            if ($name === '' || ! array_key_exists($name, $data)) {
                continue;
            }

            $data[$name] = $component->hydrateState($data[$name], $record);
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function buildStateDefinitions(): array
    {
        $definitions = [];

        foreach ($this->getFlatComponents() as $component) {
            if ($component instanceof Field) {
                $name = $component->getName();
                $type = $component->getStateType();

                if ($name !== '' && $type !== 'string') {
                    $definitions[$name] = $type;
                }
            }
        }

        return $definitions;
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        return $this->stateManager->getState();
    }

    /**
     * Prepare all components with state paths and disabled state.
     */
    public function prepare(): void
    {
        if ($this->isPrepared) {
            return;
        }

        $livewire = $this->stateManager->getLivewire();

        foreach ($this->config->schema as $component) {
            if ($component instanceof LayoutComponent) {
                $component->prepareChildren($this->config->statePath ?? '', $this->config->isLive, $livewire, $this->config->isDisabled);
            } elseif ($component instanceof Component) {
                if ($this->config->statePath) {
                    $component->statePath($this->config->statePath);
                }
                if ($this->config->isDisabled) {
                    $component->disabled();
                }
                if ($this->config->isLive && method_exists($component, 'live')) {
                    $component->live();
                }
                if ($livewire !== null) {
                    $component->livewire($livewire);
                }
            }
        }

        $this->isPrepared = true;

        // Give relationship-aware fields (e.g. BelongsToSelect) the form's parent
        // record, so they can resolve their related model — both to auto-load
        // options and to auto-create a new option from createOptionForm() without
        // an explicit createOptionUsing(). Only a Model instance carries a usable
        // relationship; a bare class string is instantiated for introspection.
        // Runs after isPrepared is set so getFlatComponents() (which calls
        // prepare()) short-circuits instead of recursing.
        $this->propagateRecordToFields();
    }

    /**
     * Hand the form's parent record to every field exposing a record() setter.
     */
    private function propagateRecordToFields(): void
    {
        $model = $this->config->model;

        $record = match (true) {
            $model instanceof Model => $model,
            is_string($model) && is_a($model, Model::class, true) => new $model,
            default => null,
        };

        if ($record === null) {
            return;
        }

        $this->applyRecordToSchema($this->config->schema, $record);
    }

    /**
     * Recursively set the parent record on record-aware fields throughout the
     * schema — including inside layouts and Repeater templates. A Repeater is a
     * LayoutComponent, so descending into its template schema is enough: each
     * per-item field is a (deep) clone of that template built in
     * getItemSchema(), and cloning carries the record reference into every item
     * (and hence into a repeater item's create/edit-option flow).
     *
     * @param  array<int, mixed>  $components
     */
    private function applyRecordToSchema(array $components, Model $record): void
    {
        foreach ($components as $component) {
            if (method_exists($component, 'record')) {
                $component->record($record);
            }

            if ($component instanceof LayoutComponent) {
                $this->applyRecordToSchema($component->getSchema(), $record);
            }
        }
    }

    /**
     * Get flat list of all field components (recursively flattened).
     *
     * @return array<int, Component>
     */
    public function getFlatComponents(): array
    {
        if ($this->cachedFlatComponents !== null) {
            return $this->cachedFlatComponents;
        }

        $this->prepare();

        return $this->cachedFlatComponents = $this->flattenComponents($this->config->schema);
    }

    /**
     * @param  array<int, mixed>  $components
     * @return array<int, Component>
     */
    private function flattenComponents(array $components): array
    {
        $flat = [];

        foreach ($components as $component) {
            if ($component instanceof Repeater) {
                // A repeater is a leaf for flattening: its template children live at
                // per-item wildcard paths and are validated via getRepeaters(), not
                // as flat fields at the (wrong) template path.
                continue;
            }

            if ($component instanceof LayoutComponent) {
                $flat = array_merge($flat, $this->flattenComponents($component->getSchema()));
            } elseif ($component instanceof Component) {
                $flat[] = $component;
            }
        }

        return $flat;
    }

    /**
     * Locate the component bound to the given absolute state path.
     *
     * Flat fields (including layout-wrapped ones) are matched directly; paths
     * inside repeater items — `{repeater}.{index}.{field…}` — resolve through
     * the repeater's prepared per-item schema, so reactive dispatch (field
     * actions, live validation, afterStateUpdated, remote search) reaches
     * fields inside repeater items even though flattening treats a repeater as
     * a leaf. Nested repeaters resolve recursively. Returns null when no
     * component owns the path.
     */
    public function findComponentByStatePath(string $absolutePath): ?Component
    {
        foreach ($this->getFlatComponents() as $component) {
            if ($component->getStatePath() === $absolutePath) {
                return $component;
            }
        }

        return $this->findInRepeaters($this->getRepeaters(), $absolutePath);
    }

    /**
     * @param  array<int, Repeater>  $repeaters
     */
    private function findInRepeaters(array $repeaters, string $absolutePath): ?Component
    {
        foreach ($repeaters as $repeater) {
            $base = $repeater->getStatePath().'.';

            if (! str_starts_with($absolutePath, $base)) {
                continue;
            }

            // The segment right after the repeater path must be an item index.
            $index = explode('.', substr($absolutePath, strlen($base)), 2)[0];

            if (! ctype_digit($index)) {
                continue;
            }

            $found = $this->searchItemComponents($repeater->getItemSchema((int) $index), $absolutePath);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Search prepared item-schema clones (descending into layouts, recursing
     * into nested repeaters) for the component bound to the path.
     *
     * @param  array<int, mixed>  $components
     */
    private function searchItemComponents(array $components, string $absolutePath): ?Component
    {
        $nestedRepeaters = [];

        foreach ($components as $component) {
            if ($component instanceof Repeater) {
                $nestedRepeaters[] = $component;

                continue;
            }

            if ($component instanceof LayoutComponent) {
                $found = $this->searchItemComponents($component->getSchema(), $absolutePath);

                if ($found !== null) {
                    return $found;
                }

                continue;
            }

            if ($component instanceof Component && $component->getStatePath() === $absolutePath) {
                return $component;
            }
        }

        return $this->findInRepeaters($nestedRepeaters, $absolutePath);
    }

    /**
     * Collect all repeaters in the schema (recursively), after preparation so
     * each repeater reports its resolved, prefixed state path.
     *
     * @return array<int, Repeater>
     */
    public function getRepeaters(): array
    {
        $this->prepare();

        return $this->collectRepeaters($this->config->schema);
    }

    /**
     * @param  array<int, mixed>  $components
     * @return array<int, Repeater>
     */
    private function collectRepeaters(array $components): array
    {
        $repeaters = [];

        foreach ($components as $component) {
            if ($component instanceof Repeater) {
                $repeaters[] = $component;
            } elseif ($component instanceof LayoutComponent) {
                $repeaters = array_merge($repeaters, $this->collectRepeaters($component->getSchema()));
            }
        }

        return $repeaters;
    }
}
