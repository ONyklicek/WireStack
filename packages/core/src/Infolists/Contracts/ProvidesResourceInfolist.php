<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Contracts;

use NyonCode\WireCore\Infolists\Infolist;

/**
 * A resource that can show one record read-only.
 *
 * Lives beside the `Infolist` it names rather than with the identity contract in
 * `Core/Resources`: that is L1 and Infolists is an L2 surface, so naming it from
 * there is the boundary ModuleLayersTest guards.
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
