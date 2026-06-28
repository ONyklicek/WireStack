<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use Livewire\Component;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;
use RuntimeException;
use stdClass;

/**
 * A deliberately malformed table whose columns array contains non-column
 * values, used to exercise the reflector's defensive guards.
 */
class BadTable extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table->columns([
            'not-a-column',
            new stdClass,
            new class
            {
                public function getName(): string
                {
                    throw new RuntimeException('cannot read name');
                }
            },
        ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
