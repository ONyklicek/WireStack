<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Component;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Infolists\Components\ColorEntry;
use NyonCode\WireCore\Infolists\Components\IconEntry;
use NyonCode\WireCore\Infolists\Components\KeyValueEntry;
use NyonCode\WireCore\Infolists\Components\RepeatableEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Infolist;

class InfolistPreview extends Component
{
    public string $variant = 'overview';

    public function mount(string $variant = 'overview'): void
    {
        $this->variant = $variant;
    }

    public function render()
    {
        return view('livewire.previews.infolist-preview', [
            'variant' => $this->variant,
            'overview' => $this->overviewInfolist(),
            'entries' => $this->entriesInfolist(),
        ]);
    }

    private function overviewInfolist(): Infolist
    {
        return Infolist::make()
            ->record([
                'name' => 'Ada Lovelace',
                'email' => 'ada@analytical.engine',
                'status' => 'active',
                'verified' => true,
                'created_at' => now()->subMonths(8)->toDateTimeString(),
                'lifetime_value' => 184500,
                'plan' => 'Enterprise',
                'seats' => 24,
                'address' => ['city' => 'London', 'country' => 'United Kingdom'],
            ])
            ->schema([
                Section::make('Customer')
                    ->icon('user')
                    ->description('Account profile, status, and billing summary')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Full name')->weight('bold')->icon('user-circle'),
                        TextEntry::make('email')->icon('envelope')->copyable(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state) => $state === 'active' ? Color::Success : Color::Gray),
                        IconEntry::make('verified')->label('Verified')->boolean(),
                        TextEntry::make('created_at')->label('Customer since')->dateTime()->since(),
                        TextEntry::make('lifetime_value')->label('Lifetime value')->money('Kč')->weight('semibold'),
                    ]),
                Section::make('Subscription')
                    ->icon('credit-card')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('plan')->badge()->color(Color::Info),
                        TextEntry::make('seats')->numeric()->icon('users'),
                        TextEntry::make('address.city')->label('City')->icon('map-pin'),
                        TextEntry::make('address.country')->label('Country'),
                    ]),
            ]);
    }

    private function entriesInfolist(): Infolist
    {
        return Infolist::make()
            ->record([
                'status' => 'published',
                'is_featured' => true,
                'is_archived' => false,
                'brand_color' => '#6366f1',
                'tags' => ['laravel', 'livewire', 'tailwind'],
                'meta' => ['SKU' => 'WIRE-001', 'Weight' => '1.2 kg', 'Origin' => 'CZ'],
                'items' => [
                    ['label' => 'Wire Forms license', 'price' => 4900, 'qty' => 2],
                    ['label' => 'Wire Table license', 'price' => 6900, 'qty' => 1],
                    ['label' => 'Priority support', 'price' => 2400, 'qty' => 1],
                ],
            ])
            ->schema([
                Section::make('Entry types')
                    ->icon('squares-2x2')
                    ->description('Every built-in infolist entry, bound to one record')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Badge entry')
                            ->badge()
                            ->color(fn ($state) => $state === 'published' ? Color::Success : Color::Warning),
                        TextEntry::make('tags')
                            ->label('List entry')
                            ->bulleted(),
                        IconEntry::make('is_featured')->label('Boolean — true')->boolean(),
                        IconEntry::make('is_archived')->label('Boolean — false')->boolean(),
                        ColorEntry::make('brand_color')->label('Color entry')->copyable(),
                        KeyValueEntry::make('meta')->label('Key-value entry'),
                        RepeatableEntry::make('items')
                            ->label('Repeatable entry')
                            ->columnSpan('full')
                            ->columns(3)
                            ->schema([
                                TextEntry::make('label')->weight('medium'),
                                TextEntry::make('price')->money('Kč'),
                                TextEntry::make('qty')->label('Qty')->numeric(),
                            ]),
                    ]),
            ]);
    }
}
