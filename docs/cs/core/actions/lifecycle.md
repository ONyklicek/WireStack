---
order: 30
summary: "Co běží kolem callbacku — před, po, při selhání — jak běh zastavit zevnitř hooku a co se změní, když práce patří na frontu."
api_class: NyonCode\WireCore\Actions\ActionHalt
---

# Lifecycle a fronty

Mezi kliknutím a notifikací je pevné pořadí kroků a u každého z nich je hook,
který si můžeš vzít. Tahle stránka je to pořadí — co běží kdy, co který hook
dostane, jak běh zevnitř zastavit a co je jinak, jakmile je práce dost dlouhá na
to, aby patřila na frontu.

## Lifecycle hooky

Pořadí je pevné a u každého kroku je místo, které si můžeš vzít:

| Krok | Co běží | Co je dobré vědět |
| --- | --- | --- |
| 1. Modal | potvrzení, formulář, infolist nebo wizard, pokud je akce deklaruje | jeho formulář se validuje dřív, než pipeline začne |
| 2. `before()` | tvoje callbacky, v pořadí deklarace | **přeskočí se při opakovaném běhu** po potvrzeném haltu (tam je `$confirmed` true) |
| 3. `action()` | samotná práce | dostane `$record` / `$records`, `$data`, `$confirmed`, `$halt` |
| 4. Redirect a notifikace | redirect výsledku a pak `successNotification()` nebo `failureNotification()` | zaznamenává se tady, *před* krokem 5 |
| 5. `after()` | tvoje callbacky | **přeskočí se, když běh haltnul**, takže vedlejší efekty haltu nemůžou proběhnout dvakrát |

Tři důsledky, které je dobré mít v hlavě, než napíšeš hook:

- **`before()`, který haltne, zastaví všechno za sebou.** Práce neproběhne a after
  callbacky taky ne — což je smyslem věci, ale zároveň to znamená, že úklid do
  `after()` nepatří.
- **O notifikaci je rozhodnuto dřív, než `after()` proběhne.** After callback
  nemůže změnit, co se uživatel dozví; ať si radši vyvolá vlastní notifikaci.
- **Akce na frontě odchází dřív, než se pipeline postaví.** `before()` a `after()`
  jsou práce prohlížeče, takže job spustí callback a nic jiného — viz
  [Běh na frontě](#beh-na-fronte).

```php
Action::make('publish')
    ->before(fn ($record) => $record->validate())
    ->action(fn ($record) => $record->update(['status' => 'published']))
    ->after(fn ($record) => event(new Published($record)))
    ->successNotification('Published!')
    ->failureNotification('Publish failed.');
```

## Halt vykonávání

Halt pozastaví vykonávání a zobrazí sekundární modal pro potvrzení uživatelem:

```php
Action::make('process')
    ->before(function ($record, Action $action) {
        if ($record->has_warnings) {
            $action->halt()
                ->heading('Warnings Detected')
                ->description('There are unresolved warnings. Continue anyway?');
        }
    })
    ->action(fn ($record) => $record->process());
```

Halt může nést vlastní formulář — otázku, na kterou akce v průběhu zjistila, že
potřebuje odpověď. Po potvrzení se akce vykoná znovu, s `$confirmed` true a
hodnotami z halt formuláře sloučenými do `$data`:

```php
Action::make('archive')
    ->action(function ($record, array $data, bool $confirmed, callable $halt) {
        if (! $confirmed) {
            return $halt()                                        // [tl! focus:start]
                ->heading('Why is this being archived?')
                ->form([
                    Select::make('reason')->options(ArchiveReason::class),
                    DateTimePicker::make('review_at'),
                ]);                                               // [tl! focus:end]
        }

        $record->archive($data['reason'], $data['review_at']);
    });
```

Halt formulář se před opětovným spuštěním akce zvaliduje — nejdřív vlastní
pravidla jeho polí, pak případná extra pravidla, která halt deklaroval nad celým
bagem:

```php
$halt()
    ->form([TextInput::make('reason')->required()->minLength(10)])
    ->validation(['reason' => 'not_in:test'], ['reason.not_in' => 'Zadej skutečný důvod.']);
```

Při chybě zůstane modal otevřený se zprávou u pole a akce se znovu nespustí.
Deklarovaná pravidla se píšou proti holým názvům polí a hlásí se u odpovídajícího
pole.

`$data` je i tady dehydratovaná, za stejných podmínek jako u action modalu —
pole halt formuláře tvarují své vlastní hodnoty a klíče, které si halt přinesl
z prvního pokusu, projdou beze změny.

### Halt, který jen něco sděluje

Ne každý halt je otázka. `informative()` — nebo `noSubmit()` tam, kde se to čte
líp — zahodí odesílací tlačítko, formulář i jeho pravidla a nechá modal s jedinou
cestou ven. Akce se znovu nespustí, protože není co potvrzovat:

```php
$action->halt()
    ->informative()                                  // [tl! focus]
    ->heading('Není co exportovat')
    ->description('Filtru, který jsi zadal, neodpovídá žádný řádek.');
```

### Kde se dá halt vyvolat

Kdekoli běží akce. Pipeline, která ho vyvolá, žije ve `wire-core`, takže halt
funguje stejně v tabulce, na stránce resourcu i na obyčejné Livewire komponentě
skládající `WithActions` — a to poslední začalo platit až ve 2.0, kdy se
vykreslení modalu přesunulo do core vedle enginu. Předtím halt mimo tabulku
nastavil stav, který nikdo nekreslil: akce se zastavila a obrazovka mlčela.

Jediné, co hostitel haltu dluží, je modal host, který stejně vykresluje kvůli
modálům akcí:

```blade
<x-wire-actions::modal-host :component="$this" />
```

**Halt s poli potřebuje cache store, který přežije request.** Schéma je
deklarované uvnitř callbacku, takže ho nejde na dalším renderu postavit znovu tak
jako formulář akce: odloží se do cache pod id komponenty a čte se zpět při každém
renderu, dokud se halt nezavře. Na storu, který si nic nenechá (`array`, nebo
žádný), se modal pořád otevře a pořád validuje — jen se po neúspěšné validaci
vrátí bez polí. Haltu bez formuláře se to netýká.
### Halt úplně bez akce

Halt nefunguje díky akci. Ten objekt je popis modalu, jeho stav je pět klíčů a
jeho pokračování je jméno metody plus skaláry — nic z toho není o akcích a od 2.0
už nic z toho ani není uvnitř nich. `InteractsWithHalt` je ten mechanismus sám o
sobě, takže se zastavit a zeptat umí **kterákoli** Livewire komponenta:

```php
use NyonCode\WireCore\Actions\ActionHalt;
use NyonCode\WireCore\Actions\Concerns\InteractsWithHalt;

class OrderCard extends Component
{
    use InteractsWithHalt;                                       // [tl! focus]

    public function archive(): void
    {
        $this->halt(                                             // [tl! focus:4]
            ActionHalt::confirmDanger('Archivovat tuhle objednávku?', 'Zmizí z aktivního seznamu.'),
            then: 'archiveConfirmed',
            arguments: ['id' => $this->order->id],
        );
    }

    public function archiveConfirmed(array $data, array $arguments): void
    {
        Order::findOrFail($arguments['id'])->archive();          // [tl! focus]
    }
}
```

```blade
{{-- Jen pro komponentu, která halty vyvolává bez action runtimu; hostitel,
     který vykresluje modal host akcí, je kreslí s ním. --}}
<x-wire-actions::halt-host :component="$this" />
```

**Pokračování je jméno, ne closure**, a není to omezení implementace — je to to,
co dovolí hranice requestu. Halt se kreslí, čte a odpovídá na *pozdějším*
requestu, než na kterém vznikl, a closure takovou cestu nepřežije. Halt proto
veze to, co action pipeline vezla vždycky: jméno metody a skaláry. `$then` se po
potvrzení zavolá jako `$then(array $data, array $arguments)`, kde `$data` je to,
co halt vybral.

`$then` musí pojmenovat **veřejnou** metodu, a není to konvence, ale hranice: stav
haltu je veřejná Livewire property, takže prohlížeč umí to jméno před potvrzením
přepsat. Veřejnou metodu si zavolat umí i tak, takže tudy nezískává nic nového —
privátní nebo chráněná by byla cesta dovnitř, a proto se odmítá.

Čeho se komponenta bez `wire-forms` vzdá, jsou pole: `form()` potřebuje formulářovou
vrstvu, aby schéma vykreslila, zvalidovala a dehydrovala. Halt je tam potvrzení —
nadpis, popis, dvě tlačítka — a pravidla, která deklaruje, se pořád kontrolují
proti datům, se kterými se odešle. Hostitel skládající `WithActions` nebo
`WithTable` formulářovou vrstvu má a nepřichází o nic.

### API haltu

Halt **je** modal, takže mluví slovníkem, který vlastní třídy modálů —
`heading()`, `description()`, `width()`, `closeOnEscape()`. **Akce** tytéž věci
prefixuje (`modalHeading()`, `modalWidth()`), protože akce je tlačítko, které
modal *má*, a její vlastní `icon()` a `color()` patří tomu tlačítku. To je celé
pravidlo za tím, co může vypadat jako dva zápisy jedné věci.

```php
->heading(string|Closure|null $heading)              // titulek modalu; closure se vyhodnotí hned, viz níž
->description(string|Closure|null $description)      // věta pod ním
->icon(string|Icon|null $icon, string|Color|null $color = null)
->color(string|Color|null $color)                    // akcent a barva odesílacího tlačítka
->danger(bool $danger = true)                        // záměr, ne odstín: barvu vyplní, jen když ji nikdo nezvolil
->width(string|ModalWidth $width)                    // 'sm'|'md'|'lg'|'xl'|'2xl'…'7xl'|'full' — výchozí 'md'
->maxHeight(string $maxHeight)                       // CSS délka, po které se tělo začne scrollovat
->closeOnClickAway(bool $close = true)               // výchozí true
->closeOnEscape(bool $close = true)                  // false pro potvrzení, které stojí za přečtení
->id(string $id)                                     // stabilní DOM id, když ho musí adresovat něco zvenčí
->submitLabel(?string $label)                        // výchozí: „Potvrdit" z frameworku
->cancelLabel(?string $label)
->informative(bool $informative = true)              // bez odeslání, bez formuláře, bez pravidel — slepá ulička se Zavřít
->noSubmit(bool $noSubmit = true)                    // totéž pod jménem, které se na místě volání čte líp
->form(array|ModalForm $fields)                      // pole, na která se odpoví, než se akce spustí znovu
->validation(array $rules, ?array $messages = null, ?array $attributes = null)
->fillForm(array $data)                              // předvyplnění halt formuláře — klíče jsou holé názvy polí
->skipBeforeOnConfirm(bool $skip = true)             // výchozí true; false spustí before() i při potvrzeném průchodu
->redirectAfterConfirm(?string $url)                 // kam jít, jakmile potvrzení projde
```

Pět presetů nastaví nadpis, text, ikonu a barvu jedním voláním:

```php
->confirmDelete(?string $recordName = null)
->confirmDanger(string $heading, ?string $description = null)
->confirmWarning(string $heading, ?string $description = null)
->info(string $heading, ?string $description = null)
->success(string $heading, ?string $description = null)
```

Tři z nich mají pravidlo, které stojí za to říct nahlas, protože každé z nich
byla past:

- **Closure v nadpisu se vyhodnotí, když ji napíšeš, ne když se modal kreslí.**
  Halt se serializuje do stavu komponenty v okamžiku vyvolání a scope, který by
  closure uměl odpovědět, je při dalším requestu pryč.
- **`form()` a `informative()` se navzájem přebijí, platí to poslední.**
  `informative()` zahodí formulář i jeho pravidla; deklarace formuláře potom
  vezme halt z informativního režimu zpátky. Před 2.0 o tom potichu rozhodovalo
  pořadí: `->informative()->form([...])` si instanci nechal, žádná pole nevykreslil
  a modal zůstal bez odeslání.
- **`skipBeforeOnConfirm(false)` teď hooky opravdu spustí znovu.** Před 2.0 ten
  setter nikdo nečetl. `before()`, který halt vyvolává, se musí pohlídat přes
  `$confirmed`, jinak vyvolá tentýž halt znovu — a proto je výchozí chování
  přeskočit je.

## Běh na frontě

Většina akcí má zůstat synchronní — kdo klikne na Smazat, čeká, že řádek bude po
návratu stránky pryč, a přesunout to na workera nekoupí nic než race.
`->queue()` je pro dlouhý ocas: hromadná akce nad deseti tisíci řádky,
přepočet, který by vytimeoutoval.

```php
Action::make('recalculate')
    ->queue()                   // [tl! focus:3]
    ->onQueue('reports')        // pojmenovat frontu implikuje ->queue()
    ->onConnection('redis')
    ->action(fn ($records) => Report::rebuild($records));
```

Kliknutí dispatchne job a hned se vrátí; uživatel dostane notifikaci „běží na
pozadí" a druhou, až to doběhne.

### Co přes hranici jde

**Jména a klíče, nikdy objekty.** Job veze třídu hostitele, jméno akce, klíče
záznamů a odeslaná data formuláře — samé skaláry. Ne akci, ta drží closury; ne
modely, ty by byly zastaralé, než je worker vezme, a u hromadné akce nad deseti
tisíci řádky by to byl megabajt payloadu. Job hostitele znovu postaví, zeptá se
ho na akci podle jména a záznamy načte čerstvé.

Stojí za to o tom vědět, ne to schovávat: řádek změněný mezi kliknutím a během
se zpracuje **v podobě, v jaké je při běhu jobu**, ne v jaké byl při zařazení.

### Co přes ni nejde

Frontovaná akce **nemá prohlížeč**. Bindingy, které synchronní callback dostane
pro modál — `$set`, `$setParent`, `$close`, `$replace`, `$halt` — vyhazují
výjimku, nejsou to no-opy:

```php
Action::make('recalculate')->queue()->action(function ($records, $close) {
    Report::rebuild($records);
    $close();   // QueuedActionException: potřebuje prohlížeč, který už nemá
});
```

No-op by vypadal, že fungoval, a vývojář by se to dozvěděl, až by uživatel
nahlásil, že se modál nezavřel. Hlas se místo toho notifikací — přesně na to je
[databázový driver](../notifications/index.md), protože request, který job spustil, už
touhle dobou není.

Akce přejmenovaná nebo smazaná mezi dispatchem a během vyhodí výjimku ze
stejného důvodu: job veze jméno, takže není co spustit, a říct to je lepší než
selhat potichu.

## Plugin hooky: action.executing / action.executed

Vedle callbacků jedné akce výše se kolem **každé** akce spouští dva plugin hooky —
a právě po nich sáhne nainstalovaný balíček, protože builder té akce nikdy nedrží:

```php
$manager->hook(Hook::ActionExecuting, function (ActionExecutingPayload $payload) {
    Log::info('běží', ['action' => $payload->actionName]);   // [tl! focus]

    return $payload;
}, for: 'invoices');
```

`for:` zúží callback na jednoho hostitele — registrovaný klíč, který stránka
deklaruje, nebo její třídu. Bez něj callback běží pro každou akci v aplikaci.

**Ve stejný okamžik, deset řádků od sebe, se spouští i Laravel event a ty dva
nejsou zaměnitelné.** `ActionExecuting` a `ActionExecuted` jsou ta pozorovací
půlka: audit, telemetrie a metriky patří tam a změnit běh neumí. Hook je ta půlka,
která to umí. Viz [Hooky](../plugins/hooks.md).

## Související

- [Akce](index.md) — callback, který tyhle hooky obklopují
- [Modály akcí](modals.md) — co může běžet dřív než callback
- [Notifikace](../notifications/index.md) — jak se dokončený nebo selhaný běh ohlásí
- [Save lifecycle](../../forms/save-lifecycle.md) — tentýž nápad na straně formuláře
