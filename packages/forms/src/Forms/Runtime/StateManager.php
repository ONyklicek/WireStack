<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Runtime;

use Livewire\Component;
use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireCore\Foundation\Support\EnumResolver;

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
        // A model with enum-cast attributes fills raw enum instances; collapse them to
        // their scalar key so the bound Livewire state is wire-safe and matches the
        // <option> values, while still round-tripping back to the cast on save.
        $data = $this->normaliseEnums($data);

        $this->container->replaceClean($data);

        if ($this->livewire && $this->statePath) {
            $this->writeLivewireState($this->statePath, $data);
        }
    }

    /**
     * Recursively reduce any enum instances in filled data to their scalar form.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normaliseEnums(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = is_array($value)
                ? $this->normaliseEnums($value)
                : EnumResolver::scalar($value);
        }

        return $data;
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
            $this->writeLivewireState($this->statePath.'.'.$path, $value);
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
            $this->writeLivewireState($this->statePath, $data);
        }
    }

    /**
     * Write a value to the Livewire component at the given dot-notation path.
     *
     * Traverses the path segment by segment. When a StateContainer is encountered
     * at any depth, delegates the remaining sub-path to its set() method — which
     * uses Arr::set internally — instead of data_set(), which cannot write through
     * non-array PHP objects.
     */
    private function writeLivewireState(string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $current = $this->livewire;

        foreach ($segments as $index => $segment) {
            $child = match (true) {
                is_object($current) => $current->{$segment} ?? null,
                is_array($current) => $current[$segment] ?? null,
                default => null,
            };

            if ($child instanceof StateContainer) {
                $subPath = implode('.', array_slice($segments, $index + 1));

                if ($subPath !== '') {
                    $child->set($subPath, $value);
                } else {
                    $child->replace(is_array($value) ? $value : []);
                }

                return;
            }

            $current = $child;
        }

        data_set($this->livewire, $path, $value);
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
