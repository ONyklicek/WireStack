<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts\Enum;

use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Support\EnumResolver;

/**
 * Opt-in contract a PHP enum may implement to carry its own palette color.
 *
 * When implemented, badge/icon columns and infolist entries auto-resolve the
 * color from the enum case (no explicit `colors()` map needed). Resolved
 * centrally by {@see EnumResolver::color()}.
 */
interface HasColor
{
    public function getColor(): string|Color|null;
}
