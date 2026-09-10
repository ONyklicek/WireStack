---
order: 10
summary: Šest celých oblastí dodaných jako composer balíčky — co která nainstaluje, co potřebuje od aplikace a jak ji zase odebrat.
---

# Hotové moduly

[Modul](../panels/modules.md) je manifest jedné byznysové oblasti: resource,
dashboardy a nadpis v menu, ze kterých se skládá. Těchhle šest ten manifest veze
**jako composer balíček**, takže oblast dorazí přes `composer require` místo
třídy, kterou je potřeba napsat, a řádku v configu, na který je potřeba si
vzpomenout.

```bash
composer require nyoncode/wire-module-users
```

U většiny z nich je to celá instalace. Provider balíčku zaregistruje modul jako
plugin, modul naplní registry, které už existovaly, a menu, router i vyhledávací
palette si ho odtud vyzvednou — stejnou cestou, jakou jde modul samotné aplikace.

## Jak to funguje

Nic tady není druhý druh věci. Dodaný modul je [plugin](../core/plugins/index.md), který
jmenuje [resource](../panels/resources.md) a dashboardy, a všechno pod ním to čte
přes [katalog](../panels/navigation.md#catalog-api):

1. `composer require` položí balíček na disk; Laravel objeví jeho provider.
2. Provider zaregistruje modul do `PluginManager` — bez zásahu do configu, což je
   rozdíl mezi dodaným modulem a modulem, který deklaruje aplikace.
3. `WireCoreServiceProvider` pak rozprostře, co modul deklaruje, do registru
   resourců, registru dashboardů a navigačních skupin.
4. `Route::wireResources()`, sidebar a palette čtou tyhle registry a novou oblast
   najdou, aniž by jim to někdo řekl.

**Modul, který aplikace nemůže odebrat, je přesně to, kvůli čemu lidi přestanou
moduly instalovat**, takže každý z nich jde odebrat dvěma způsoby: `composer
remove` vezme celou oblast a config každého modulu umí zahodit nebo nahradit to,
čím přispěl, i když balíček zůstane. Tam, kde modul veze obrazovku nad něčím, co
aplikace už vlastní — nastavovací záložka, uživatelský resource — vyhrává vlastní
deklarace aplikace nad tou dodanou.

## Co která nainstaluje

| Modul | Balíček | Co přijde | Co potřebuje |
| --- | --- | --- | --- |
| [Uživatelé](users.md) | `wire-module-users` | Uživatelský resource, jeho stránky, profilová obrazovka a správa rolí tam, kde aplikace role má | Uživatelský model; `nyoncode/laravel-permission-extended` pro rolové povrchy |
| [Přihlašování](auth.md) | `wire-module-auth` | Přihlášení, reset hesla, ověření e-mailu, dvoufaktorová výzva a **Odhlásit** v uživatelském menu | `laravel/fortify` — vlastní bezpečnost, modul vlastní obrazovky |
| [Nastavení](settings.md) | `wire-module-settings` | Typovaná tabulka nastavení, její cache a obrazovka nad ní | Databázové připojení |
| [Notifikace](notifications.md) | `wire-module-notifications` | Historie za zvonečkem, jako tabulka | Laravelí tabulka `notifications` |
| [Audit](audit.md) | `wire-module-audit` | Obrazovka pro stopu, kterou `wire-core` už zapisuje | `HasAuditable` na modelech, které chcete sledovat |
| [Média](media.md) | `wire-module-media` | Knihovna médií — uploady, složky, náhledy a picker do formulářů | Filesystem disk |

[Týmy a dvoufaktor](teams-and-two-factor.md) není sedmý balíček: je to návod, jak
zapnout dva povrchy, které users modul drží za `auto` přepínačem — ten hledá věc
samotnou (Fortify pro dvoufaktor, permission balíček pro týmy) a zůstává vypnutý,
když chybí.

## Instalace všech najednou

`wire:install` nabídne každý modul, který vidí, a pojmenuje ty, které nevidí —
nabídnout modul, který není nainstalovaný, je totiž smyslem věci: seznam řádků
`composer require`, které si můžete zkopírovat, je užitečnější než ticho:

```bash
composer require nyoncode/wire-suite
php artisan wire:install
```

Composer za vás nikdy nespustí. Co instalátor dělá a co odmítá dělat, popisuje
[Instalace Wire](../start/installation.md).

## Vlastní modul

Těch šest jsou obyčejné moduly s providerem kolem sebe a
[`packages/module-users`](users.md) je referenční implementace. Co modul deklaruje,
jak závisí na jiném a jak ho balíček veze, pokrývají [Moduly](../panels/modules.md).

## Související

- [Moduly](../panels/modules.md) — samotný manifest a jak ho deklarovat v aplikaci
- [Resources](../panels/resources.md) — z čeho jsou oblasti modulu složené
- [Pluginy](../core/plugins/index.md) — registrační cesta, kterou modul jde
- [Instalace Wire](../start/installation.md) — interaktivní instalátor, který je nabízí
