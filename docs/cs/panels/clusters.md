---
order: 45
summary: Jedna sekce administrace jako jedna třída — jediná položka v menu, jeden prefix URL a cesta mezi jejími obrazovkami na každé z nich.
---

# Clustery

Nastavení je deset obrazovek, které k sobě patří. V menu mají být jednou
položkou, v adresním řádku jedním prefixem, a kdo je na jedné z nich, má mít
zbylých devět na jedno kliknutí. Cluster je taková sekce, deklarovaná jako jedna
třída, kterou jmenují její členové.

```text
menu                 URL                             na stránce
─────────            ───────────────────────────     ─────────────────────────────
Settings      →      /admin/settings                 přesměruje na prvního člena
                     /admin/settings/currencies      Currencies │ Taxes │ Mail
                     /admin/settings/taxes           stejní členové, označené Taxes
```

## Jak to funguje

Cluster je [`Page`](pages.md#vlastni-stranka) — `Clusters\Cluster` ji
rozšiřuje —, takže se registruje, routuje a zobrazuje v menu přesně jako vlastní
stránka, ze stejných statických vlastností. Všechno ostatní plyne z jednoho řádku
na každém členovi: člen jmenuje svůj cluster.

Členství mění pět věcí a každou vlastní vrstva, která tu práci už dělala:

| Povrch | Co se změní |
| --- | --- |
| **Menu** | Členové opustí seskupené menu; zastoupí je položka clusteru, která svítí na stránce každého člena. `Workspace::items()` — plochý seznam, který prohledává paleta příkazů — si je nechá. |
| **URL** | Člen se routuje na `{prefix clusteru}/{svůj prefix}`. **Jméno** jeho routy se nemění, takže každý odkaz stavěný přes klíč — `urlFor()`, `X::url()`, menu, výsledky hledání — přesun následuje. |
| **Middleware** | `$permission` clusteru (jeho `can:`) se přidá ke každé routě člena. Do sekce, kam někdo nesmí, se nedostane ani přes záložku v prohlížeči. |
| **Stránka** | Každá stránka člena vykreslí členy clusteru — jako sloupec vedle obsahu, nebo jako záložky nad ním. |
| **Drobečky** | Cluster je první drobeček: *Settings › Currencies*. |

**Adresa clusteru přesměrovává.** Nic nevykreslí: pošle uživatele na prvního
člena, kterého smí otevřít, v pořadí, v jakém se členové kreslí, a přeskočí toho,
jehož routa by ho odmítla. Když odmítnou všichni, je to 403; cluster, který nikdo
nejmenuje, je 404.

**Členové se kreslí z vlastních položek menu.** Každý člen už deklaruje
`navigation()` — popisek, ikonu, pořadí, badge, viditelnost — a cluster kreslí
právě tyhle, v pořadí `sort()`, bez skrytých. Nic nového se pro to nedeklaruje
a člen, který žádnou položku nedeklaruje, je routovaný pod prefixem, ale
v navigaci chybí, stejně jako by chyběl v menu. Aktuální člen se pozná podle
**registrovaného klíče**, takže resource zůstane označený i na své editační
stránce, nejen na seznamu. Cluster s jedním viditelným členem navigaci nekreslí:
jedna záložka by byla nadpis stránky napsaný podruhé.

**Pasti.** Člen, který jmenuje třídu, jež není cluster, cluster, který není
registrovaný, nebo cluster v jiném clusteru, se odmítne výjimkou
`ClusterException` při registraci rout — nikdy to není stránka potichu
routovaná v kořeni, mimo oprávnění sekce. A vnořený resource sedí tam, kde jeho
rodič: jeho stránky se routují pod záznamem rodiče, a tedy i pod rodičovým
clusterem.

## Deklarace clusteru

```php
use NyonCode\WirePanels\Clusters\Cluster;
use NyonCode\WirePanels\Enums\SubNavigationPosition;

final class Settings extends Cluster
{
    protected static ?string $navigationIcon = 'outline:cog-6-tooth';   // [tl! focus:start]
    protected static ?string $navigationGroup = 'admin';
    protected static int $navigationSort = 90;
    protected static ?string $permission = 'settings.view';
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Start;   // [tl! focus:end]
}
```

a zaregistrujte ho stejně jako stránku — `config('wire-panels.pages')` nebo
objevovanou složkou:

```php
// config/wire-panels.php
'pages' => [App\Clusters\Settings::class],
```

`php artisan make:wire-cluster Settings` zapíše třídu do `app/Clusters/` a řekne,
který z obou kroků zbývá.

## Jak do něj dát obrazovku

Stránka jmenuje svůj cluster statickou vlastností:

```php
use NyonCode\WirePanels\Pages\Page;

final class Taxes extends Page
{
    protected static string $view = 'livewire.settings.taxes';

    protected static ?string $cluster = Settings::class;   // [tl! focus]

    protected static int $navigationSort = 20;
}
```

Resource implementuje `BelongsToCluster`, což je jedna metoda:

```php
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Routing\Contracts\BelongsToCluster;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;

final class CurrencyResource implements BelongsToCluster, DescribesResource, ProvidesNavigation, ProvidesPages
{
    use DescribesRecords;

    public static function cluster(): ?string   // [tl! focus:start]
    {
        return Settings::class;
    }                                            // [tl! focus:end]

    public static function modelClass(): ?string
    {
        return Currency::class;
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()->icon('outline:banknotes')->sort(10);
    }

    public static function pages(): array
    {
        return ['index' => ListCurrencies::class, 'edit' => EditCurrency::class];
    }
}
```

`php artisan make:wire-page Taxes --cluster=Settings` zapíše stránku člena, která
tu statickou vlastnost už má.

## Kde se členové kreslí

`$subNavigationPosition` bere `SubNavigationPosition`:

| Případ | Od `lg` výš | Pod `lg` |
| --- | --- | --- |
| `Start` — výchozí | sloupec před obsahem | záložky nad nadpisem |
| `End` | sloupec za obsahem | záložky nad nadpisem |
| `Top` | záložky nad nadpisem | záložky nad nadpisem |

Sloupec na telefonu je sloupec, ke kterému nikdo nesroluje, takže pod `lg` jsou
záložky v každé poloze. Sloupec rozkládá pár řádků obyčejného CSS navázaných na
atribut stránky `data-cluster-frame`, ne utility třídy, takže nezávisí na tom,
co zrovna obsahuje Tailwind build aplikace.

## Odkazy do clusteru

Na odkazování se nic nemění. Člen si nechá svůj klíč i jméno routy, takže:

```php
CurrencyResource::url();              // /admin/settings/currencies
CurrencyResource::url('edit', $eur);  // /admin/settings/currencies/3/edit
Settings::url();                      // /admin/settings — pošle vás na prvního člena
```

Ručně napsaná cesta člena do clusteru nenásleduje; odkaz postavený přes klíč
nebo přes třídu ano.

## Co cluster deklaruje

| Statická vlastnost | Výchozí | Účel |
| --- | --- | --- |
| `$slug` | jméno třídy v kebab-case | Klíč a prefix URL každého člena |
| `$navigationLabel` | polidštěné jméno třídy | Položka menu, drobeček a přístupné jméno navigace |
| `$navigationIcon` | `null` | Ikona položky menu |
| `$navigationGroup` | `null` | Skupina, ve které sedí položka clusteru — vlastní `group()` členů už neplatí |
| `$navigationSort` | `100` | Pořadí v té skupině |
| `$navigationParent` | `null` | Položka, pod kterou sedí položka clusteru |
| `$permission` | `null` | `can:` routy clusteru, přidané k routě každého člena |
| `$shouldRegisterNavigation` | `true` | `false` nechá cluster routovaný a mimo menu |
| `$subNavigationPosition` | `SubNavigationPosition::Start` | Kde se členové kreslí na stránce každého člena |

A na členovi:

| Člen | Deklarace |
| --- | --- |
| `Pages\Page` | `protected static ?string $cluster = Settings::class;` |
| Resource | `implements BelongsToCluster` s `public static function cluster(): ?string` |
| Vnořený resource | nic — je tam, kde jeho rodič |

`ClusterNavigation` je jediný vlastník, kterého se ptá router, menu i stránka:
`clusterOf($class)`, `members($cluster)`, `items($cluster, $zone)`,
`landing($cluster, $zone, $user)` a `for($member, $zone)`.

## Související

- [Navigace](navigation.md) — menu, ve kterém sedí položka clusteru, a `parent()` pro větev, která zůstává v sidebaru
- [Stránky](pages.md) — `Page`, kterou cluster je, a vlastní záložky záznamu
- [Routování](routing.md) — prefixy, jména rout a `url()`
- [Příkazová řádka](cli.md) — `make:wire-cluster` a `make:wire-page --cluster`
