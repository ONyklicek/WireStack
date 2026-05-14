<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Metadata;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;

/**
 * Cache layer for metadata registry.
 *
 * Uses Laravel cache with keys: wire_meta:{model}:{hash}
 */
final class MetadataCache
{
    private const PREFIX = 'wire_meta';

    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function getModelMetadata(string $modelClass): ?ModelMetadata
    {
        $key = $this->buildKey($modelClass, 'model');

        /** @var ModelMetadata|null */
        return $this->cache->get($key);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function putModelMetadata(string $modelClass, ModelMetadata $metadata): void
    {
        $key = $this->buildKey($modelClass, 'model');
        $this->cache->forever($key, $metadata);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, RelationMetadata>  $relations
     */
    public function putRelations(string $modelClass, array $relations): void
    {
        $key = $this->buildKey($modelClass, 'relations');
        $this->cache->forever($key, $relations);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<string, RelationMetadata>|null
     */
    public function getRelations(string $modelClass): ?array
    {
        $key = $this->buildKey($modelClass, 'relations');

        /** @var array<string, RelationMetadata>|null */
        return $this->cache->get($key);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function forget(string $modelClass): void
    {
        $this->cache->forget($this->buildKey($modelClass, 'model'));
        $this->cache->forget($this->buildKey($modelClass, 'relations'));
    }

    public function flush(): void
    {
        // Note: full flush relies on cache store supporting tags or manual tracking
        // For now, individual models must be invalidated explicitly
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function buildKey(string $modelClass, string $segment): string
    {
        $hash = md5($modelClass);

        return self::PREFIX.':'.$hash.':'.$segment;
    }
}
