<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Runtime;

use Illuminate\Validation\ValidationException;
use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
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

        // In standalone mode, wrap state under statePath so rules like "data.name" match
        $validationData = $this->config->statePath
            ? [$this->config->statePath => $state]
            : $state;

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
        $this->stateManager->fill($data);
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

        foreach ($this->config->schema as $component) {
            if ($component instanceof LayoutComponent) {
                $component->prepareChildren($this->config->statePath ?? '');
            } elseif ($component instanceof Component) {
                if ($this->config->statePath) {
                    $component->statePath($this->config->statePath);
                }
                if ($this->config->isDisabled) {
                    $component->disabled();
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
            if ($component instanceof LayoutComponent) {
                $flat = array_merge($flat, $this->flattenComponents($component->getSchema()));
            } elseif ($component instanceof Component) {
                $flat[] = $component;
            }
        }

        return $flat;
    }
}
