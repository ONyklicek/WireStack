<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts\Enum;

use NyonCode\WireCore\Foundation\Support\EnumResolver;

/**
 * Opt-in contract a PHP enum may implement to expose a human-facing label.
 *
 * Implement this on a backed or unit enum used as an Eloquent cast so display
 * surfaces (table columns, infolist entries, exports) render the label instead
 * of the raw backing value / case name. Resolved centrally by
 * {@see EnumResolver::label()}.
 *
 * Distinct from the builder-facing {@see \NyonCode\WireCore\Foundation\Contracts\HasLabel}
 * (which carries a fluent `label()` setter for components); this one is enum-only.
 */
interface HasLabel
{
    public function getLabel(): ?string;
}
