<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\State;

/**
 * Central state container replacing multiple Livewire properties.
 *
 * Provides a unified interface for managing component state
 * with dot-notation path access and automatic dirty tracking.
 */
/** @implements \ArrayAccess<string, mixed> */
final class StateContainer implements \ArrayAccess
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
        // Single traversal — Arr::get returns the default only for missing
        // paths, so a stored null still wins over the default (same semantics
        // as the previous has() + resolve() double walk).
        return StatePathResolver::resolve($this->state, $path, $default);
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
     * Intended ONLY for initial state population at hydration time
     * (e.g. Livewire synthesizer restoring state from a previous request).
     * Calling this mid-request discards any pending dirty changes silently.
     * Use replace() for mutations that should be tracked.
     *
     * @internal
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
     * Tracks top-level keys that actually changed as dirty so that dirty
     * tracking consumers (e.g. audit log, form change detection) see the
     * same information as after an equivalent set() call.
     *
     * @param  array<string, mixed>  $state
     */
    public function merge(array $state): void
    {
        foreach ($state as $key => $value) {
            if (! $this->dirtyTracker->isDirty($key)) {
                $this->dirtyTracker->setOriginal($key, $this->state[$key] ?? null);
            }

            $this->dirtyTracker->markDirty($key);
        }

        $this->state = array_replace_recursive($this->state, $state);
    }

    /**
     * Get the dirty state tracker instance.
     */
    public function getDirtyTracker(): DirtyStateTracker
    {
        return $this->dirtyTracker;
    }

    /**
     * Canonical owner for writing a dot-notation path into a host that may carry
     * StateContainer bags (e.g. a Livewire component whose `tableState` is a
     * StateContainer).
     *
     * Walks the path segment by segment. When a StateContainer is encountered at
     * any depth, the remaining sub-path is delegated to its set() (so dirty
     * tracking still fires), because data_set() cannot write through an
     * overloaded ArrayAccess element by reference — it silently drops the write
     * with an "Indirect modification of overloaded element" notice. Hosts with
     * only plain array/object segments fall through to data_set() unchanged.
     */
    public static function writeInto(object $host, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $current = $host;

        foreach ($segments as $index => $segment) {
            $child = match (true) {
                is_object($current) => $current->{$segment} ?? null,
                is_array($current) => $current[$segment] ?? null,
                default => null,
            };

            if ($child instanceof self) {
                $subPath = implode('.', array_slice($segments, $index + 1));

                if ($subPath === '') {
                    $child->replace(is_array($value) ? $value : []);
                } else {
                    $child->set($subPath, $value);
                }

                return;
            }

            $current = $child;
        }

        data_set($host, $path, $value);
    }

    /**
     * Allow data_get() to traverse top-level state keys as if they were properties.
     */
    public function __get(string $key): mixed
    {
        return $this->state[$key] ?? null;
    }

    /**
     * Allow data_get() / isset() checks on top-level state keys.
     */
    public function __isset(string $key): bool
    {
        return isset($this->state[$key]);
    }

    // ─── ArrayAccess ────────────────────────────────────────────────
    // Allows Arr::get() / Arr::set() (used by Laravel Validator) to traverse
    // top-level keys. Arr::accessible() returns true for ArrayAccess objects,
    // enabling the Validator to read nested paths like
    // "tableState.modal.action.formData.title" through this container.

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->state[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->state[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->state[] = $value;
        } else {
            $this->set((string) $offset, $value);
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->forget((string) $offset);
    }
}
