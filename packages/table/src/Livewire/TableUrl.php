<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Livewire;

use Livewire\Features\SupportQueryString\BaseUrl;

/**
 * URL-tracking attribute for tableState paths (Table::queryString()).
 *
 * Registered at runtime via Component::setPropertyAttribute() after
 * mountWithTable() has seeded state from the request URL — the same
 * pattern Livewire's own PaginationUrl uses.
 *
 * BaseUrl's own URL→property seeding cannot be used here: its mount()
 * runs before mountWithTable() initialises the StateContainer, and
 * data_set() cannot write through StateContainer's ArrayAccess
 * (indirect modification). Seeding is owned by WithTableQueryString.
 */
#[\Attribute]
class TableUrl extends BaseUrl
{
    /**
     * No-op: seeding from the query string is handled by
     * WithTableQueryString::seedTableStateFromQueryString().
     */
    public function mount(): void {}

    /**
     * Push the url effect unconditionally (not only when mounting) so lazy
     * tables — whose mount runs on a subsequent Livewire request — still
     * emit it. The attribute only exists on requests that ran mount, so
     * this never fires on regular update requests.
     *
     * @param  mixed  $context
     */
    public function dehydrate($context): void
    {
        $this->pushQueryStringEffect($context);
    }
}
