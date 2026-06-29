<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use Livewire\Component;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;
use RuntimeException;

class ThrowingTable extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        throw new RuntimeException('boom');
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
