<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts\Enum;

use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Support\EnumResolver;

/**
 * Opt-in contract a PHP enum may implement to carry its own icon.
 *
 * When implemented, badge/icon columns and infolist entries auto-resolve the
 * icon from the enum case (no explicit `icons()` map needed). Resolved
 * centrally by {@see EnumResolver::icon()}.
 *
 * Distinct from the builder-facing {@see \NyonCode\WireCore\Foundation\Contracts\HasIcon}
 * (which carries a fluent `icon()` setter for components); this one is enum-only.
 */
interface HasIcon
{
    public function getIcon(): string|Icon|null;
}
