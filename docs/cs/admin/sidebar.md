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

