<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Enums;

use NyonCode\WireCore\Foundation\Concerns\HasNativeControl;

/**
 * Which control a surface with both a browser-native element and a custom one
 * (a select and its combobox, a date field and its calendar) actually renders.
 *
 * Resolved once by {@see HasNativeControl} and handed to the view as one value,
 * so a template asks "which do I draw?" instead of re-deriving it from two
 * booleans.
 */
enum NativeControlMode: string
{
    /** The custom control everywhere — the default. */
    case Never = 'never';

    /** The browser's own element everywhere. */
    case Always = 'always';

    /**
     * The browser's element below the surface's mobile breakpoint, the custom
     * control from the breakpoint up. Both are in the markup; CSS shows one.
     */
    case Mobile = 'mobile';

    public function rendersNative(): bool
    {
        return $this !== self::Never;
    }

    public function rendersCustom(): bool
    {
        return $this !== self::Always;
    }

    /** Both controls are rendered and the breakpoint decides which one shows. */
    public function isResponsive(): bool
    {
        return $this === self::Mobile;
    }
}
