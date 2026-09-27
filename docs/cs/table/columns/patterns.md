---
order: 29
summary: Hotové receptury sloupců — buňky, které aplikace pořád staví znovu, napsané jednou.
---

# Vzory a recepty

## Tabulka uživatelů s avatarem

```php
$table->columns([
    StackedColumn::make('user')
        ->avatar('avatar_url')
        ->primary('name')
        ->secondary('email')
        ->circular()
        ->searchable()
        ->searchColumns(['name', 'email']),

    BadgeColumn::make('role')
        ->colors(['primary' => 'admin', 'success' => 'editor', 'gray' => 'viewer']),

    TextColumn::make('department.name')
        ->sortable()
        ->searchable(),

    TextColumn::make('posts.count')
        ->label('Posts')
        ->sortable()
        ->alignCenter(),

    TextColumn::make('last_login')
        ->since()
        ->sortable()
        ->textSize('sm')
        ->textColor('gray'),

    BooleanColumn::make('is_active'),
]);
```

## Finanční tabulka

```php
$table->columns([
    TextColumn::make('number')
        ->searchable()
        ->fontFamily('mono'),

    TextColumn::make('client.name')
        ->searchable()
        ->sortable(),

    TextColumn::make('issued_at')
        ->date('d.m.Y')
        ->sortable(),

    TextColumn::make('due_at')
        ->date('d.m.Y'),

    TextColumn::make('total')
        ->money('CZK')
        ->sortable()
        ->alignRight()
        ->weight('bold')
        ->summarize('sum', 'Total'),

    BadgeColumn::make('status')
        ->colors([
            'draft' => 'gray',
            'sent' => 'warning',
            'paid' => 'success',
            'overdue' => 'danger',
        ]),

    PollColumn::make('payment_status')
        ->intervalSeconds(30)
        ->badge()
        ->colors(['success' => 'received', 'warning' => 'pending', 'gray' => 'none'])
        ->pollWhile(fn ($state) => $state === 'pending'),
]);
```

## Tabulka nástěnky úkolů

```php
$table->columns([
    SelectColumn::make('status')
        ->options([
            'todo' => '📋 To Do',
            'in_progress' => '🔄 In Progress',
            'review' => '👀 Review',
            'done' => '✅ Done',
        ]),

    TextColumn::make('title')
        ->searchable()
        ->weight('semibold')
        ->description(fn ($r) => Str::limit($r->body, 60))
        ->actionUrl(fn ($r) => route('tasks.show', $r)),

    StackedColumn::make('assignee')
        ->avatar('assignee.avatar_url')
        ->primary('assignee.name')
        ->circular()
        ->avatarSize('sm'),

    BadgeColumn::make('priority')
        ->colors(['danger' => 'high', 'warning' => 'medium', 'gray' => 'low'])
        ->icons(['arrow-up' => 'high', 'minus' => 'medium', 'arrow-down' => 'low']),

    TextColumn::make('due_at')
        ->date('d.m.')
        ->textColor('gray')
        ->textSize('sm'),
]);
```

## Vlastní třída sloupce

Když buňku, kterou potřebujete, nenakreslí žádná fluent metoda dodávaných
sloupců, napište třídu sloupce a její pohled:

```bash
php artisan make:wire-column Price
```

zapíše `app/Tables/Columns/PriceColumn.php` a
`resources/views/tables/columns/price.blade.php`. Třída přepisuje
`renderCell()`, jedinou metodu, kterou tabulka pro buňku volá, a pohledu předává
prostá data — rychlá vykreslovací cesta tabulky přepsání pozná a buňku vykreslí
celou, místo aby ji vkládala do kostry textového sloupce:

```php
namespace App\Tables\Columns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\Column;

class PriceColumn extends Column
{
    protected ?string $view = 'tables.columns.price';

    public function renderCell(Model $record): string // [tl! focus:start]
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $state = $this->getState($record);

        return trim($this->renderView('tables.columns.price', [
            'column' => $this,
            'record' => $record,
            'state' => $state,
            'value' => $this->formatValue($state, $record),
        ]));
    } // [tl! focus:end]
}
```

Jméno pohledu je nastavené na `$view`, ne jen předané do `renderView()`, takže
`PriceColumn::make('price')->view('…')` ho pro jednu tabulku stále přepíše a
vlastní pohledy balíčku `tables.columns.*` se místo něj nikdy nehledají.
`value` je naformátovaný stav — text prázdné buňky, když žádný není — takže
`->placeholder()`, `->limit()` a ostatní dál fungují. Existující pohled se nikdy
nepřepíše, `--force` nahradí jen třídu a
`php artisan vendor:publish --tag=wire-table::stubs` vám dovolí změnit, co
zapisuje.
