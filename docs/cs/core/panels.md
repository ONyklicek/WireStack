---
order: 56
---

# Editovatelné panely

**Panel** je editovatelný „panel záznamu": vypadá jako [infolist](infolists.md) — stejné deklarativní schéma sekcí, gridů a entry — ale vedle read-only entry umí hostit **editovatelné** entry (switch, checkbox, select, textové pole), které zapisují **rovnou do záznamu**. Každá změna se odešle samostatně s optimistickým UI a optimistickým zámkem, stejnou cestou jako [editovatelné sloupce tabulky](../table/columns/editing.md) — žádné tlačítko Uložit, žádný buffer formuláře.

Infolisty zůstávají kontraktem read-only; panel je plocha pro „přečti *a* uprav tento jeden záznam na místě".

```php
use NyonCode\WireCore\Panels\Panel;
use NyonCode\WireCore\Panels\Components\ToggleEntry;
use NyonCode\WireCore\Panels\Components\SelectEntry;
use NyonCode\WireCore\Panels\Components\TextInputEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry; // read-only entry se volně mísí
use NyonCode\WireCore\Foundation\Schema\Section;

Panel::make()
    ->record($user)
    ->columns(2)
    ->schema([
        Section::make('Účet')->icon('user')->columns(2)->schema([
            TextInputEntry::make('name')->rules(['required', 'min:2']),
            SelectEntry::make('role')->options([
                'viewer' => 'Divák',
                'editor' => 'Editor',
                'admin'  => 'Admin',
            ]),
            ToggleEntry::make('is_active')->label('Aktivní')->onColor('success'),
            TextEntry::make('email')->icon('envelope'), // read-only
        ]),
    ]);
```

> **Nováček?** Panel je infolist, který jde editovat. Sestavíš ho v PHP, předáš mu záznam a každý switch/select/pole se odešle ve chvíli, kdy ho změníš.

## Instalace

Panely jsou součástí `wire-core` — nic dalšího se neinstaluje. Zajisti, aby views balíčku byly v Tailwind content cestách, aby se vygenerovaly styly (i inline-edit ovládací prvky):

```js
export default {
    content: [
        // ...cesty tvé aplikace
        './vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
    ],
}
```

Editovatelné entry odesílají přes sdílený Alpine engine `wireEditableCell`, který dodává předkompilovaný JS bundle `wire-core` a injektuje se automaticky přes Livewire `@assets` — žádný JavaScript nezapojuješ ručně.

## Rychlý start

Panel potřebuje Livewire hostitele, aby jeho editace měly kam odeslat. Nejrychlejší cesta je rozšířit `PanelComponent`: podrž si záznam, vrať schéma z `panel()` a hotovo — základní komponenta ho vykreslí a poskytne zapisovací endpoint.

```php
use NyonCode\WireCore\Panels\Panel;
use NyonCode\WireCore\Panels\PanelComponent;
use NyonCode\WireCore\Panels\Components\ToggleEntry;
use NyonCode\WireCore\Panels\Components\SelectEntry;
use NyonCode\WireCore\Panels\Components\TextInputEntry;

class EditOrderPanel extends PanelComponent // [tl! focus:start]
{
    public Order $order;                    // Livewire ho každý request znovu načte z DB

    public function panel(): Panel
    {
        return Panel::make()
            ->record($this->order)          // 1. záznam, který každé entry čte i zapisuje
            ->columns(2)
            ->schema([                       // 2. co zobrazit / editovat
                TextInputEntry::make('reference')->rules(['required']),
                SelectEntry::make('status')->options(OrderStatus::class),
                ToggleEntry::make('is_paid')->label('Zaplaceno'),
            ]);
    }
} // [tl! focus:end]
```

Umísti ho na stránku jako každou Livewire komponentu:

```blade
<livewire:edit-order-panel :order="$order" />
```

To je celé. Přepni switch `is_paid` a sloupec `orders.is_paid` se okamžitě aktualizuje; žádný formulář, žádné odeslání.

> **Proč komponenta, ne jen `{{ $panel }}`?** Read-only infolisty jsou bezstavové, takže je můžeš vypsat kdekoliv. Panel zapisuje, a proto potřebuje Livewire hostitele, který commit přijme. `PanelComponent` je tím hostitelem; pokud už komponentu máš, použij místo dědění trait [`WithEditablePanel`](#prime-pouziti-traitu).

## Editovatelné entry

Každé editovatelné entry je navázané na atribut záznamu podle svého názvu (`ToggleEntry::make('is_active')` čte i zapisuje `is_active`). Sdílejí fluent Foundation slovník (label, ikona, barva, column span, viditelnost) s infolist entry a poli formulářů.

| Entry | Ovládací prvek | Zapisuje |
| --- | --- | --- |
| `ToggleEntry` | Switch | boolean |
| `CheckboxEntry` | Checkbox | boolean |
| `SelectEntry` | Rozbalovací seznam | zvolenou hodnotu |
| `TextInputEntry` | Textové pole (uložení při blur / Enter) | string |

### ToggleEntry & CheckboxEntry

Dvě vykreslení téhož booleovského zápisu. Toggly berou barvy on/off dráhy z kanonické palety; obě před uložením převedou hodnotu na boolean.

```php
use NyonCode\WireCore\Panels\Components\ToggleEntry;
use NyonCode\WireCore\Panels\Components\CheckboxEntry;

ToggleEntry::make('is_active')
    ->label('Aktivní')
    ->onColor('success')     // kanonická paleta HasColor
    ->offColor('gray');

CheckboxEntry::make('accepts_marketing')
    ->label('Marketingové e-maily')
    ->color('primary');      // barva zaškrtnutí
```

### SelectEntry

Options přijímají prostou mapu `hodnota => popisek` nebo název backed-enum třídy (přeložený stejným `EnumResolver` jako `Select` pole a `SelectColumn`):

```php
use NyonCode\WireCore\Panels\Components\SelectEntry;

SelectEntry::make('status')
    ->options(OrderStatus::class)   // nebo ['open' => 'Otevřeno', 'closed' => 'Zavřeno']
    ->placeholder('Vyber stav');
```

### TextInputEntry

Inline textové pole, které se uloží při blur a Enter, s návratem přes Escape. Typ HTML inputu zvolíš přes `type()`:

```php
use NyonCode\WireCore\Panels\Components\TextInputEntry;

TextInputEntry::make('name')->rules(['required', 'min:2']);
TextInputEntry::make('price')->type('number')->rules(['numeric', 'min:0']);
```

## Validace

Pravidla běží na serveru **před** zápisem; neplatná hodnota je odmítnuta a ovládací prvek zobrazí chybu inline bez uložení.

```php
TextInputEntry::make('email')->rules(['required', 'email']);
```

## Hlídání editací

`disabled()` vykreslí prvek neinteraktivní **a** odmítne zápis na serveru (klientský stav je jen kosmetika — podvržený požadavek je stejně zablokován). Přijímá closure, která dostane záznam:

```php
ToggleEntry::make('is_published')
    ->disabled(fn (Post $record) => $record->is_locked);
```

Pro autorizaci `permission()` odmítne zápis, pokud aktuální uživatel neprojde `hasPermissionTo()`:

```php
SelectEntry::make('role')
    ->options(Role::class)
    ->permission('assign-roles');
```

Zapsat lze jedině entry, které ve schématu deklaruješ jako editovatelné — název read-only `TextEntry`, ani žádný atribut mimo schéma, hostitel neuloží. To je zapisovací whitelist.

## Vlastní ukládání & vedlejší efekty

Ve výchozím stavu entry zapíše vlastní atribut. Přepiš to přes `saveUsing()` a spusť vedlejší efekt po úspěšném zápisu přes `afterStateUpdated()`:

```php
ToggleEntry::make('is_active')
    ->saveUsing(fn (User $record, $value) => $record->forceFill(['is_active' => $value])->save())
    ->afterStateUpdated(fn (User $record, $value) => activity()->log("toggled {$record->id}"));
```

## Optimistické UI & zámek

Každý commit prvek okamžitě aktualizuje, pak se srovná se serverem:

- **Úspěch** — hodnota zůstane a verze `updated_at` záznamu se posune.
- **Selhání** — prvek se vrátí na poslední potvrzenou hodnotu a zobrazí zprávu inline.
- **Konflikt** — pokud se záznam od načtení panelu změnil jinde (jeho `updated_at` už nesedí), zápis je odmítnut, prvek převezme aktuální serverovou hodnotu a uživatel vidí poznámku „změněno jinde". Žádné ztracené aktualizace.

Záznam se vždy dohledá na serveru z vlastního navázaného záznamu komponenty uvnitř zamčené transakce — klient nikdy nevolí, který řádek ani sloupec se zapíše.

## Přímé použití traitu

Pokud už Livewire komponentu máš, místo dědění `PanelComponent` použij trait `WithEditablePanel`. Implementuj `panel()`, vykresli panel ve svém view a přidej partial se sdílenými assety:

```php
use Livewire\Component;
use NyonCode\WireCore\Panels\Concerns\WithEditablePanel;
use NyonCode\WireCore\Panels\Panel;

class Dashboard extends Component
{
    use WithEditablePanel;

    public Account $account;

    public function panel(): Panel
    {
        return Panel::make()->record($this->account)->schema([
            ToggleEntry::make('two_factor_enabled')->label('Dvoufaktorové ověření'),
        ]);
    }
}
```

```blade
<div>
    @include('wire-core::partials.floating-assets') {{-- načte engine wireEditableCell --}}
    {{ $this->panel() }}
</div>
```

## Panely vs. infolisty vs. formuláře

| | Infolist | Panel | Formulář |
| --- | --- | --- | --- |
| Účel | Zobrazit jeden záznam | Editovat jeden záznam na místě | Editovat s krokem odeslání |
| Zapisuje | Nikdy | Po každé změně, přímo do záznamu | Při uložení, ze stavového bufferu |
| Hostitel | Jakýkoliv (bezstavový) | Livewire (`PanelComponent` / trait) | Livewire |
| Použij když | Read-only detail | Rychlé inline editace, obrazovky nastavení | Vícepolní formuláře, průvodci, náročná validace |

Sáhni po panelu, když má editace jednoho záznamu působit jako přepínání switchů na stránce nastavení; sáhni po [formuláři](../forms/overview.md), když chceš vědomé odeslání.
