<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Contracts;

use NyonCode\WirePanels\Resources\Concerns\EmbedsRelationManagers;
use NyonCode\WireTable\RelationManagers\RelationManager;

/**
 * A resource whose record has related lists worth showing beside it.
 *
 * Lives here rather than with the identity contract because it names
 * {@see RelationManager}, which is wire-table's — the same rule that puts
 * `ProvidesResourceTable` here and `ProvidesResourceForm` in wire-forms.
 *
 * Nothing about `RelationManager` changes: it was already a working owner, and
 * this only lets a resource say which ones belong to it so a page can embed them
 * without the application wiring each `@livewire` call by hand. Mounting one
 * directly keeps working exactly as before.
 *
 * @see EmbedsRelationManagers
 */
interface ProvidesRelationManagers
{
    /**
     * @return array<int, class-string<RelationManager>>
     */
    public function relationManagers(): array;
}
