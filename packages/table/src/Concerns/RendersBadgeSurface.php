<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Concerns\HasSize;
use NyonCode\WireTable\Columns\Column;

/**
 * Badge chrome (color + size utility classes) for columns rendered as a pill.
 *
 * Delegates to the canonical {@see HasColor}
 * and {@see HasSize} resolvers already
 * present on the base Column, so the badge-class vocabulary lives in one place.
 * Shared by BadgeColumn and PollColumn.
 *
 * @phpstan-require-extends Column
 */
trait RendersBadgeSurface
{
    public function getColorClasses(?string $color): string
    {
        return self::getBadgeColorClasses($color ?? Color::Gray->value);
    }

    public function getSizeClasses(): string
    {
        return self::getBadgeSizeClasses($this->getSize());
    }
}
