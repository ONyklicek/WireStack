---
order: 30
summary: Potvrzovací dialogy, slide-overy a vícekrokové wizardy — povrchy, které akce otevírá, a co každý z nich dělá s fokusem, stavem a odesláním.
---

# Modaly

Systém modalů pro potvrzovací dialogy, slide-overy a vícekrokové wizardy.

## Typy modalů

| Třída | Popis |
|-------|-------------|
| `Modal` | Standardní vycentrovaný modal |
| `ConfirmationDialog` | Modal s tlačítky potvrdit/zrušit |
| `SlideOver` | Panel vysouvající se zprava |
| `Wizard` | Vícekrokový wizard s navigací kroků |

## Potvrzovací dialog

Nejběžnější použití — spouštěné z akcí:

```php
Action::make('delete')
    ->requiresConfirmation()
    ->modalHeading('Delete this record?')
    ->modalDescription('This action cannot be undone.')
    ->modalIcon('trash', 'danger')
    ->modalSubmitActionLabel('Yes, delete')
    ->modalCancelActionLabel('Cancel')
    ->action(fn ($record) => $record->delete());
```

## Slide-over

Panel se vysouvá zprava:

```php
Action::make('details')
    ->slideOver()
    ->stickyHeader()
    ->stickyFooter()
    ->modalMaxHeight('60vh');
```

## Konfigurace modalu

```php
Action::make('edit')
    // Velikost
    ->modalWidth('2xl')              // sm, md, lg, xl, 2xl, 3xl, 4xl, 5xl, 6xl, 7xl, full

    // Chování zavírání
    ->closeModalOnClickAway()
    ->closeModalOnEscape()

    // Mobilní adaptace
    ->slideOverOnMobile()            // spodní sheet pod breakpointem (výchozí pro dialogy) [tl! focus:start]
    ->fullScreenOnMobile()           // celá obrazovka pod breakpointem
    ->dialogOnMobile()               // ponechá plovoucí dialog i pod breakpointem [tl! focus:end]
    ->mobileBreakpoint('md');        // kde se spustí mobilní podoba (sm|md|lg)
```

Jak se mobilní podoba volí a co každý dialog dělá se scrollováním, fokusem a
Escape, popisuje [Na telefonu](#na-telefonu).

Modal postavený přímo bere ještě jedno: `->mobileWidth(string $width)` nastaví
šířku, kterou panel použije pod tím breakpointem — pro případ, kdy nechcete ani
sheet, ani celou obrazovku.

**Potvrzení, kde není co potvrzovat** — hláška, kterou uživatel může jen vzít na
vědomí — je `->informative()`: zahodí odesílací tlačítko a to zbylé přejmenuje na
*Zavřít*, takže dialog, který se na nic neptá, ani nic nenabízí udělat.

## Na telefonu

Pod mobilním breakpointem dialog změní podobu; nad ním je každá varianta stejný
vycentrovaný dialog.

**Podoba se určí pro každý modal, v tomto pořadí:**

1. **Modal to řekne.** `->slideOverOnMobile()` → spodní sheet;
   `->fullScreenOnMobile()` → celá obrazovka; `->dialogOnMobile()` → plovoucí
   karta, jakou je na desktopu. Nastavení kteréhokoli z příznaků, na `true` *i*
   `false`, se počítá jako to, že modal mluví sám za sebe — `dialogOnMobile()`
   nastaví oba na `false`. Když jsou zapnuté oba, vyhraje sheet.
2. **Config.** Modal, který nenastavil ani jeden, se řídí
   `wire-core.modals.mobile`: `'sheet'` (výchozí), `'full-screen'` nebo
   `'dialog'`. Neznámá hodnota se čte jako `'sheet'`.

Takže **každý dialog je na telefonu spodní sheet, pokud něco neřekne jinak** —
včetně potvrzení. Výjimkou je desktopový **slide-over**: výchozí hodnota z
configu se týká dialogů a boční panel je na telefonu už od okraje k okraji, takže
se slide-over stane sheetem, jen když sám zavolá `->slideOverOnMobile()`.

```php
// config/wire-core.php
'modals' => [
    'mobile' => env('WIRE_MODAL_MOBILE', 'sheet'), // 'sheet' | 'full-screen' | 'dialog'
],
```

```php
Action::make('pickColour')
    ->form([/* … */])
    ->dialogOnMobile()               // malý dialog zůstane na telefonu kartou

Action::make('composeEmail')
    ->form([/* … */])
    ->fullScreenOnMobile()           // dlouhý formulář zabere celou obrazovku
```

Breakpoint je `->mobileBreakpoint()`, jinak `wire-core.mobile.breakpoint`
(`sm`, `< 640px`); `'md'` (`< 768px`) a `'lg'` (`< 1024px`) ho rozšíří. Viz
[Konfigurace → Mobil](../start/configuration.md#mobil).

**Co dělá sheet a celá obrazovka:**

- **Tělo se scrolluje uvnitř panelu**, takže tlačítka patičky zůstanou na
  obrazovce.
- **Sheet nese úchyt** a zavře se, když ho stáhnete dolů o víc než ~90px, nebo
  švihnete — 40px rychlostí přes půl pixelu za milisekundu — tak, jak hod čte
  nativní sheet. Kratší, pomalejší tah se vrátí zpět.
- **Výšky používají `dvh`**, ne `vh`: na telefonu je `vh` viewport se skrytými
  lištami prohlížeče a sheet s `85vh` dal svou patičku pod spodní lištu Safari.
  Sheet je vysoký nejvýše `92dvh`.
- **Bezpečné oblasti dostanou odsazení.** Patička varianty od okraje k okraji
  drží tlačítka nad home indikátorem (`env(safe-area-inset-bottom)`) a panel přes
  celou obrazovku se vyhne výřezu. Admin layouty nastavují `viewport-fit=cover`,
  aby tyto insety nebyly nulové; váš vlastní layout potřebuje
  `<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">`
  pro stejný efekt.

### Zámek scrollování

Stránka za **každým** otevřeným modalem zůstane na místě — dialog, sheet,
slide-over, potvrzení. Zámek sedí na `<html>` (iOS Safari ignoruje
`overflow: hidden` na `<body>` a pod prstem dál scrolluje stránku) a šířka
scrollbaru se vrátí jako padding, aby desktopová stránka neposkočila do strany.

Zámky se **počítají podle vlastníka**: modal navrstvený na modalu nebo sheet nad
dialogem uvolní stránku, až když se zavře ten poslední. Zámek se uvolní i ve
chvíli, kdy jeho element opustí stránku — modal akce obvykle odstraní re-render
ze serveru, ne zavření, a zámek čekající na `show = false` by ho přežil.

Dva způsoby, jak stejný zámek použít pro vlastní overlay:

```blade
{{-- zamkne, dokud je výraz pravdivý, a když je element odstraněn --}}
<div x-data="{ open: false }">
    <div x-show="open" x-scroll-lock="open">…</div>
</div>
```

```js
// imperativně — takto ho používá admin drawer
window.wireScrollLock.lock(drawerElement)
window.wireScrollLock.unlock(drawerElement)
```

Direktiva se registruje s Alpine bundlem wire-core; atribut
`data-wire-scroll-locked` je na `<html>`, dokud trvá jakýkoli zámek.

### Fokus a klávesnice

- **Kam padne fokus, když se dialog otevře:** na pole s `[autofocus]`, pokud ho
  dialog má; na dotykové obrazovce na dialog samotný, protože fokus na poli tam
  vytáhne klávesnici přes sheet, který uživatel ještě nečetl; jinak na první
  prvek, který **není** zavírací tlačítko. Potvrzení se otevře na **Zrušit**,
  takže Enter omylem nepotvrdí smazání. Fokus zůstává uvězněný v dialogu, dokud
  je otevřený.
- **Escape zavře dialog, jen když ho nic uvnitř nepoužilo.** Otevřený dropdown,
  select nebo výběr data uvnitř dialogu převezme první Escape a zavře sám sebe;
  dialog zůstane. Další Escape zavře dialog (s ohledem na
  `->closeModalOnEscape()`).
- **Popsané pro asistenční technologie.** Každý dialog má vlastní id titulku a
  popisu, napojená na `aria-labelledby` a (má-li popis) `aria-describedby`, takže dva
  navrstvené dialogy nikdy neukazují na titulek toho druhého.

## Modal config objekty

Místo fluent `->modal*()` setterů lze akci nakonfigurovat z
deklarativního modal objektu. Předejte jakýkoli `ModalContract` — `Modal`, `SlideOver`,
`ConfirmationDialog` nebo `Wizard` — do `->modal()`:

```php
use NyonCode\WireCore\Modals\ConfirmationDialog;
use NyonCode\WireCore\Modals\Modal;
use NyonCode\WireCore\Modals\SlideOver;
use NyonCode\WireCore\Modals\Wizard;

// Vycentrovaný dialog
Action::make('edit')->modal(
    Modal::make() // [tl! focus:start]
        ->heading('Edit record')
        ->description('Update the details below')
        ->width('lg')
        ->icon('pencil', 'primary') // [tl! focus:end]
);

// Slide-over panel (->mobileOnly() = slide-over na mobilu, modal na desktopu)
Action::make('view')->modal(
    SlideOver::make()->heading('Details')->width('xl') // [tl! focus]
);

// Potvrzovací dialog — s presety (delete / makeDanger / makeWarning / makeInfo)
Action::make('delete')->modal(
    ConfirmationDialog::delete('User') // [tl! focus]
);

// Vícekrokový wizard (viz níže)
Action::make('create')->modal(
    Wizard::make()->heading('Create user')->steps([/* ModalStep::make(...) */]) // [tl! focus]
);
```

Hodnoty config objektu se přeloží do modal stavu akce a
vykreslí přes stejný runtime jako fluent settery — existuje jediný
kanonický vlastník modalu, takže se oba styly chovají identicky.

## Akce v patičce

Vlastní tlačítka v patičce modalu:

```php
use NyonCode\WireCore\Actions\ModalFooterAction;

Action::make('edit')
    ->form([...])
    ->modalFooterActions([
        ModalFooterAction::make('save')
            ->label('Save')
            ->color('primary')
            ->submitsForm(),

        ModalFooterAction::make('save-and-close')
            ->label('Save & Close')
            ->action(fn () => $this->saveAndClose()),

        ModalFooterAction::make('cancel')
            ->label('Cancel')
            ->color('gray')
            ->outlined()
            ->closesModal(),                 // zavře modal

        ModalFooterAction::make('reset')
            ->requiresConfirmation()         // zeptá se před spuštěním (nativní wire:confirm)
            ->confirm('Really reset the form?') // …nebo s vlastní zprávou
            ->action(fn ($set) => $set('name', '')),
    ]);
```

<a id="multi-step-wizard"></a>
## Vícekrokový wizard

Dejte akci více kroků pomocí `->steps([...])` (nebo `Wizard` objektu přes
`->modal()`). Modal vykreslí indikátor kroků s navigací **Back / Next / Submit**;
každý krok validuje před postupem, data jsou sdílená napříč všemi
kroky a finální odeslání znovu zvaliduje každý krok.

```php
use NyonCode\WireCore\Actions\ModalStep;

Action::make('create')
    ->steps([
        ModalStep::make('Basic Info')
            ->description('Enter user details')
            ->icon('user')
            ->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required(),
            ])
            ->validation(['name' => 'required|min:2']),    // extra pravidla pro každý krok

        ModalStep::make('Settings')
            ->schema([
                Select::make('role')->options([...]),
                Toggle::make('active'),
            ])
            ->afterValidation(fn (array $data) => logger('step 2 passed', $data)),

        ModalStep::make('Review')
            ->before(fn (array $data) => ['summary' => "Creating {$data['name']}"]) // předvyplnit
            ->schema([
                Placeholder::make('summary')
                    ->content(fn ($data) => $data['summary'] ?? ''),
            ]),
    ])
    ->action(fn ($record, $data) => User::create($data));
```

Každý krok zapisuje do stejného form-data bagu, takže dříve zadané hodnoty přetrvají,
jak se uživatel pohybuje tam a zpět. Validace na **Next** spustí field pravidla kroku
(přes form runtime), pak jakákoli pravidla `->validation()`, pak
hook `afterValidation`; hook `before` dalšího kroku může vrátit pole, aby ho
předvyplnil. **Submit** znovu zvaliduje každý krok kumulativně (hooky
`afterValidation` se nespouští znovu, takže nikdy nevystřelí dvakrát).

### Přizpůsobení popisků navigace

Popisky wizardu **Back**, **Next** a submit-in-progress **Saving…** jsou
konfigurovatelné a spadají na přeložitelné výchozí hodnoty
(`wire-core::actions.{wizard_previous,wizard_next,submit_saving}`):

```php
Action::make('create')
    ->steps([/* ... */])
    ->modalPreviousActionLabel('Back')
    ->modalNextActionLabel('Continue')
    ->modalSubmitActionLabel('Create user')
    ->modalSavingLabel('Creating…');
```

[`modalFooterActions()`](../forms/reactive-fields.md#akce-v-paticce-modalu) se vykreslí
i v patičce wizardu, vedle Back / Next / Submit.

### Sestavení kroku z dřívějších dat

Předejte Closure do `->schema()` pro sestavení polí kroku z hodnot zadaných v
předchozích krocích. Closura dostane naakumulovaný form-data bag, takže pozdější krok
se může přizpůsobit dřívější volbě. Funguje to pro řádkové, hromadné **i** hlavičkové akce —
hlavičková akce nenese žádný záznam, takže její step closury stále dostanou živý
form-data bag (ne `null`):

```php
HeaderAction::make('create')
    ->steps([
        ModalStep::make('Type')
            ->schema([
                Select::make('kind')
                    ->options(['business' => 'Business', 'person' => 'Person']),
            ]),

        ModalStep::make('Details')
            // $data drží vše zadané doposud (zde: 'kind' z kroku 1).
            ->schema(fn (array $data) => [
                TextInput::make('name')->required(),
                ...($data['kind'] === 'business'
                    ? [TextInput::make('vat_id')->required()]
                    : [TextInput::make('birth_date')]),
            ]),
    ])
    ->action(fn (array $data) => Customer::create($data));
```

### API ModalStep

```php
ModalStep::make(string $label)
    ->description(?string $description)
    ->icon(string|Icon|null $icon)
    ->schema(array|Closure $fields)
    ->validation(array|Closure $rules)         // extra pravidla, klíčovaná názvem pole
    ->validationMessages(?array $messages)
    ->afterValidation(Closure $callback)       // běží po validaci kroku
    ->before(Closure $callback)                // běží před zobrazením kroku; vrátit pole pro předvyplnění
```

## Halt modal

`ActionHalt` vytvoří sekundární potvrzovací modal uprostřed vykonávání:

```php
Action::make('process')
    ->before(function ($record, Action $action) {
        if ($record->has_warnings) {
            $action->halt()                                    // [tl! focus:start]
                ->heading('Warnings Detected')
                ->description('There are unresolved warnings. Continue anyway?')
                ->icon('exclamation', 'warning')
                ->submitLabel('Continue')
                ->cancelLabel('Cancel')
                ->width('md');                                 // [tl! focus:end]
        }
    })
    ->action(fn ($record) => $record->process());
```

### API ActionHalt

Halt je modal, takže bere slovník modalů — tytéž `heading()` a `description()`,
které používají třídy na této stránce:

```php
->heading(string|Closure|null $heading)
->description(string|Closure|null $description)
->icon(string|Icon|null $icon, string|Color|null $color = null)
->submitLabel(?string $label)
->cancelLabel(?string $label)
->width(string|ModalWidth $width)
->closeOnClickAway(bool $close = true)
->closeOnEscape(bool $close = true)
->validation(array $rules, ?array $messages = null, ?array $attributes = null)
```

Celý povrch a to, co halt dělá s `before()` hooky při potvrzeném průchodu, najdete
v [Životním cyklu a frontách](actions/lifecycle.md#api-haltu).

## Blade komponenty

```blade
<x-wire-modals::modal />
<x-wire-modals::confirmation />
```
