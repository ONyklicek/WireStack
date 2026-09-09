---
order: 20
summary: "Notifikace na obrazovce — kde je namountovaná, jak dlouho vydrží a jak ji spustit z JavaScriptu."
---

# Toasty

Toast je notifikace, kterou vidíš a pak už ne: objeví se v rohu, vydrží tak
dlouho, jak si její závažnost zaslouží, a zmizí. Tahle stránka je driver, který ho
kreslí, Blade komponenta, která ho hostí, a jeden JavaScriptový vstupní bod pro
kód, který se PHP nikdy nedotkl.

## Toasty

Vše níže vykresluje `<x-wire-notifications::toast-container />` — drivery jen odesílají payloady; kontejner rozhoduje, jak toast vypadá a jak se chová.

### Odpočtová lišta

Každý auto-zavírající toast má u spodní hrany tenkou **odpočtovou lištu**, která ubývá, jak toast stárne — uživatel tak vidí, za jak dlouho se zavře. **Najetí na jakýkoli toast pauzuje lištu i auto-zavření** (a po odjetí pokračuje). Lišta je defaultně zapnutá a obarvená podle typu notifikace.

- Je **volitelná** — `:progress="false"` ji skryje.
- **Trvalé toasty lištu nemají** — sticky toast neodpočítává, takže by neměl co ukazovat (viz níže).

```blade
<x-wire-notifications::toast-container :progress="false" />  {{-- bez odpočtové lišty --}}
```

### Trvalé toasty

`->persistent()` (nebo `->duration(0)`) udělá toast **sticky**: zůstane, dokud ho uživatel nezavře, a nemá odpočtovou lištu. Ideální pro zprávy vyžadující rozhodnutí.

```php
NotificationManager::send(
    Notification::warning('Platba potřebuje kontrolu, než se zúčtuje.')
        ->title('Vyžaduje akci')
        ->persistent()
);
```

### Akční tlačítka

Přidejte tlačítka, která po kliknutí dispatchnou Livewire událost — afordance „Undo". Hostitelská komponenta poslouchá přes `#[On(...)]`.

```php
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationAction;

// zkratka: label + Livewire událost
NotificationManager::send(
    Notification::success('Položka smazána')->action('Vrátit', 'restore-record')
);

// plná kontrola
NotificationManager::send(
    Notification::success('Objednávka #1042 uložena')->action(
        NotificationAction::make('Vrátit', 'restore-record')
            ->payload(['id' => 1042])   // odešle se s dispatchnutou událostí
            ->color('primary')          // akcent tlačítka (fallback na typ toastu)
            ->keepOpen()                // po kliknutí toast nezavírat
    )
);
```

```php
// v hostitelské Livewire komponentě
#[On('restore-record')]
public function restore(int $id): void
{
    // …
}
```

`NotificationAction` je immutable hodnotový objekt: `make(label, event)`, `->payload([...])`, `->color(...)`, `->keepOpen()`. Klik dispatchne `Livewire.dispatch(event, payload)` a (pokud není `keepOpen()`) zavře toast.

### Skládání a přetečení

- **`stack`** sbalí toasty do úhledné hromádky; najetí na hromádku je rozevře do plného seznamu. Nejnovější toast je nejblíže kotvící hraně.
- **`max`** omezí, kolik je jich vidět naráz; přebytek se sbalí do klikacího pillu **„+N more“**, který odhalí zbytek.

```blade
<x-wire-notifications::toast-container stack :max="5" />
```

### Přístupnost

Kontejner je `aria-live="polite"` region (error toasty používají `role="alert"`), takže screen readery toasty ohlašují, jak přicházejí. Ctí i **`prefers-reduced-motion`**: při požadavku na omezený pohyb se hromádka nikdy nesbaluje/nerozevírá a přechody karet jsou vypnuté.

## Spouštění toastů z JavaScriptu

Toast kontejner nainstaluje globální helper `window.wireToast` (a Alpine `$toast` magic), když se namountuje, takže můžete vyvolat toast rovnou z frontendu — bez server round-tripu. Helper jen odešle `eventName` window událost kontejneru se standardním payloadem (`type`, `message`, `title`, `duration`).

```js
// zkratka — type + message
wireToast.success('Saved');
wireToast.error('Something went wrong');
wireToast.warning('Careful');
wireToast.info('Heads up');

// s volbami (title, duration, …)
wireToast.success('Saved', { title: 'Done', duration: 6000 });

// plný payload objekt (type výchozí 'info' při vynechání)
wireToast({ type: 'success', message: 'Saved', title: 'Done' });
wireToast('Plain info toast');
```

Uvnitř Alpine použijte `$toast` magic:

```blade
<button @click="$toast.success('Copied!')">Copy</button>
```

Helper cílí na nakonfigurovaný `eventName` kontejneru, takže vlastní `event-name="my-toast"` je zapojeno automaticky. `window.wireToast` se nainstaluje jednou (vyhrává první kontejner); pokud vykreslíte více kontejnerů s různými názvy událostí, odešlete `CustomEvent` sami pro ty sekundární:

```js
window.dispatchEvent(new CustomEvent('my-toast', {
    detail: { type: 'success', message: 'Saved' },
}));
```

## Blade komponenta

Umístěte toast kontejner do svého layoutu:

```blade
<x-wire-notifications::toast-container />
```

Můžete přizpůsobit pozici, fallback trvání auto-zavření a browser událost, které naslouchá:

```blade
<x-wire-notifications::toast-container
    position="bottom-right"
    :duration="5000"
    event-name="table-notification" />
```

| Prop | Výchozí | Účel |
|------|---------|---------|
| `position` | `top-right` | `top-left` / `top-center` / `top-right` / `bottom-left` / `bottom-center` / `bottom-right` |
| `duration` | `4000` | fallback auto-zavření (ms) pro notifikace bez vlastního `duration` |
| `event-name` | `table-notification` | `window` událost, které naslouchá (`x-on:{eventName}.window`) |
| `progress` | `true` | zobrazit per-toast odpočtovou lištu (viz [Odpočtová lišta](#odpoctova-lista)) |
| `stack` | `false` | sbalit toasty do hromádky, která se na hover rozevře |
| `max` | `0` | omezit počet viditelných toastů (`0` = neomezeno); přebytek se sbalí do pillu „+N more“ |

## Související

- [Notifikace](index.md) — objekt, který se doručuje
- [Perzistentní notifikace](persistent.md) — když musí přežít stránku
- [Odkud je posílat](usage.md) — místa volání
- [Notifikace v tabulce](../../table/notifications.md) — co tabulka řekne sama od sebe
