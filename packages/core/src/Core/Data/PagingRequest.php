<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Data;

/**
 * What the caller wants one page of a dataset to look like.
 *
 * Separate from `QueryPlan` on purpose: the plan describes *which rows*
 * (filters, search, sorting, joins) and is cacheable across pages, while this
 * describes *which slice of them* and changes on every click of the pager.
 */
final readonly class PagingRequest
{
    /**
     * `$page` is nullable and nullable means something: **let the paginator
     * resolve the current page itself**. That is not a convenience — Livewire's
     * `WithPagination` sets the page through `setPage()` and Laravel reads it
     * back through `Paginator::resolveCurrentPage()`, so a request that named a
     * page explicitly would quietly bypass the host's own paging.
     */
    private function __construct(
        public PagingMode $mode,
        public int $perPage,
        public ?int $page = null,
        public ?string $cursor = null,
        public string $pageName = 'page',
    ) {}

    public static function lengthAware(int $perPage, ?int $page = null, string $pageName = 'page'): self
    {
        return new self(PagingMode::LengthAware, $perPage, $page, null, $pageName);
    }

    public static function simple(int $perPage, ?int $page = null, string $pageName = 'page'): self
    {
        return new self(PagingMode::Simple, $perPage, $page, null, $pageName);
    }

    public static function cursor(int $perPage, ?string $cursor = null, string $pageName = 'cursor'): self
    {
        return new self(PagingMode::Cursor, $perPage, null, $cursor, $pageName);
    }
}
