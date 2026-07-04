<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Foundation\Schema\Split;
use NyonCode\WireCore\Infolists\Components\BadgeEntry;
use NyonCode\WireCore\Infolists\Components\BooleanEntry;
use NyonCode\WireCore\Infolists\Components\ColorEntry;
use NyonCode\WireCore\Infolists\Components\IconEntry;
use NyonCode\WireCore\Infolists\Components\KeyValueEntry;
use NyonCode\WireCore\Infolists\Components\ListEntry;
use NyonCode\WireCore\Infolists\Components\RepeatableEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;
use NyonCode\WireForms\Concerns\WithActions;

class InfolistPreview extends Component
{
    use WithActions;

    public string $variant = 'overview';

    /** Order fulfillment state — mutated by the "Mark fulfilled" header action. */
    public bool $fulfilled = false;

    public function mount(string $variant = 'overview'): void
    {
        $this->variant = $variant;
    }

    /**
     * Expose the order infolist so its header / entry / row actions dispatch
     * through the core action runtime on this standalone detail page (no modal).
     *
     * @return array<int, Infolist>
     */
    protected function infolistsForActions(): array
    {
        return [$this->orderInfolist()];
    }

    protected function actions(): array
    {
        return [];
    }

    public function render()
    {
        return view('livewire.previews.infolist-preview', [
            'variant' => $this->variant,
            'overview' => $this->overviewInfolist(),
            'entries' => $this->entriesInfolist(),
            'order' => $this->orderInfolist(),
        ]);
    }

    private function notify(Notification $notification): void
    {
        NotificationManager::send($notification, null, $this);
    }

    /**
     * A realistic order detail screen — the read-only counterpart of an edit
     * form, the way an app would actually render `Order #1042`. Header, entry,
     * and per-row actions do believable things (mark fulfilled, resend receipt,
     * open a line item) rather than write to a debug readout.
     */
    private function orderInfolist(): Infolist
    {
        $fulfilled = $this->fulfilled;

        return Infolist::make()
            ->record([
                'number' => '1042',
                'placed_at' => now()->subDays(2)->setTime(14, 8)->toDateTimeString(),
                'total' => 19100,
                'customer' => 'Ada Lovelace',
                'email' => 'ada@analytical.co',
                'email_verified' => true,
                'segments' => ['VIP', 'Wholesale', 'Newsletter', 'Beta'],
                'carrier' => 'DHL Express',
                'tracking' => 'JD0043998877',
                'delivered' => false,
                'items' => [
                    ['product' => 'Wire Forms — Team license', 'qty' => 2, 'price' => 4900],
                    ['product' => 'Wire Table — Team license', 'qty' => 1, 'price' => 6900],
                    ['product' => 'Priority support (12 mo)', 'qty' => 1, 'price' => 2400],
                ],
            ])
            ->schema([
                Section::make('Order #1042')
                    ->icon('shopping-bag')
                    ->description('Placed by Ada Lovelace · 3 items')
                    ->columns(3)
                    ->headerActions([
                        Action::make('edit')
                            ->label('Edit')
                            ->icon('pencil-square')
                            ->outlined()
                            ->action(fn () => $this->notify(Notification::info('Opening the order editor…'))),
                        Action::make('fulfill')
                            ->label('Mark fulfilled')
                            ->icon('check-circle')
                            ->color(Color::Success)
                            ->visible(! $fulfilled)
                            ->action(function () {
                                $this->fulfilled = true;
                                $this->notify(Notification::success('Order #1042 marked as fulfilled')->title('Fulfilled'));
                            }),
                    ])
                    ->schema([
                        BadgeEntry::make('status')
                            ->label('Status')
                            ->state(fn () => $fulfilled ? 'Fulfilled' : 'Processing')
                            ->icon($fulfilled ? 'check-circle' : 'clock')
                            ->color($fulfilled ? Color::Success : Color::Warning),
                        TextEntry::make('placed_at')->label('Placed')->dateTime()->since(),
                        TextEntry::make('total')->label('Total')->money('Kč')->weight('semibold'),
                    ]),

                Split::make()->from('md')->schema([
                    Section::make('Line items')
                        ->icon('list-bullet')
                        ->schema([
                            RepeatableEntry::make('items')
                                ->columns(3)
                                ->schema([
                                    TextEntry::make('product')->weight('medium'),
                                    TextEntry::make('qty')->label('Qty')->numeric(),
                                    TextEntry::make('price')->label('Unit price')->money('Kč'),
                                ])
                                ->actions([
                                    Action::make('viewProduct')
                                        ->label('View')
                                        ->icon('arrow-top-right-on-square')
                                        ->outlined()
                                        ->action(fn ($record) => $this->notify(
                                            Notification::info('Opening “'.data_get($record, 'product').'”'),
                                        )),
                                ]),
                        ]),

                    Section::make('Customer & delivery')
                        ->icon('user')
                        ->columns(1)
                        ->schema([
                            TextEntry::make('customer')->label('Customer')->weight('medium')->icon('user-circle'),
                            TextEntry::make('email')
                                ->label('Email')
                                ->icon('envelope')
                                ->copyable()
                                ->actions([
                                    Action::make('resendReceipt')
                                        ->label('Resend receipt')
                                        ->icon('paper-airplane')
                                        ->outlined()
                                        ->action(fn ($state) => $this->notify(
                                            Notification::success('Receipt re-sent to '.$state)->title('Receipt sent'),
                                        )),
                                ]),
                            BooleanEntry::make('email_verified')->label('Email verified'),
                            ListEntry::make('segments')->label('Segments')->badge()->color(Color::Info)->limitList(3),
                            BadgeEntry::make('carrier')->label('Carrier')->icon('truck')->color(Color::Gray),
                            TextEntry::make('tracking')->label('Tracking number')->copyable()->weight('medium'),
                            BooleanEntry::make('delivered')
                                ->label('Delivered')
                                ->trueColor('success')
                                ->falseIcon('clock')->falseColor('warning'),
                        ]),
                ]),
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
                        BadgeEntry::make('status')
                            ->label('Badge entry')
                            ->color(fn ($state) => $state === 'published' ? Color::Success : Color::Warning),
                        ListEntry::make('tags')->label('List entry')->badge()->color(Color::Info),
                        BooleanEntry::make('is_featured')->label('Boolean — true'),
                        BooleanEntry::make('is_archived')->label('Boolean — false'),
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
