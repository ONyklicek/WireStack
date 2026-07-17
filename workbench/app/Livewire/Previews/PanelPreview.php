<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Panels\Components\SelectEntry;
use NyonCode\WireCore\Panels\Components\TextInputEntry;
use NyonCode\WireCore\Panels\Components\ToggleEntry;
use NyonCode\WireCore\Panels\Panel;
use NyonCode\WireCore\Panels\PanelComponent;
use Workbench\App\Models\User;

/**
 * Editable panel ("record panel") preview — a real, Model-backed account panel.
 *
 * Toggling the switch, changing the role, or editing the name commits directly
 * to the User row with optimistic UI + optimistic locking through the shared
 * wireEditableCell engine. The read-only email entry proves editable and
 * read-only entries mix in one schema.
 */
class PanelPreview extends PanelComponent
{
    public int $userId = 0;

    public string $variant = 'default';

    public function mount(string $variant = 'default'): void
    {
        $this->variant = $variant;
        $this->userId = (int) User::query()->min('id');
    }

    public function panel(): Panel
    {
        return Panel::make()
            ->record(User::find($this->userId))
            ->columns(1)
            ->schema([
                Section::make('Account')
                    ->icon('user')
                    ->description('Edits below write straight to the record — no Save button.')
                    ->columns(2)
                    ->schema([
                        TextInputEntry::make('name')->label('Name')->rules(['required', 'min:2']),
                        SelectEntry::make('role')->label('Role')->options([
                            'viewer' => 'Viewer',
                            'editor' => 'Editor',
                            'manager' => 'Manager',
                            'admin' => 'Admin',
                        ]),
                        ToggleEntry::make('is_active')->label('Active')->onColor('success'),
                        TextEntry::make('email')->label('Email')->icon('envelope'), // read-only, mixed in
                    ]),
            ]);
    }
}
