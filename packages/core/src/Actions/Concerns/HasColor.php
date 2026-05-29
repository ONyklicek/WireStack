<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use NyonCode\WireCore\Foundation\Concerns\HasColor as FoundationHasColor;

/**
 * Trait HasColor (Actions namespace).
 *
 * Thin alias of {@see FoundationHasColor}. Kept for backwards compatibility
 * so the existing `NyonCode\WireCore\Actions\Concerns\HasColor` FQCN keeps
 * working while the canonical implementation lives in Foundation.
 */
trait HasColor
{
    use FoundationHasColor;
}
