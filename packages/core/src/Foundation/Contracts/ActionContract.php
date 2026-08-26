<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

/**
 * The two things Foundation needs from an action: what it is called, and
 * whether it renders.
 *
 * Deliberately this small. Foundation components carry actions — an infolist
 * entry, a schema section header, a field's prefix/suffix/hint affix — and to
 * do that they used to type against `Actions\Action` itself, which put the base
 * layer in the debt of a surface module above it. Nothing in Foundation ever
 * invoked an action, styled it or rendered it; it lists them, filters the
 * hidden ones out and finds one by name when the host dispatches a click. That
 * is the whole surface, so that is the whole contract.
 *
 * Keep it that way. If Foundation ever seems to need a third method, the
 * question to ask first is whether that behaviour belongs in Foundation at all
 * — this contract's smallness is what keeps the layer boundary honest, and
 * growing it to fit a caller is how the boundary would quietly come back.
 */
interface ActionContract
{
    /**
     * The action's identifier, used by the host to resolve which action a
     * click belongs to.
     */
    public function getName(): string;

    /**
     * Whether the action is hidden and should not render.
     */
    public function isHidden(): bool;
}
