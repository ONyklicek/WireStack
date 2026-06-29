<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

/**
 * A plain class that is not a Livewire component; the scanner must skip it even
 * though it declares a table() method.
 */
class NotAComponent
{
    public function table(): string
    {
        return 'noop';
    }
}
