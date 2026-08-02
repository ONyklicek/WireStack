<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Concerns\HasIcon;
use NyonCode\WireForms\Exceptions\FormConfigurationException;

/**
 * One block type a {@see Builder} can place: a name, a label, an optional icon,
 * and the schema its items are edited with.
 *
 * A block is a *definition*, not a rendered surface — the Builder renders each
 * item through the block's schema under that item's state path, so a block is
 * never asked to render itself.
 */
class Block extends LayoutComponent
{
    use HasIcon;

    /**
     * A block only carries a definition; the Builder renders its schema per item.
     *
     * Implemented to satisfy the LayoutComponent contract and to fail loudly if a
     * block is ever placed in a schema directly, where it would otherwise render
     * as nothing at all.
     */
    protected function viewName(): string
    {
        throw FormConfigurationException::blockIsNotRenderable($this->getName());
    }
}
