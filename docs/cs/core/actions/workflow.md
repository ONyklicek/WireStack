---
order: 60
summary: "Které přechody stavů jsou legální, kde žije která podmínka a jak se odmítnutí dostane k tomu, kdo o přechod požádal."
---

# Workflow a přechody

Záznam, který se pohybuje mezi stavy, obvykle skončí s pravidly rozesetými po
controllerech a voláních `->update()`, kde nikde nestojí, které přechody jsou
legální. Tahle stránka je to jedno místo, kde to stojí — a zároveň hranice mezi
držením toho tvaru a workflow enginem, kterým tohle záměrně není.

## Workflow a přechody

Záznam, který prochází stavy — draft, potvrzeno, odesláno — obvykle skončí
s pravidly rozesetými po controllerech a `->update()` voláních, kde nikde není
napsané, které přesuny jsou legální. `WorkflowState` ten tvar drží na jednom
místě.

```php
use NyonCode\WireCore\Core\Workflow\WorkflowState;

$orders = WorkflowState::for(OrderStatus::class)
    ->column('status')                                              // [tl! focus:5]
    ->allow(OrderStatus::Draft, OrderStatus::Confirmed)
    ->allow(OrderStatus::Confirmed, OrderStatus::Shipped)
    ->allow([OrderStatus::Draft, OrderStatus::Confirmed], OrderStatus::Cancelled)
    ->guard(OrderStatus::Confirmed, fn ($order) => $order->lines()->exists())
    ->after(OrderStatus::Shipped, fn ($order) => ShipmentJob::dispatch($order));
```

`allow()` bere seznam výchozích stavů, protože „cokoli až sem jde zrušit" je
běžný tvar a napsat ho třikrát je způsob, jak na jeden ze tří zapomenout.

**Seam, ne workflow engine.** Vlastní tvar a význam deleguje: žádné definice
procesů, žádné modelování schvalování, žádný scheduler. Přechody ukládají
obyčejnou cestou, takže tenant scope i audit přijdou s sebou, aniž by se
drátovaly znovu.

Barva, popisek ani ikona tu záměrně nejsou. Status je enum implementující
`Enum\HasColor` / `HasLabel` / `HasIcon` a `BadgeColumn` to už vykresluje —
druhá mapa by byla paralelní slovník, který se rozejde.

### Dvě odmítnutí, která se chovají jinak

```php
$orders->transition($order, OrderStatus::Shipped);   // z draftu → výjimka
$orders->transition($order, OrderStatus::Confirmed); // bez položek → false
```

**Nelegální** přechod vyhodí výjimku: stroj říká, že ta hrana neexistuje, a mlčet
znamená nechat záznam tam, kde uživatel věří, že se posunul. **Guard veto** vrátí
false, protože „ještě ne" je doménová odpověď, ne rozbitý stroj — volající ji
ohlásí.

Guardy na jednom stavu musí projít všechny. Schvalovací limit a kontrola
úplnosti jsou samostatná pravidla a `&&` do jedné closury ztratí, které z nich
řeklo ne.

`after()` hooky běží až po uložení, takže hook, který dispatchne přepravu,
nemůže vystřelit pro save, který se pak vrátí zpět.

### Nabídnutí v UI

```php
use NyonCode\WireCore\Actions\TransitionAction;

TransitionAction::to(OrderStatus::Confirmed)->workflow($orders)
```

Ve všem ostatním obyčejná akce — stejný pipeline, stejná autorizace, umí
potvrzení i frontu. Přidává dvě věci, a obě fungují, aniž by povrch, který ji
kreslí, o nějakém workflow věděl:

**Sama se skryje tam, kde by přechod neprošel.** `isHidden($record)` — na což se
každý povrch akcí ptá před vykreslením a `canExecute($record)` před spuštěním —
skryje akci, dokud hrana neexistuje **nebo** neprojdou její guardy pro tenhle
záznam a tohohle uživatele. Akce nabídnutá k přechodu, který uživatel nemůže
dokončit, je akce existující proto, aby byla odmítnuta, a odmítnutí, které nešlo
předvídat, se čte jako chyba aplikace, ne jako pravidlo procesu.
`isAvailableFor($record, $user)` odpoví na půlku toho stroje samostatně, pro UI,
které se chce zeptat přímo.

**Stisk provede přechod.** Připojení workflow nastaví akci callback na přechod,
takže tlačítko nepotřebuje vlastní `->action()`. Explicitní `->action()` pořád
vyhraje, v libovolném pořadí.

Label, barvu i ikonu bere z cílového enumu stejnou kanonickou cestou jako
`BadgeColumn`, takže se tlačítko a badge nemůžou neshodnout na tom, jak vypadá
„Confirmed". Explicitní `->label()` pořád vyhraje.

Vlastní `->visible()` akce a odpověď stroje zůstávají oddělené otázky — musí
platit obě a ani jedna tiše nepřebíjí druhou. Kontrola bez záznamu (header akce,
view ptající se naprázdno) stroj vynechá.

### Co má UI nabídnout

```php
$orders->availableFrom($order, auth()->user());   // jen přesuny, které by prošly
```

## Související

- [Akce](index.md) — čím se přechod obvykle spouští
- [Foundation: Enumy](../foundation/enums.md) — odkud se bere popisek, barva a ikona stavu
- [Auditní log](../audit.md) — stopa, kterou přechod nechá
- [BadgeColumn](../../table/columns/badge.md) — sloupec, který stav vykreslí
