---
order: 25
---

# Reaktivní pole

Pole mohou reagovat na hodnoty druhých bez opuštění schématu. Každá dynamická field
closura — `visible()`, `hidden()`, `disabled()`, `afterStateUpdated()` — se vyhodnocuje se
state accessory ve stylu Filamentu resolvovanými proti **živému** Livewire state bagu. Stejné API
funguje v samostatném `WithForms` formuláři i uvnitř modalu akce tabulky.

## State accessory

| Accessor | Vrací |
| --- | --- |
| `$get('field')` | Aktuální hodnotu jiného pole (souseda pod stejným `statePath`). |
| `$get()` | Vlastní hodnotu tohoto pole, čtenou **live** — odráží `$set` provedený dříve ve stejné closuře. |
| `$set('field', $value)` | Zapíše jiné pole. StateContainer-safe, takže funguje i uvnitř action modalů. |
| `$state` | Hodnotu tohoto pole, snapshotnutou při vyvolání closury. |
| `$component` | Instanci pole. |

Sáhněte po `$get` / `$set` místo `Livewire::current()` + `data_get()` — accessory za vás vyresolvují
správný prefix bagu, včetně bagu `tableState.modal.action.formData` action modalu.

## Podmíněná pole

Zobrazit pole jen když má jiné pole danou hodnotu. Pole takto skryté se také **přeskočí
během validace**, takže pravidlo `required()` nikdy nezablokuje odeslání, dokud je pole skryté:

```php
Select::make('type')
    ->options(['business' => 'Business', 'person' => 'Person'])
    ->live(),

TextInput::make('vat_id')
    ->visible(fn ($get) => $get('type') === 'business')
    ->required(),
```

Řídicí pole musí být `->live()`, aby jeho změna dojela na server a znovu vyhodnotila
viditelnost závislého pole.

Layoutové komponenty dostávají stejné accessory, takže celý `Grid`, `Section` nebo `Fieldset` může
zobrazit nebo skrýt podle hodnoty souseda:

```php
Section::make('Billing')
    ->schema([
        TextInput::make('vat_id'),
        TextInput::make('company'),
    ])
    ->visible(fn ($get) => $get('type') === 'business'),
```

<a id="field-actions-and-buttons"></a>
## Field akce a tlačítka

Připojte interaktivní `Action` k affixu inputu nebo oblasti hintu pomocí `suffixAction()`,
`prefixAction()` nebo `hintAction()`. Callback akce běží na serveru se stejným
kontextem `$get` / `$set` / `$state` — ideální pro lookup, „generate" helper nebo inline
verify tlačítko:

```php
use NyonCode\WireCore\Actions\Action;

TextInput::make('title')->suffixAction(
    Action::make('to_upper')
        ->icon('heroicon-o-arrow-up')
        ->action(fn ($get, $set) => $set('title', strtoupper((string) $get('title')))),
);
```

Pro samostatné tlačítko stylované design-systémem navázané na closuru — místo surového
`Html::make()->content('<button …>')`, které obchází paletu — použijte pole `Button`. Jeho
prezentace (`label` / `icon` / `color` / `size` / `outlined`) zrcadlí akce:

```php
use NyonCode\WireForms\Components\Button;

Button::make('generate_slug')
    ->label('Generate slug')
    ->icon('heroicon-o-sparkles')
    ->action(fn ($get, $set) => $set('slug', Str::slug((string) $get('title')))),
```

Obojí funguje v samostatném `WithForms` formuláři i uvnitř action modalu tabulky — hostitelův
endpoint `callFieldAction()` znovu vyresolvuje pole z živého schématu a spustí closuru.

## `afterStateUpdated()`

Spustit callback po změně hodnoty pole. Registrace callbacku **automaticky zapne
`live()`** — bez server round-tripu by hook nikdy nemohl vystřelit. Callback dostane novou
hodnotu jako `$state`, předchozí hodnotu jako `$old`, plus `$get` / `$set` / `$component`:

```php
TextInput::make('type')
    ->afterStateUpdated(function ($state, $old, $get, $set) {
        // Auto-naplnit závislé pole z právě zadané hodnoty.
        $set('vat_id', $state === 'business' ? null : '');
    });
```

Libovolnou podmnožinu parametrů lze type-hintovat v libovolném pořadí — resolvují se podle názvu:

```php
TextInput::make('quantity')
    ->afterStateUpdated(fn ($state, $set) => $set('total', $state * 10));
```

Veškerá tato reaktivita — `afterStateUpdated()`, live validace, field akce, remote
select hledání a podmíněná viditelnost (`visibleWhen()` / `visible(fn ($get) => …)`) — funguje
i pro pole uvnitř `Repeater` položek: dispatch vyresolvuje pole per položka a
`$get`/`$set` čtou a zapisují do vlastního bagu té položky (takže `$set('slug', …)` na řádku 2 sáhne jen na
řádek 2). Podmíněné pole uvnitř repeateru se zobrazí nebo skryje podle stavu **své vlastní položky**,
ne svých sousedů.

Vícekrokové formuláře dostávají stejné zacházení: uvnitř Livewire hostitele samostatný
[Wizard](../core/schema/layout/wizard.md#validace-po-krocich) zvaliduje aktuální krok na serveru, než
„Další" postoupí, a neúspěšné odeslání skočí na první krok obsahující chybu.

## Předvyplnění formuláře z akce

Formulář action modalu čte a zapisuje bag `modal.action.formData`. Naplňte jeho počáteční hodnoty
pomocí `fillFormUsing()`. Callback dostane záznam pro řádkové akce (a `null` pro hlavičkové akce,
které záznam nemají):

```php
use NyonCode\WireCore\Actions\EditAction;

EditAction::make()
    ->form([
        TextInput::make('name')->required(),
        Select::make('role')->options(Role::class),
    ])
    ->fillFormUsing(fn ($record) => [
        'name' => $record->name,
        'role' => $record->role->value,
    ]);
```

Uvnitř toho formuláře se reaktivní accessory výše chovají přesně jako v samostatném formuláři — `$get`,
`$set` a `afterStateUpdated()` se všechny resolvují proti živému modal bagu.

<a id="modal-footer-actions"></a>
## Akce v patičce modalu

Přidejte extra tlačítka do patičky action modalu pomocí `modalFooterActions()`. Každý
callback `ModalFooterAction` dostane živý form-data bag jako `$data`, `$set` writer pro něj,
`$component` a modalový `$record` / `$records` kontext — takže tlačítko v patičce může číst a
zapisovat rozpracovaný formulář bez jeho odeslání:

```php
use NyonCode\WireCore\Actions\ModalFooterAction;

EditAction::make()
    ->form([
        TextInput::make('name')->required(),
        TextInput::make('slug'),
    ])
    ->modalFooterActions([
        ModalFooterAction::make('generate_slug')
            ->label('Generate slug')
            ->icon('sparkles')
            ->action(fn ($data, $set) => $set('slug', Str::slug($data['name'] ?? ''))),

        ModalFooterAction::make('preview')
            ->position('after')      // 'before' (výchozí) nebo 'after' tlačítek Cancel/Submit
            ->submitsForm()          // zvalidovat formulář před během callbacku
            ->closesModal()          // poté zavřít modal
            ->action(fn ($data, $component) => $component->dispatch('preview', data: $data)),
    ]);
```

- `->submitsForm()` nejdřív zvaliduje modal formulář, takže validační chyby vyplavou před callbackem.
- `->closesModal()` zavře modal, jakmile se callback vrátí.
- `->position('before'|'after')` umístí tlačítko před nebo za vestavěné Cancel/Submit.
- `->requiresConfirmation()` se zeptá uživatele před během callbacku (nativní `wire:confirm`
  dialog s přeloženou výchozí zprávou); `->confirm('Really reset?')` nastaví vlastní zprávu.


---

<a id="field-partials"></a>
## Částečné renderování polí

Pole s `live()` posílá změnu na server při každé úpravě a server odpovídá
překreslením celé hostitelské komponenty. U dvanáctipolního formuláře je to
**19 860 B** HTML kvůli 1 562 B jednoho pole — 12,7× surově, 2,3× po gzipu — plus
morph toho všeho v prohlížeči.

`fieldPartials()` zařídí, že formulář odpoví poli, která se pohnula:

```php
public function form(Form $form): Form
{
    return $form
        ->statePath('data')
        ->fieldPartials()                                    // [tl! focus]
        ->schema([
            TextInput::make('name')->live(),
            TextInput::make('summary')
                ->helperText(fn () => 'Souhrn pro '.$this->data['name']),
        ]);
}
```

<a id="what-a-commit-answers-with"></a>
### Čím commit odpoví

Tři výsledky, a ten nejčastější neposílá vůbec nic:

| co se změnilo | co přijde zpátky |
|---|---|
| nic — běžné ťuknutí do klávesnice | ani view, ani region |
| markup jednoho či více polí | ta pole jako regiony |
| *množina* polí — sourozenec s `visibleWhen()` se objevil nebo zmizel | plný render |

První řádek lidi překvapí, a právě o to jde: hodnota pole jede přes `wire:model`
a datovou payload, ne přes markup. `TextInput` žádný atribut `value` nerenderuje,
takže jeho commit nikde markup nezmění a opravdu není co posílat. Markup se hne
jen tehdy, když se hne něco *odvozeného* — sourozenec, jehož `options()`,
`label()` nebo `helperText()` čte stav, objevivší se validační chyba, pole, které
se stalo disabled.

<a id="how-it-decides"></a>
### Jak se to rozhoduje

Tak, že pole vyrenderuje a porovná markup každého z nich s tím, co naposledy
poslal — ne tak, že by odvozoval, které pole na kterém závisí. To je záměr: graf
závislostí by musel rozumět každé closure, kterou konfigurace pole může nést, a to
jedno přehlédnuté by tiše ukazovalo zvětralou hodnotu. Jediné, čeho si tohle musí
všimnout, je odlišný markup — stejně, jako si `Table::rowPartials()` všímá
změněného řádku.

<a id="what-you-trade"></a>
### Co za to platíte

**View hostitele se při pokrytém commitu nepřekresluje.** Cokoliv kreslí *mimo*
formulář — živý náhled dat, nadpis počítající vyplněná pole — si drží předchozí
hodnotu až do dalšího plného renderu:

```blade
<div>
    <h2>{{ $this->data['name'] }}</h2>   {{-- při commitu pole se neaktualizuje --}}
    {{ $this->form }}
</div>
```

Pokud na tom stránka formuláře stojí, příznak nezapínejte. Nic jiného se
nemění: sourozenci s `visibleWhen()` zůstanou správně sami od sebe, protože
objevení nebo zmizení pole je změna tvaru, která spadne zpět na plný render.
