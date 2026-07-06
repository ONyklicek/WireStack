<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Runtime;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use NyonCode\WireCore\Core\State\StateHydrator;
use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Schema\Step;
use NyonCode\WireCore\Foundation\Schema\Wizard;
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

        $data = $this->captureOptimisticLockBaseline($data);

        $this->stateManager->fill($data);
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
