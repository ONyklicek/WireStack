<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\State;

/**
 * Tracks which state paths have been modified.
 *
 * Stores dirty paths and original values so consumers
 * can determine what changed since the last reset.
 */
final class DirtyStateTracker
{
    /** @var array<string, true> */
    private array $dirtyPaths = [];

    /** @var array<string, mixed> */
    private array $originals = [];

    /**
     * Mark a path as dirty (modified).
     */
    public function markDirty(string $path): void
    {
        $this->dirtyPaths[$path] = true;
    }

    /**
     * Check if a specific path (or any path) is dirty.
     *
     * When $path is null, returns true if anything has been modified.
     */
    public function isDirty(?string $path = null): bool
    {
        if ($path === null) {
            return $this->dirtyPaths !== [];
        }

        return isset($this->dirtyPaths[$path]);
    }

    /**
     * Get all paths that have been marked as dirty.
     *
     * @return array<int, string>
     */
    public function getDirtyPaths(): array
    {
        return array_keys($this->dirtyPaths);
    }

    /**
     * Reset dirty tracking, clearing all dirty paths and originals.
     */
    public function reset(): void
    {
        $this->dirtyPaths = [];
        $this->originals = [];
    }

    /**
     * Get the original value for a path before modification.
     */
    public function getOriginal(string $path): mixed
    {
        return $this->originals[$path] ?? null;
    }

    /**
     * Store the original value for a path before modification.
     */
    public function setOriginal(string $path, mixed $value): void
    {
        $this->originals[$path] = $value;
    }
}
