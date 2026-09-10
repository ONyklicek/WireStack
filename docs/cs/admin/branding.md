---
order: 40
summary: Dvě podoby loga, třístavový přepínač motivu rozhodnutý před prvním vykreslením a publikování views, když konfigurace nestačí.
---

# Branding a motiv

Dvě rozhodnutí padnou dřív, než se stránka vykreslí — které logo a který motiv — a
obě ze stejného důvodu v hlavičce: přečtěte kterékoli z nich o snímek později a
uživatel se dívá, jak se to mění.

## Logo v liště

Značka má dvě podoby a obě jsou v dokumentu; kterou vidíte, rozhoduje `data-rail`
na `<html>` — tedy dřív, než se stránka vykreslí, stejně jako u všeho ostatního.
`logo` se kreslí v širokém menu a `mark` v liště, protože wordmark zmenšený do 64
pixelů není malý, ale nečitelný; když zadáte jen `logo`, lišta spadne zpět na
iniciálu aplikace v zaobleném čtverci.

```php
// config/wire-admin.php
'brand' => [
    'name' => 'Acme',
    'logo' => 'images/logo.svg',        // široké menu
    'mark' => 'images/mark.svg',        // lišta — spadne zpět na iniciálu
    'logo_dark' => 'images/logo-dark.svg',
],
```

Dvě věci, které tu dřív byly špatně, a obě šlo vidět jedině na obrazovce. Mezi
podobami vybíral `x-show`, který běží *až po* prvním vykreslení — sbalené menu
tedy nakreslilo wordmark oříznutý na 31 pixelů vlastním přetečením hlavičky a
o snímek později ho vyměnilo za čtverec. Při každém načtení i každém
`wire:navigate`. A značka seděla natvrdo u levého okraje sloupce, 16 pixelů mimo
osu, na které sedí každá ikona pod ní — protože vycentrovat 32pixelový odkaz sám
v sobě ho nechá tam, kam ho postavila hlavička.

## Přepínač motivu

Tři stavy, ne přepínač: **den, systém, noc**. Ten třetí je ten, který nese váhu —
dvoustavový spínač si vynutí volbu ve chvíli, kdy se ho někdo dotkne, a pak si ji
nechá napořád, takže notebook, který si večer sám ztmaví, přestane platit hned
při prvním stisknutí. K `systému` se musí dát vrátit.

Vokabulář je `NyonCode\WireCore\Foundation\Enums\Theme`, takže druhá plocha —
stránka nastavení, jiný shell — vykreslí tytéž tři volby se stejnými popisky a
ikonami, aniž by znovu rozhodovala, jak se jmenují.

Který motiv platí, se rozhoduje **dřív, než se stránka vykreslí**, malým skriptem
v hlavičce — číst to později je přesně ten záblesk, podle kterého se každá
implementace tmavého režimu posuzuje. `systém` navíc poslouchá dál, dokud je
stránka otevřená, takže se motiv mění s operačním systémem místo aby zůstal
takový, jaký byl při načtení.

## Změna vzhledu

```bash
php artisan vendor:publish --tag=wire-admin::views
```

Publikované views jsou obyčejný Blade. Je to záměrně hrubší než konfigurační
API — a právě ta hrubost drží shell od toho, aby se z něj nabalováním stal panel
framework.

## Související

- [Sidebar](sidebar.md) — kde se obě podoby loga kreslí
- [Motivy a přizpůsobení](../start/theming.md) — barevný a ikonový slovník pod tím
- [Konfigurace](../start/configuration.md) — celý `wire-admin.brand`
