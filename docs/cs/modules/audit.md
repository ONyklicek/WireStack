---
order: 70
summary: Obrazovka pro auditní stopu, kterou wire-core už zaznamenává — událost, člověk, záznam a před/po u každého pole, které se pohnulo.
---

# Modul auditu

Motor je v balíčku roky: `HasAuditable` vystřelí události, `AuditLogger` je
zapíše a prořezávací příkaz drží tabulku poctivou. Co žádný balíček nepřidal,
byla cesta si to celé přečíst bez SQL.

```bash
composer require nyoncode/wire-module-audit
php artisan wire-module-audit:install
```

## Jak to funguje

**Jen ke čtení, a to je záměr.** Auditní záznam, který jde upravit, není auditní
záznam — resource proto vyhlašuje výpis a detail a nic dalšího: žádný formulář,
žádnou stránku pro vytvoření a `ProvidesResourceForm` schválně chybí. Není tu ani
tlačítko smazat: retenci řeší `wire-core.audit.retention_days` a příkaz
`wire-core:audit-prune`, což je naplánované rozhodnutí, a ne tlačítko, ke kterému
někoho ukecají.

**Nad vlastním modelem `wire-core`.** Modul nevlastní žádnou tabulku: `AuditEntry`,
jeho migrace i výpočet rozdílu patří jádru a tohle na ně jen ukazuje. Jádro tu
migraci publikuje na vyžádání, takže čerstvá instalace může mít obrazovku dřív než
tabulku — instalátor to řekne, místo aby po vás nechal prázdný log vysvětlovat.

```bash
php artisan vendor:publish --tag=wire-core::migrations
php artisan migrate
```

**Každý sloupec má pojmenovaného vlastníka.** Název a barva události, jméno
člověka, název záznamu, pole, která se pohnula — každou tu odpověď potřebuje
sloupec, filtr *i* detail, a obrazovka, která by si ji odvodila třikrát, by ji
jednou odvodila jinak.

**Zaznamenávání zůstává vypínačem jádra.** S vypnutým `wire-core.audit.enabled`
není co ukazovat, a instalátor to taky řekne.

## Kdo to udělal

Záznam ukládá klíč toho, kdo byl přihlášený; obrazovka ukazuje jméno — přes
relaci, kterou definuje jádro, a načtené jednou pro celou stránku, ne jednou na
řádek.

Tři odpovědi, protože jde o tři různé skutečnosti:

| Uloženo | Zobrazeno | Proč |
| --- | --- | --- |
| žádné `user_id` | *Systém* | Seedery, fronty a konzolové příkazy auditují změny bez přihlášení — `AuditLogger` je zapisuje záměrně |
| klíč bez uživatele | *Neznámý uživatel #12* | Účet je pryč, takže klíč je jediné, co po něm zbylo — a zůstává vidět |
| klíč s uživatelem | jeho jméno | První z nastavených atributů, který doopravdy má |

`name` je zvyklost, ne kontrakt, takže co se čte jako jméno člověka, říkáte vy:

```php
// config/wire-module-audit.php
'actor' => [
    'attributes' => ['full_name', 'email'],
],
```

Samotný model uživatele je nastavení jádra, protože relaci definuje jádro:

```php
// config/wire-core.php
'audit' => [
    'user_model' => App\Models\Account::class,
],
```

## Co se změnilo

Výpis pojmenuje pole, která se pohnula; detail postaví „před“ vedle „po“ jako
**jednu tabulku**, řádek na pole, nad rozdílem, který jádro už spočítalo
(`AuditEntry::getChangeDiff()`).

Jedna tabulka, ne karta na každé pole — a přesně tak to kreslil nejdřív: karta
nese záhlaví *Pole / Stará / Nová* jednou na řádek, takže úprava, která sáhla na
osm sloupců, je zopakovala čtyřiadvacetkrát. To je práce, kterou rozdíl existuje
proto, aby už byla hotová. Je to [`ChangesEntry`](../core/infolists/entries.md#changesentry)
z jádra, tatáž komponenta, kterou kreslí slide-over se stopou nad záznamem — díky
tomu se změna čte v obou místech stejně.

Jak se hodnota čte, má taky jednoho vlastníka,
`NyonCode\WireCore\Foundation\ValueObjects\ChangeSet`: uložené pole — JSON
sloupec, seznam ID hromadné akce — se vykreslí jako text, ne jako slovo `Array`;
boolean je `true` nebo `false`, ne `1` a nic; a hodnota, která tam nebyla, se čte
jako *(prázdné)*, ne jako prázdno, které vypadá nezměněně.

Samotná stránka jsou tři sekce v pořadí, v jakém přicházejí otázky: **co se
stalo** (událost, okamžik, člověk, záznam), **změny** a **požadavek**, ve kterém
změna přišla — IP a prohlížeč, které `AuditLogger` zaznamenal, plus cokoli,
s čím událost přišla. Poslední začíná sbalená: je to ta půlka, kvůli které
stránku nikdo neotevírá, a zároveň ta, která bývá nejdelší.

## Z logu k záznamu

Řádek říká, že se něco stalo faktuře číslo sedm, a další věc, kterou kdokoli
chce, je faktura číslo sedm. Tam, kde auditovaný model má resource a ten je
zaroutovaný, nabídne řádek odkaz — vyřešený přes registr, v zóně, ve které byl
výpis otevřený.

Kde se vyřešit nedá, odkaz není — místo takového, který nikam nevede. Pokrývá to
víc případů, než to zní: model bez resource, aplikace, která routuje log a nic
jiného, a třídu, kterou log přežil.

Aplikace, které si do databáze názvy tříd nepouštějí, se čtou zpátky tou samou
mapou, která je zapsala, takže alias z `morphMap` je popsaný a prolinkovaný jako
každý jiný typ.

## Kdo tam smí

Bez nastavení je log otevřený jako zbytek panelu. Pojmenujte oprávnění a hlídá
routy i odkazy, které k nim vedou, z jediného řádku:

```php
// config/wire-module-audit.php
'permission' => 'audit.view',
```

Kontroluje se přes `Gate::allows()`, do kterého se oba permission balíčky samy
registrují — takže zástupný znak (`audit.*`) i obcházení pro superadmina fungují,
aniž by o nich tenhle modul věděl. Audit log je obrazovka, u které se pojmenovat
oprávnění vyplatí nejvíc.

## Co dostanete

| Obrazovka | Poznámky |
| --- | --- |
| Audit log | Událost, záznam, kdo, změněná pole, kdy — filtr podle události, typu záznamu, člověka a rozsahu dat; od nejnovějšího |
| Jeden záznam | Před a po u každého pole, které se pohnulo, a požadavek, ve kterém to přišlo |

## Nastavení

| Klíč | Výchozí | Popis |
|-----|---------|-------------|
| `model` | `AuditEntry::class` | Model záznamu; musí dědit z modelu jádra, a ten, který nedědí, je odmítnut, místo aby se ukázal jako prázdný log |
| `actor.attributes` | `['name', 'email']` | Který atribut uživatele se čte jako jeho jméno; vyhrává první, který má |
| `permission` | `null` | Oprávnění pro čtení logu — `null` ho nechává otevřený |
| `navigation.group` | `system` | Skupina v menu |
| `navigation.label` | `null` | Nadpis skupiny; `null` použije vlastní nadpis modulu |
| `navigation.icon` | `outline:clipboard-document-list` | Ikona v menu |
| `navigation.sort` | `95` | Pořadí skupiny v menu |

Tenhle modul nedodává vlastní pohledy — jeho obrazovky jsou framework tabulka
a infolist nad stopou, kterou zapisuje `wire-core`, takže se přestylují tam, kde
každá jiná obrazovka ([Vzhled](../start/theming.md)). Co dodává, jsou texty:
`php artisan vendor:publish --tag=wire-module-audit::translations`; soubor se
slučuje přes ten balíčkový klíč po klíči, takže přepis obsahuje jen řádky, které
jste změnili.

## Související

- [Audit log](../core/audit.md) — motor a co zaznamenává
- [Moduly](../panels/modules.md) — jak balíček dodá takovouhle oblast
