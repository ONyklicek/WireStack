<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use Livewire\Component;
use NyonCode\WireTable\Concerns\WithTable;

/**
 * Abstract Livewire component; the scanner must skip it.
 */
abstract class AbstractDemoComponent extends Component
{
    use WithTable;
}
