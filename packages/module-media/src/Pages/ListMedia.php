<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Pages;

use NyonCode\WireCore\Core\Plugin\Contracts\IdentifiesHookTarget;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesBreadcrumbs;
use NyonCode\WireModuleMedia\Livewire\MediaManager;
use NyonCode\WireModuleMedia\Resources\MediaResource;
use NyonCode\WirePanels\Resources\Concerns\BelongsToResource;
use NyonCode\WirePanels\Resources\Pages\ListPage;

/**
 * The library's own screen.
 *
 * Not a {@see ListPage}, and that is the
 * change: a table of rows is a list of files, and what a library needs is a
 * place to keep them. The page is {@see MediaManager} — a folder tree, a grid,
 * drag-and-drop — with the resource wiring that every panel page has bolted on
 * here rather than inside the manager, so the manager stays usable on its own
 * page, in a modal, or beside a form.
 */
class ListMedia extends MediaManager implements IdentifiesHookTarget, ProvidesBreadcrumbs
{
    use BelongsToResource;

    protected static ?string $resource = MediaResource::class;

    public function getTitle(): ?string
    {
        return $this->title ?? $this->resourceLabel();
    }
}
