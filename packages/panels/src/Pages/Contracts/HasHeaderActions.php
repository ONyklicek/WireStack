<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Pages\Contracts;

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WirePanels\Pages\Concerns\InteractsWithHeaderActions;

/**
 * A page that puts actions beside its heading — *New* on a list, *Delete* on a
 * record, anything of the application's own on a page it wrote.
 *
 * Answered by {@see InteractsWithHeaderActions}.
 */
interface HasHeaderActions
{
    /**
     * The page's header actions, in the order they are drawn.
     *
     * @return array<int, Action|ActionGroup>
     */
    public function getHeaderActions(): array;
}
