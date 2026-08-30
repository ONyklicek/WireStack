<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Managers;

use NyonCode\WireTable\Exceptions\TableConfigurationException;
use NyonCode\WireTable\Resources\Contracts\DescribesResource;

/**
 * Which resources this application has, and which one owns a given model.
 *
 * Deliberately not a panel builder (ADR 0020 Q4, locked): it holds class names,
 * answers two questions about them, and owns no routing, no URL shell and no
 * navigation tree. Everything it does is answerable from
 * {@see DescribesResource} — the static half of the resource contract — so
 * listing a menu or routing a model never instantiates a resource or composes a
 * table.
 *
 * Registration is a config array first (`config('wire-table.resources')`);
 * attribute discovery is the opt-in second path and belongs with boost's
 * `ComponentScanner`, not here.
 */
final class ResourceRegistry
{
    /** @var array<string, class-string<DescribesResource>> Keyed by resource key. */
    private array $resources = [];

    /**
     * @var array<class-string, class-string<DescribesResource>>|null
     *
     * Model class to resource class. Built on first use rather than on every
     * register() call: an application registers every resource at boot and asks
     * this question at most once per request, so paying per registration would
     * rebuild the map n times to use it once.
     */
    private ?array $byModel = null;

    /**
     * @param  class-string<DescribesResource>  $resource
     *
     * @throws TableConfigurationException When the class is not a resource, or
     *                                     when its key is already taken by a different class.
     */
    public function register(string $resource): void
    {
        if (! is_subclass_of($resource, DescribesResource::class) && ! in_array(DescribesResource::class, class_implements($resource) ?: [], true)) {
            throw TableConfigurationException::notAResource($resource);
        }

        $key = $resource::key();
        $existing = $this->resources[$key] ?? null;

        // Registering the same class twice is idempotent — config merging and a
        // service provider that boots twice in tests both do it. Two *different*
        // classes claiming one key is the real error: the later would silently
        // take over routing for the earlier.
        if ($existing !== null && $existing !== $resource) {
            throw TableConfigurationException::duplicateResourceKey($key, $existing, $resource);
        }

        $this->resources[$key] = $resource;
        $this->byModel = null;
    }

    /**
     * Every registered resource class, keyed by its key.
     *
     * @return array<string, class-string<DescribesResource>>
     */
    public function all(): array
    {
        return $this->resources;
    }

    /** @return class-string<DescribesResource>|null */
    public function find(string $key): ?string
    {
        return $this->resources[$key] ?? null;
    }

    /**
     * The resource owning a model class, or null when nothing claims it.
     *
     * @param  class-string  $model
     * @return class-string<DescribesResource>|null
     */
    public function forModel(string $model): ?string
    {
        return $this->modelMap()[ltrim($model, '\\')] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->resources[$key]);
    }

    /**
     * @return array<class-string, class-string<DescribesResource>>
     */
    private function modelMap(): array
    {
        if ($this->byModel !== null) {
            return $this->byModel;
        }

        $map = [];

        foreach ($this->resources as $resource) {
            $model = $resource::modelClass();

            // A resource over a non-Eloquent source has no model to be found by;
            // it is still registered, listed and routable by key.
            if ($model === null) {
                continue;
            }

            $map[ltrim($model, '\\')] ??= $resource;
        }

        return $this->byModel = $map;
    }
}
