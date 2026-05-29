<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\BooleanColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;
use Workbench\App\Models\User;

class TablePreview extends Component
{
    use WithTable;

    public string $variant = 'overview';

    public function mount(string $variant = 'overview'): void
    {
        $this->variant = $variant;
    }

    /**
     * Seed selection state after WithTable has booted its state container,
     * so the selection variant renders with rows checked and the bulk
     * toolbar visible. (Doing this in mount() is wiped by mountWithTable.)
     */
    public function booted(): void
    {
        if ($this->variant !== 'selection') {
            return;
        }

        $this->tableState->set('selection.records', User::query()
            ->orderBy('id')
            ->limit(3)
            ->pluck('id')
            ->map(fn (int $id): string => (string) $id)
            ->all());
    }

    public function table(Table $table): Table
    {
        return $table
            ->model(User::class)
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                BadgeColumn::make('role')
                    ->label('Role')
                    ->colors([
                        'admin' => 'primary',
                        'manager' => 'info',
                        'editor' => 'warning',
                        'viewer' => 'gray',
                    ]),
                BooleanColumn::make('is_active')
                    ->label('Active')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->date('M j')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'admin' => 'Administrator',
                        'manager' => 'Manager',
                        'editor' => 'Editor',
                        'viewer' => 'Viewer',
                    ]),
            ])
            ->actions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('pencil')
                    ->color('primary'),
                DeleteAction::make(),
            ])
            ->headerActions([
                HeaderAction::make('invite')
                    ->label('Invite user')
                    ->icon('plus')
                    ->color('primary'),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->selectable()
            ->paginated(false);
    }

    public function render()
    {
        return view('livewire.previews.table-preview');
    }
}
