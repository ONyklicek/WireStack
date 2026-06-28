<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use Livewire\Component;
use NyonCode\WireCore\Infolists\Components\IconEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Concerns\WithInfolists;
use NyonCode\WireCore\Infolists\Infolist;

class DemoInfolist extends Component
{
    use WithInfolists;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('name'),
            IconEntry::make('status'),
        ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
