<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Runtime;

use Livewire\Component;
use NyonCode\WireCore\Core\State\StateContainer;

/**
 * Manages form state and wire:model bindings.
 *
 * Delegates state storage to the shared Core StateContainer,
 * adding Livewire synchronization on top.
 *
 * @internal This class is not part of the public API.
 */
final class StateManager
{
    private StateContainer $container;

    private ?Component $livewire = null;

    private ?string $statePath = null;

    public function __construct()
    {
        $this->container = new StateContainer;
    }

    public function setLivewire(?Component $livewire): void
    {
        $this->livewire = $livewire;
    }

    public function setStatePath(?string $path): void
    {
        $this->statePath = $path;
    }

    public function getStatePath(): ?string
    {
        return $this->statePath;
    }

    /**
     * Get the underlying StateContainer.
     */
    public function getContainer(): StateContainer
    {
        return $this->container;
    }

    /**
     * Fill the state with initial data (clean baseline, no dirty tracking).
     *
     * @param  array<string, mixed>  $data
     */
    public function fill(array $data): void
    {
        $this->container->replaceClean($data);

        if ($this->livewire && $this->statePath) {
            data_set($this->livewire, $this->statePath, $data);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        if ($this->livewire && $this->statePath) {
            return (array) data_get($this->livewire, $this->statePath, []);
        }

        return $this->container->all();
    }

    /**
     * Get a single value from state using dot notation.
     */
    public function get(string $path, mixed $default = null): mixed
    {
        if ($this->livewire && $this->statePath) {
            return data_get($this->livewire, $this->statePath.'.'.$path, $default);
        }

        return $this->container->get($path, $default);
    }

    /**
     * Set a single value in state using dot notation.
     */
    public function set(string $path, mixed $value): void
    {
        $this->container->set($path, $value);

        if ($this->livewire && $this->statePath) {
            data_set($this->livewire, $this->statePath.'.'.$path, $value);
        }
    }

    /**
     * Set the entire state (tracks changes as dirty).
     *
     * @param  array<string, mixed>  $data
     */
    public function setState(array $data): void
    {
        $this->container->replace($data);

        if ($this->livewire && $this->statePath) {
            data_set($this->livewire, $this->statePath, $data);
        }
    }

    /**
     * Check if any state values have been modified.
     */
    public function isDirty(): bool
    {
        return ! empty($this->container->getDirtyTracker()->getDirtyPaths());
    }

    /**
     * Get the list of dirty (modified) paths.
     *
     * @return array<int, string>
     */
    public function getDirtyPaths(): array
    {
        return $this->container->getDirtyTracker()->getDirtyPaths();
    }

    public function hasLivewire(): bool
    {
        return $this->livewire !== null;
    }

    public function getLivewire(): ?Component
    {
        return $this->livewire;
    }
}
