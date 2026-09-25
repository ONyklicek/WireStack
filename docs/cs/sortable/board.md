---
title: Tabule
order: 45
summary: Záznamy v drahách — kanban nad modelem a sloupcem, karty přetahované mezi drahami i uvnitř jedné, s uloženým pořadím.
---

# Board

Tabule ukazuje záznamy v drahách podle hodnoty jednoho sloupce — úkoly podle
stavu, obchody podle fáze — a přesun karty mezi drahami tu hodnotu změní. Je to
povrch jako tabulka: deklaruje se jednou a vykreslí ho komponenta, která ho hostí.

```php
use NyonCode\WireSortable\Board\Board;
```

## Jak to funguje

**Deklarace a hostitel jsou oddělené.** `Board` říká, jaké jsou dráhy, které
záznamy do nich patří a co karta ukazuje. `WithBoard`, složený do jakékoli
Livewire komponenty, ho vykreslí a odpoví na puštění karty. Stránka s tabulí je
proto [`Page`](../panels/pages.md#vlastni-stranka), která skládá `WithBoard` —
žádná vlastní třída a žádný balíček závislý na jiném.

**Tažení je Livewire.** Každá dráha je seznam `wire:sort` v jedné skupině
s hodnotou dráhy jako id skupiny, takže karta může opustit svou dráhu a puštění
zavolá na hostiteli `moveBoardCard(key, position, lane)`. Tento balíček na to
nemá žádný skript.

**Puštění se zkontroluje, pak zapíše.** Dráha musí být jedna z deklarovaných;
záznam se hledá vlastním dotazem tabule, takže kartu, kterou by tabule
neukázala, nejde přesunout pojmenováním jejího klíče; `canMoveBoardCard()` může
přechod odmítnout. Pak `MoveBoardCard` zapíše dráhu do sloupce a — když má tabule
`orderColumn()` — přečísluje dráhu od nuly s kartou na místě puštění, v jedné
transakci, a zapíše jen řádky, jejichž číslo se změnilo. Bez sloupce pořadí
puštění změní dráhu a nic dalšího.

**Celou tabuli vykreslí jeden dotaz**, do drah roztříděný v PHP; záznam, jehož
hodnota neodpovídá žádné dráze, se nevykreslí. Velkou tabuli zužte přes `query()`.

## Základní použití

```php
use NyonCode\WirePanels\Pages\Page;
use NyonCode\WireSortable\Board\Board;
use NyonCode\WireSortable\Concerns\WithBoard;

final class TaskBoard extends Page
{
    use WithBoard;

    protected static string $view = 'wire-sortable::board.content';   // [tl! focus]

    public function board(Board $board): Board                         // [tl! focus:start]
    {
        return $board->model(Task::class)->groupBy('status')->lanes(TaskStatus::class);
    }                                                                   // [tl! focus:end]
}
```

## Dráhy

Tři způsoby, jak říct, jaké jsou dráhy, v pořadí, v jakém se kreslí:

```php
->lanes(TaskStatus::class)                      // backed enum: jeho case, popisky a barvy
->lanes(['todo' => 'To do', 'done' => 'Done'])  // hodnota => popisek
->lanes([Lane::make('todo')->label('To do'), Lane::make('done')->color('success')])
```

Jméno dráhy je hodnota sloupce tak, jak je uložená — u sloupce s enum castem
backing hodnota case. Enum, který implementuje kontrakty `HasLabel` a `HasColor`
z core, své dráhy popíše a obarví; jinak se jméno case polidští a značka je šedá.

## Karty

```php
->cardTitle('title')                                     // atribut, nebo fn (Task $task) => …
->cardDescription(fn (Task $task) => $task->owner?->name)
->cardUrl(fn (Task $task) => route('tasks.edit', $task)) // nadpis se stane odkazem s wire:navigate
```

## Pořadí

```php
->orderColumn('position')
```

Dráha, do které karta dopadne, se kolem ní přečísluje od nuly. Bez něj si tabule
drží pořadí, které dá `query()`, a mění jen dráhu.

## Hlídání přesunu

```php
protected function canMoveBoardCard(Model $record, string $lane): bool
{
    return $record->status !== TaskStatus::Done;   // hotový úkol se znovu otevírá ručně
}

protected function boardCardMoved(Model $record, string $from, string $to): void
{
    Notification::make()->title("Přesunuto do {$to}")->send();
}
```

## Rozšířený příklad

Tabule ve vlastní komponentě vedle jiného obsahu, ne jako stránka:

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use NyonCode\WireSortable\Board\Board;
use NyonCode\WireSortable\Board\Lane;
use NyonCode\WireSortable\Concerns\WithBoard;

final class DealPipeline extends Component
{
    use WithBoard;

    public function board(Board $board): Board                                    // [tl! focus:start]
    {
        return $board
            ->model(Deal::class)
            ->query(fn (Builder $query) => $query->where('owner_id', auth()->id()))
            ->groupBy('stage')
            ->lanes([
                Lane::make('lead')->label('Poptávky'),
                Lane::make('proposal')->color('info'),
                Lane::make('won')->color('success'),
                Lane::make('lost')->color('danger'),
            ])
            ->cardTitle('name')
            ->cardDescription(fn (Deal $deal) => money($deal->value))
            ->orderColumn('stage_position');
    }                                                                              // [tl! focus:end]

    protected function canMoveBoardCard(Model $record, string $lane): bool
    {
        return $lane !== 'won' || $record->signed_at !== null;
    }

    public function render()
    {
        return view('livewire.deal-pipeline');   // @include('wire-sortable::board.board', ['lanes' => $this->boardLanesForView()])
    }
}
```

## Board API

```php
Board::make()
->model(string $model)                          // class-string<Model>
->query(?Closure $callback)                     // fn (Builder $query) => $query->… — zúží záznamy
->groupBy(string $column)                       // [tl! focus:start] sloupec, jehož hodnotou je dráha
->lanes(array|string $lanes)                    // [tl! focus:end] Lane[], hodnota => popisek, nebo třída backed enumu
->cardTitle(string|Closure $title)              // atribut nebo fn (Model $record) => string — výchozí 'id'
->cardDescription(string|Closure|null $description)
->cardUrl(?Closure $url)                        // fn (Model $record) => ?string
->orderColumn(?string $column)                  // přečíslovaný při puštění — výchozí žádný
->getLanes(): array
->getGroupBy(): string
->getOrderColumn(): ?string
->getQuery(): Builder
```

`Lane`: `Lane::make(string $name)`, `->label(string|Closure|null)`,
`->color(string|Color|null)` — výchozí `'gray'`.

`WithBoard`: `board(Board $board): Board` (deklarace), `getBoard(): Board`,
`moveBoardCard(mixed $key, int $position, string $lane): void` (co volá puštění),
`canMoveBoardCard(Model, string): bool`, `boardCardMoved(Model, string, string):
void`, `boardLanesForView(): array`. Pohledy: `wire-sortable::board.board`
(dráhy, dostane `$lanes`) a `wire-sortable::board.content` (totéž pro `$view`
stránky `Page`). Jména hooků: `board`, `board-lane`, `board-card`.

## Související

- [Stránky](../panels/pages.md#vlastni-stranka) — stránka, na které tabule obvykle sedí
- [Řazení řádků](row-sorting.md) — řazení řádků tabulky tažením
