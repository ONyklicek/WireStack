# ADR 0015: Performance Extensions

## Status

ACCEPTED

## Context

Large-scale applications need performance features beyond standard pagination:
- Standard `paginate()` runs a COUNT query which is expensive on large tables
- No built-in query result caching for repeated identical requests
- Bulk operations load all records into memory at once
- Cursor-based pagination is needed for real-time feeds and large datasets

## Decision

### 1. Pagination Modes
Add three pagination modes to Table:
- `standardPagination()` — default, uses `paginate()` with total count
- `simplePagination()` — uses `simplePaginate()`, no total count query
- `cursorPagination()` — uses `cursorPaginate()`, most efficient for sequential access

### 2. Query Caching
Add `cacheQuery(int $ttl, ?string $key)` to Table:
- Uses Laravel's `Cache::remember()` to cache paginated/unpaginated results
- Auto-generates cache key from SQL, bindings, and table state when no custom key provided
- TTL in seconds, no forever caching to prevent stale data

### 3. Chunking
Add `chunk(int $chunkSize, Closure $callback)` to Table:
- Uses Eloquent's `chunkById()` for memory-efficient bulk operations
- Primarily for exports, bulk updates, and background processing

## Consequences

- Simple pagination eliminates COUNT queries for large datasets
- Cursor pagination enables efficient real-time feeds
- Query caching reduces database load for frequently-accessed tables
- Chunking prevents memory exhaustion during bulk operations
- All features are opt-in — no performance changes for existing tables
