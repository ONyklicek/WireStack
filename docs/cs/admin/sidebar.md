---
order: 30
summary: Komponenta menu — samostatně i uvnitř layoutu — 64pixelová lišta a co se stane z každého řádku, když zmizí popisky.
---

# Sidebar

Menu je vlastní komponenta. Čte [`Workspace`](../panels/navigation.md), odkazuje
přes `ResolvesPageUrls` a aktivní položku pozná z právě vykreslované routy —
takže nepotřebuje nic deklarovat a nedrží žádný vlastní stav.

## Sidebar samotný

Aplikace s vlastním rámem použije menu bez layoutu:

```blade
<x-wire-admin::sidebar />
<x-wire-admin::sidebar :linked-only="true" />
```

`linkedOnly` rozhoduje, co se stane se zaregistrovanou položkou, kterou tahle
zóna neroutuje. Ve výchozím stavu zůstane v menu jako řádek bez odkazu —
viditelná, neklikatelná — což je poctivý obraz částečně zaroutované aplikace.
S `linkedOnly` se místo toho vynechá.

Obě formy přijmou explicitní `zone` a `activeKey`, když je hostitel už vyřešil:

```blade
<x-wire-admin::sidebar :zone="$zone" :active-key="$activeKey" />
```

## Sbalené menu

Táhlo v horní liště — nebo `⌘B` / `Ctrl+B` — zúží sloupec na 64pixelovou lištu.
Volba se drží v `localStorage` pod klíčem `wire-admin.rail` a **uplatní se dřív,
než se stránka vykreslí**, stejným blokujícím skriptem v hlavičce, jaký rozhoduje
o motivu. Není to detail: kdyby se četla z Alpine storu, první snímek každé
stránky by nesl *opačnou* odpověď — a protože sloupec nese `transition-[width]`,
sbalené menu se otevřelo na 288 pixelů, ukázalo všechny popisky a třetinu vteřiny
se zavíralo. Při každém načtení i každém `wire:navigate`.

Zkratka se neuplatní, když je kurzor v poli — v editoru bohatého textu je stejná
kombinace tučné písmo.

### Co se kam přesune

| Ve sloupci | V liště |
| --- | --- |
| Popisek vedle ikony | `aria-label` řádku a tooltip po najetí |
| Odznak jako pilulka za popiskem | Tečka v rohu ikony, ve stejné barvě |
| Potomci jako složený seznam pod rodičem | Popover vedle řádku |
| Nadpis skupiny | Oddělovač mezi skupinami |
| Aktivní položka podbarvením | Totéž podbarvení plus značka u hrany sloupce — podbarvení má stejný tvar jako hover a lišta je devět stejných čtverců |

**Tooltip a menu nejsou tentýž objekt** a právě ten rozdíl dělá sbalenou lištu
čitelnou. Řídí se to pravidlem, které pro svou boční navigaci uvádí SAP Fiori:
ve sbaleném stavu se po najetí ukáže tooltip s popiskem a podpoložky se zobrazí
v popoveru.

- Položka **bez potomků** dostane tooltip — jeden tmavý řádek, žádné odsazení
  menu, nic, co by šlo splést s něčím, co se dá otevřít.
- Položka **s potomky** dostane popover: název rodiče jako nadpis a pod ním jeho
  potomky.

Splést to je snadné a je to vidět. Dřívější verze tohohle shellu dávala každému
řádku tutéž kartu o velikosti menu, takže na otázku „co je tahle ikona?“
odpovídala u položky bez potomků panelem s jedním slovem.

Řádky potomků v popoveru vykresluje **stejná partial**, jaká je kreslí pod
rodičem v širokém menu — takže podoba řádku potomka má jedinou definici a jeho
URL, stav aktivity, barva odznaku ani zakázaný stav se pro panel nekódují znovu.
Druhá kopie se rozejde při první změně kterékoli strany.

**Najetí panel otevře, přejetí ne.** Panel čeká, až se ukazatel zhruba na pětinu
vteřiny zastaví, takže sáhnutí kolem menu k okraji stránky nic přes stránku
nepřehodí. Jakmile je jeden panel otevřený, posun po menu otevře další okamžitě —
čekání se platí za jednu návštěvu menu, ne za každý řádek. Klávesnice se k nim
dostane taky: tabulátorem se řádek otevře, `Enter` přesune fokus do popoveru
(je teleportovaný na konec dokumentu, takže samotný `Tab` by přeskočil na další
řádek) a `Escape` panel zavře a vrátí fokus zpět. Zavření `Escapem` drží, dokud
se ukazatel nebo fokus neposune jinam — jinak by vrácení fokusu na řádek, který
se otevírá na fokus, panel otevřelo znovu a `Escape` by působil jako klávesa,
která nic nedělá.

Nic z toho se nedeklaruje. Položka, která nese odznak i potomky, dostane
zacházení pro obojí:

```php
NavigationItem::make('Faktury')
    ->icon('outline:document-text')
    ->badge(fn (): int => Invoice::where('status', 'overdue')->count(), 'danger')
    ->children([
        NavigationItem::make('Všechny faktury')->url(route('invoices.index')),
        NavigationItem::make('Po splatnosti')->url(route('invoices.index', ['status' => 'overdue'])),
    ]);
```

Argument s barvou si tu zaslouží víc pozornosti než v širokém menu: v liště se
odznak scvrkne na tečku a odstín je jediné, co z něj ještě něco říká.

Další dvě věci, které shell řeší za vás a které kdysi fungovaly špatně:

- **Složená sbalitelná skupina** v liště své položky pořád ukazuje. Nadpis, který
  by ji rozbalil, je tam skrytý, takže složená skupina by jinak své položky
  z menu odstranila, ne jen schovala.
- Lišta je **desktopový** tvar. Pod `lg` je tentýž prvek zásuvka, takže lišta
  sbalená na notebooku menu na telefon nenásleduje.

## Filtrování menu

Jakmile má menu dvanáct řádků nebo víc, sedí nad ním pole. Psaní skryje řádky,
jejichž popisek hledaný text neobsahuje, a strukturu kolem těch, které ano,
zachová: odpovídající dítě zůstane pod rodičem s otevřeným submenu, sbalená
skupina se shodou se při filtrování otevře, skupina, ve které nic nezbylo,
zmizí, a když neodpovídá nic, řekne to, místo aby nechala prázdný sloupec. Počet
shod se ohlásí čtečce obrazovky.

**Není to druhé hledání.** ⌘K hledá záznamy, příkazy i menu a někam vede; filtr
zužuje seznam, na který už se díváte, a nechává ho vypadat jako on sám.

- `/` odkudkoli na stránce do něj dá kurzor, pokud už kurzor není v jiném poli.
- Escape ho vymaže a dá fokus na první řádek.
- Přechod na jinou stránku ho vymaže — filtr, který by vás následoval, je menu
  s polovinou řádků pryč a ničím na obrazovce, co by řeklo proč.
- V liště je skrytý: 64 pixelů nemá na pole místo a odpovědí je tam ⌘K.

`config('wire-admin.navigation.filter')` je `auto` — ukáže se od
`filter_threshold` řádků výš, děti se počítají —, `always` nebo `never`.

## Připnuté a nedávné

Každý řádek nejvyšší úrovně nese špendlík, vidět při najetí myší nebo fokusu —
záložka pro připnutí, přeškrtnutá záložka pro odepnutí. Zaujme místo badge na
konci řádku, badge mezitím ustoupí, takže řádek v klidu neukazuje nic navíc: co
je připnuté, říká sekce nahoře. Položka v té sekci stejně nabídne odepnutí a
položka v *Nedávných* připnutí. Připnutí dá kopii položky do sekce *Připnuté* nad
skupinami — řádek sám zůstane ve své skupině — a sekce vedle, *Nedávné*,
vypisuje naposledy otevřené stránky, od nejnovější, nejvýš pět.

Podřádek ho nese taky, pokud má klíč: položka umístěná pod jinou přes `parent()`
je registrovaná a klíč už má, ručně psaný podřádek si ho pojmenuje přes `key()`.
Zvolte klíč, pod kterým není registrovaný žádný resource — stránku resource
jako `settings.server` — protože klíč shodný s registrovaným rozsvítí podřádek na
každé stránce toho resource:

```php
NavigationItem::make('System')->children([
    NavigationItem::make('Server')->url(SettingsResource::url('server'))->key('settings.server'),
]);
```

Podřádek bez klíče nemá podle čeho být uchován a špendlík nedostane.

**Jen tam, kde je něco uchová.** Připnuté a nedávné se ukládají přes stejný
driver preferencí jako sloupce tabulky, jeden pytlík na člověka a
[zónu](../panels/routing.md#zony). Výchozí driver je `null`, který neuchová nic —
a špendlík, který potichu nic nedělá, je horší než žádný, takže s driverem `null`
se nevykreslí ani špendlíky, ani sekce. Zvolte ho v `config/wire-core.php`:

```php
'preferences' => [
    'default' => 'database',   // nebo 'session' — připnuté pak vydrží jen do konce session
    'guest' => 'session',
],
```

Zbytek je rozhodnutý za vás:

- **Uložený klíč se nikdy nekreslí přímo z úložiště.** Ukazuje se průnik
  uložených klíčů s menu, které člověk právě vidí, takže připnutý resource, který
  byl odinstalovaný, je mu skrytý nebo není routovaný v této zóně, prostě chybí.
- **Nedávné nikdy nevypíše stránku, na které jste**, ani nic, co už je připnuté.
- **Nedávné se zapisuje při mountu stránky** (`page.mounting`), nikdy při kreslení
  menu: požadavek, který vykresluje sidebar, nezapisuje. Stránka, která ten hook
  nedispatchuje — vlastní stránka, která neskládá žádnou stránku resource —, se
  nezapamatuje, a to je v seznamu vidět, ne potichu špatně.
- Sekce je jediná část menu, která je Livewire komponenta, takže připnutí se
  projeví hned, bez přenačtení; řádky se o svém stavu dozvědí z události, kterou
  sekce odpoví.

## Klávesnice

Shell rozhoduje, co dělá fokus, takže uživatel klávesnice nikdy nezůstane za
vrstvou, kterou nevidí:

- **Šuplík na telefonu drží Tab uvnitř**, dokud je otevřený, začne na prvním
  řádku menu a fokus vrátí tlačítku, které ho otevřelo, ať se zavře jakkoli —
  Escapem, ztmavenou vrstvou, zavíracím tlačítkem nebo odkazem.
- **Odkaz pro přeskočení vede do obsahu.** `<main>` má `tabindex="-1"`, takže
  odkaz přesune fokus, a ne jen odroluje.

A uvnitř menu navíc k Tabu — který dál prochází každý řádek, protože menu je
seznam odkazů, ne aplikační menu, takže žádný řádek z pořadí Tabu nevypadne
a čtečka obrazovky jim dál říká odkazy:

| Klávesa | Na řádku |
| --- | --- |
| ↓ / ↑ | další / předchozí vykreslený řádek, přes skupiny i připnuté řádky |
| Home / End | první / poslední řádek |
| → / ← | otevře / zavře submenu řádku — v liště jeho popover; ← na dítěti vede zpět na rodiče |
| písmeno | další řádek, jehož popisek začíná tím, co bylo napsáno do půl sekundy |
| ↓ ve filtru | první řádek, který zbyl |

Řádky, které fokus vzít nemůžou — neroutovaná položka se kreslí bez odkazu —, se
přeskočí, místo aby na nich fokus přistál.

## Zóny

[Zóna](../panels/routing.md#zony) je name prefix route skupiny a shell k jejímu
respektování nepotřebuje nic deklarovat: stejné markup odkazuje do `admin` na
admin stránce a do `business` na business stránce, protože zóna se bere z routy,
která se právě vykresluje. Paleta dělá totéž — a proto její spouštěč sedí v rámu,
ne na stránce.

## Související

- [Navigace](../panels/navigation.md) — položky, skupiny a odznaky, které tohle kreslí
- [Routování](../panels/routing.md) — zóny a odkud se bere URL položky
- [Layout](layout.md) — rám, ve kterém sidebar normálně sedí

