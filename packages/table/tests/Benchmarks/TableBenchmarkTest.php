<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\ActionHalt;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\ToggleColumn;
use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TernaryFilter;
use NyonCode\WireTable\Table;

// ─── Table Creation Benchmark ───────────────────────────────────────────────

it('creates table with full configuration in under 5ms', function () {
    $start = hrtime(true);

    for ($i = 0; $i < 100; $i++) {
        Table::make()
            ->perPage(25)
            ->perPageOptions([10, 25, 50, 100])
            ->searchable()
            ->sortable()
            ->paginated()
            ->selectable()
            ->striped()
            ->hoverable()
            ->compact()
            ->bordered()
            ->defaultSort('name', 'asc')
            ->primaryKey('id')
            ->actionsPosition('end')
            ->actionsAlignment('right')
            ->actionsColumnLabel('Akce')
            ->emptyState('Žádné záznamy', 'Žádná data.', 'search')
            ->stackedOnMobile()
            ->lazy()
            ->poll('5s')
            ->pollKeepAlive();
    }

    $elapsed = (hrtime(true) - $start) / 1_000_000; // ms

    expect($elapsed / 100)->toBeLessThan(5);
});

// ─── Column Creation Benchmark ──────────────────────────────────────────────

it('creates 50 columns with full configuration in under 10ms', function () {
    $start = hrtime(true);

    for ($i = 0; $i < 100; $i++) {
        $columns = [];
        for ($j = 0; $j < 50; $j++) {
            $columns[] = TextColumn::make("col_{$j}")
                ->label("Column {$j}")
                ->sortable()
                ->searchable()
                ->visibleFrom('md')
                ->width('100px')
                ->alignment('left')
                ->wrap()
                ->limit(100)
                ->placeholder('—')
                ->prefix('#')
                ->suffix('!')
                ->copyable();
        }
    }

    $elapsed = (hrtime(true) - $start) / 1_000_000;

    expect($elapsed / 100)->toBeLessThan(10);
});

// ─── Action Creation Benchmark ──────────────────────────────────────────────

it('creates 20 actions with closures in under 5ms', function () {
    $start = hrtime(true);

    for ($i = 0; $i < 100; $i++) {
        $actions = [];
        for ($j = 0; $j < 20; $j++) {
            $actions[] = Action::make("action_{$j}")
                ->label(fn ($record) => "Action {$record->id}")
                ->color(fn ($record) => $record->active ? 'success' : 'danger')
                ->icon(fn ($record) => $record->type === 'a' ? 'check' : 'x')
                ->tooltip(fn ($record) => "ID: {$record->id}")
                ->action(fn ($record) => null)
                ->before(fn () => null)
                ->after(fn () => null)
                ->requiresConfirmation()
                ->modalHeading('Potvrdit')
                ->successNotification('Hotovo');
        }
    }

    $elapsed = (hrtime(true) - $start) / 1_000_000;

    expect($elapsed / 100)->toBeLessThan(5);
});

// ─── ActionGroup Expansion Benchmark ────────────────────────────────────────

it('expands nested action groups quickly', function () {
    $start = hrtime(true);

    for ($i = 0; $i < 1000; $i++) {
        $table = Table::make()->actions([
            Action::make('view'),
            ActionGroup::make([
                Action::make('edit'),
                Action::make('duplicate'),
                Action::divider(),
                Action::make('archive'),
                Action::make('delete'),
            ]),
            ActionGroup::make([
                Action::make('export_csv'),
                Action::make('export_pdf'),
            ]),
        ]);

        $table->getAllActions();
    }

    $elapsed = (hrtime(true) - $start) / 1_000_000;

    expect($elapsed / 1000)->toBeLessThan(1);
});

// ─── Filter Creation Benchmark ──────────────────────────────────────────────

it('creates 10 filters in under 2ms', function () {
    $start = hrtime(true);

    for ($i = 0; $i < 100; $i++) {
        $filters = [
            SelectFilter::make('status')->options(['active' => 'Aktivní', 'inactive' => 'Neaktivní'])->label('Stav'),
            SelectFilter::make('role')->options(['admin' => 'Admin', 'user' => 'Uživatel'])->multiple(),
            DateFilter::make('created_at')->range()->label('Vytvořeno'),
            DateFilter::make('updated_at')->minDate('2024-01-01')->maxDate('2024-12-31'),
            TernaryFilter::make('active')->trueLabel('Ano')->falseLabel('Ne'),
            SelectFilter::make('category')->options(array_combine(range(1, 50), range(1, 50))),
            TernaryFilter::make('verified')->nullable(),
            DateFilter::make('deleted_at')->range(),
            SelectFilter::make('priority')->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High']),
            SelectFilter::make('department')->options(['IT' => 'IT', 'HR' => 'HR'])->searchable(),
        ];
    }

    $elapsed = (hrtime(true) - $start) / 1_000_000;

    expect($elapsed / 100)->toBeLessThan(2);
});

// ─── Dynamic Property Resolution Benchmark ──────────────────────────────────

it('resolves dynamic properties quickly', function () {
    $action = Action::make('test')
        ->label(fn ($r) => "Label: {$r->id}")
        ->color(fn ($r) => $r->active ? 'success' : 'danger')
        ->icon(fn ($r) => $r->type === 'a' ? 'check' : 'x')
        ->tooltip(fn ($r) => "Tooltip: {$r->id}")
        ->size(fn ($r) => $r->important ? 'lg' : 'sm')
        ->extraAttributes(fn ($r) => ['data-id' => $r->id]);

    $record = (object) ['id' => 1, 'active' => true, 'type' => 'a', 'important' => false];

    $start = hrtime(true);

    for ($i = 0; $i < 10000; $i++) {
        $action->getLabel($record);
        $action->getColor($record);
        $action->getIcon($record);
        $action->getTooltip($record);
        $action->getSize($record);
        $action->getExtraAttributes($record);
    }

    $elapsed = (hrtime(true) - $start) / 1_000_000;

    // 10000 iterations * 6 resolutions = 60000 closure calls
    expect($elapsed)->toBeLessThan(100); // under 100ms total
});

// ─── Table Configuration with Columns + Actions + Filters ───────────────────

it('builds complete table configuration in under 5ms', function () {
    $start = hrtime(true);

    for ($i = 0; $i < 100; $i++) {
        Table::make()
            ->columns([
                Column::make('id')->sortable(),
                TextColumn::make('name')->sortable()->searchable()->label('Jméno'),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('price')->money('CZK'),
                BadgeColumn::make('status')->colors([
                    'active' => 'success',
                    'inactive' => 'danger',
                ]),
                ToggleColumn::make('active'),
                TextColumn::make('created_at')->date()->sortable(),
                Column::make('notes')->visibleFrom('lg')->wrap()->limit(200),
            ])
            ->actions([
                Action::make('view')->icon('eye')->color('gray'),
                ActionGroup::make([
                    Action::make('edit')->icon('pencil'),
                    Action::divider(),
                    Action::make('delete')->icon('trash')->color('danger')->requiresConfirmation(),
                ]),
            ])
            ->bulkActions([
                BulkAction::make('delete')->color('danger')->requiresConfirmation(),
                BulkAction::make('export')->icon('download'),
            ])
            ->headerActions([
                HeaderAction::make('create')->icon('plus')->color('primary'),
                HeaderAction::make('import')->icon('upload')->badge(fn () => 3),
            ])
            ->filters([
                SelectFilter::make('status')->options(['active' => 'Aktivní', 'inactive' => 'Neaktivní']),
                DateFilter::make('created_at')->range(),
                TernaryFilter::make('active'),
            ])
            ->defaultSort('created_at', 'desc')
            ->perPage(25)
            ->searchable()
            ->sortable()
            ->paginated()
            ->striped()
            ->hoverable();
    }

    $elapsed = (hrtime(true) - $start) / 1_000_000;

    expect($elapsed / 100)->toBeLessThan(5);
});

// ─── Column Searchable/Sortable Filtering Benchmark ─────────────────────────

it('filters searchable and sortable columns quickly', function () {
    $columns = [];
    for ($i = 0; $i < 100; $i++) {
        $col = Column::make("col_{$i}");
        if ($i % 3 === 0) {
            $col->searchable();
        }
        if ($i % 2 === 0) {
            $col->sortable();
        }
        $columns[] = $col;
    }

    $table = Table::make()->columns($columns);

    $start = hrtime(true);

    for ($i = 0; $i < 1000; $i++) {
        $table->getSearchableColumns();
        $table->getSortableColumns();
    }

    $elapsed = (hrtime(true) - $start) / 1_000_000;

    expect($elapsed / 1000)->toBeLessThan(1);
});

// ─── Polling Config Generation Benchmark ────────────────────────────────────

it('generates polling config quickly', function () {
    $table = Table::make()
        ->poll('5s')
        ->pollKeepAlive()
        ->pollMethod('refresh')
        ->pollOnlyVisible();

    $start = hrtime(true);

    for ($i = 0; $i < 10000; $i++) {
        $table->getPollingConfig();
        $table->getPollingDirective();
    }

    $elapsed = (hrtime(true) - $start) / 1_000_000;

    expect($elapsed / 10000)->toBeLessThan(0.1);
});

// ─── Responsive Classes Generation Benchmark ────────────────────────────────

it('generates responsive classes quickly', function () {
    $columns = [
        Column::make('a')->visibleFrom('sm'),
        Column::make('b')->visibleFrom('md'),
        Column::make('c')->visibleFrom('lg'),
        Column::make('d')->hiddenFrom('md'),
        Column::make('e')->onlyOnMobile(),
        Column::make('f')->onlyOnDesktop(),
    ];

    $start = hrtime(true);

    for ($i = 0; $i < 10000; $i++) {
        foreach ($columns as $col) {
            $col->getResponsiveClasses();
        }
    }

    $elapsed = (hrtime(true) - $start) / 1_000_000;

    // 10000 * 6 = 60000 calls
    expect($elapsed)->toBeLessThan(100);
});

// ─── Notification Creation Benchmark ────────────────────────────────────────

it('creates notifications quickly', function () {
    $start = hrtime(true);

    for ($i = 0; $i < 10000; $i++) {
        Notification::success('Uloženo')
            ->title('Hotovo')
            ->duration(3000)
            ->icon('check')
            ->position('top-right')
            ->extra(['key' => 'value'])
            ->toArray();
    }

    $elapsed = (hrtime(true) - $start) / 1_000_000;

    expect($elapsed / 10000)->toBeLessThan(0.1);
});

// ─── ActionHalt Serialization Benchmark ─────────────────────────────────────

it('serializes ActionHalt quickly', function () {
    $start = hrtime(true);

    for ($i = 0; $i < 10000; $i++) {
        ActionHalt::make()
            ->heading('Smazat?')
            ->body('Opravdu?')
            ->icon('trash', 'danger')
            ->submitLabel('Smazat')
            ->cancelLabel('Zrušit')
            ->danger()
            ->form([TextInput::make('reason')])
            ->validation(['reason' => 'required'])
            ->source('action', 0)
            ->toArray();
    }

    $elapsed = (hrtime(true) - $start) / 1_000_000;

    expect($elapsed / 10000)->toBeLessThan(0.1);
});
