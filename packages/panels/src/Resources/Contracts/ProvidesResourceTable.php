<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Contracts;

use NyonCode\WireTable\RelationManagers\RelationManager;
use NyonCode\WireTable\Table;

/**
 * A resource that can list its records.
 *
 * Separate from {@see DescribesResource} rather than folded into it, for the
 * reason the standard gives: one interface, one capability. A read-only resource
 * that only lists, a resource with a form and no list, and a resource with all
 * three are all expressible — and a `ListPage` asks for exactly this and nothing
 * more, so it cannot be handed a resource that has no list to give.
 *
 * The signature is `Table $table` in, `Table` out, matching
 * {@see RelationManager::table()} and the
 * `WithTable` host exactly: the caller owns the instance and its host wiring,
 * the resource only says what goes in it.
 */
interface ProvidesResourceTable
{
    public function table(Table $table): Table;
}
