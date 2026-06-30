<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Runtime;

use Illuminate\Validation\ValidationException;
use NyonCode\WireCore\Core\State\StateHydrator;
use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
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
        $resolver = new FormValidationResolver(
            $this->getFlatComponents(),
            $this->config->statePath,
            $this->config->validationMessages,
            $this->getRepeaters(),
        );

        $state = $this->stateManager->getState();
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

        $this->stateManager->fill($data);
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
                $component->prepareChildren($this->config->statePath ?? '', $this->config->isLive, $livewire);
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
