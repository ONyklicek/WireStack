<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Resources\Contracts;

use NyonCode\WireCore\Infolists\Infolist;

/**
 * A resource that can show one record read-only.
 *
 * ADR 0020 asked whether a view page needs an owner concern of its own; it does
 * not (Q2). `Infolist` is a mature read-only surface in core, so a view page is
 * a renderer of this and nothing else — which is why this contract carries one
 * method rather than mirroring the form's lifecycle.
 */
interface ProvidesResourceInfolist
{
    public function infolist(Infolist $infolist): Infolist;
}
