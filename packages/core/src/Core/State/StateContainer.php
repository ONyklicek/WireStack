<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\State;

/**
 * Central state container replacing multiple Livewire properties.
 *
 * Provides a unified interface for managing component state
 * with dot-notation path access and automatic dirty tracking.
 */
final class StateContainer
{
    /** @var array<string, mixed> */
    private array $state;

    private DirtyStateTracker $dirtyTracker;

    /**
     * @param  array<string, mixed>  $initialState
     */
    public function __construct(array $initialState = [])
    {
        $this->state = $initialState;
        $this->dirtyTracker = new DirtyStateTracker;
    }

    /**
     * Get a value at the given path with an optional default.
     */
    public function get(string $path, mixed $default = null): mixed
    {
        if (! StatePathResolver::has($this->state, $path)) {
            return $default;
        }

        return StatePathResolver::resolve($this->state, $path);
    }

    /**
     * Set a value at the given path, tracking the change as dirty.
     */
    public function set(string $path, mixed $value): void
    {
        if (! $this->dirtyTracker->isDirty($path)) {
            $this->dirtyTracker->setOriginal(
                $path,
                StatePathResolver::resolve($this->state, $path),
            );
        }

        StatePathResolver::set($this->state, $path, $value);
        $this->dirtyTracker->markDirty($path);
    }

    /**
     * Check if a value exists at the given path.
     */
    public function has(string $path): bool
    {
        return StatePathResolver::has($this->state, $path);
    }

    /**
     * Remove a value at the given path, tracking the change as dirty.
     */
    public function forget(string $path): void
    {
        if (! $this->dirtyTracker->isDirty($path)) {
            $this->dirtyTracker->setOriginal(
                $path,
                StatePathResolver::resolve($this->state, $path),
            );
        }

        StatePathResolver::forget($this->state, $path);
        $this->dirtyTracker->markDirty($path);
    }

    /**
     * Get the entire state array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->state;
    }

    /**
     * Replace the entire state array, tracking all changed keys as dirty.
     *
     * @param  array<string, mixed>  $state
     */
    public function replace(array $state): void
    {
        $oldKeys = array_keys($this->state);
        $newKeys = array_keys($state);
        $allKeys = array_unique(array_merge($oldKeys, $newKeys));

        foreach ($allKeys as $key) {
            $oldValue = $this->state[$key] ?? null;
            $newValue = $state[$key] ?? null;

            if ($oldValue !== $newValue && ! $this->dirtyTracker->isDirty($key)) {
                $this->dirtyTracker->setOriginal($key, $oldValue);
                $this->dirtyTracker->markDirty($key);
            }
        }

        $this->state = $state;
    }

    /**
     * Replace the entire state array without dirty tracking.
     *
     * Use this for initial state population (e.g., fill) where
     * the new state should be considered the clean baseline.
     *
     * @param  array<string, mixed>  $state
     */
    public function replaceClean(array $state): void
    {
        $this->state = $state;
        $this->dirtyTracker->reset();
    }

    /**
     * Merge state recursively using array_replace_recursive.
     *
     * @param  array<string, mixed>  $state
     */
    public function merge(array $state): void
    {
        $this->state = array_replace_recursive($this->state, $state);
    }

    /**
     * Get the dirty state tracker instance.
     */
    public function getDirtyTracker(): DirtyStateTracker
    {
        return $this->dirtyTracker;
    }
}
