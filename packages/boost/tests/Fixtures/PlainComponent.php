<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use Livewire\Component;

class PlainComponent extends Component
{
    public function render(): string
    {
        return '<div></div>';
    }
}
