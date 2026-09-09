---
order: 40
summary: Přihlášení, obnova hesla, ověření e-mailu a dvoufázová výzva v designu panelu — obrazovky, o které si Laravel Fortify říká aplikaci.
---

# Modul přihlášení

Obrazovky na cestě dovnitř. Nainstalujete ho a aplikace má přihlašovací stránku,
která vypadá jako panel za ní, cestu k obnově hesla, potvrzení adresy,
dvoufázovou výzvu a položku **Odhlásit se** v uživatelském menu.

```bash
composer require nyoncode/wire-module-auth
php artisan wire-module-auth:install
```

Neobsahuje žádnou autentizaci. Tu vlastní Laravel Fortify, a právě tohle
rozdělení je celý návrh balíčku.

## Jak to funguje

**Fortify vlastní bezpečnost, tenhle balíček značkování.** Ověření přihlašovacích
údajů, omezení počtu pokusů podle adresy a IP, regenerace session, která zavírá
fixaci, tokeny pro obnovu hesla a jejich expirace, podepsané ověřovací odkazy,
okno TOTP a záložní kódy jsou bezpečnostní plocha s udržovaným vlastníkem. Panel,
který by je napsal znovu, by tu plochu vlastnil a nezískal tím jedinou funkci.

Fortify je *headless*: registruje routy a akce a o pohledy si říká aplikaci sedmi
callbacky — `Fortify::loginView()` a jeho šest sourozenců. Než vznikl tenhle
balíček, odpovídala si na ně aplikace sama — nebo, mnohem častěji, neodpověděla
vůbec a nainstalovala celý panel bez možnosti se do něj přihlásit. Ta mezera je
přesně sedm pohledů široká a tenhle balíček je těch sedm pohledů.

**Odpovídá se na všech sedm, bezpodmínečně.** Které obrazovky *existují*, je
otázka pro Fortify, ne pro tenhle balíček: registrace, obnova hesla, ověření
e-mailu a dvoufázové ověření jsou položky ve `fortify.features` a na pohled pro
vypnutou funkci se nikdy nesměruje. Podmínit registrace tady by znamenalo druhou
kopii toho seznamu, která s ním může nesouhlasit.

**Providery aplikace bootují jako poslední, takže vyhrává váš.** Pojmenování
vlastního pohledu v provideru aplikace nahradí jeden z těchhle, aniž by vypnulo
zbytek — balíčkové providery bootují dřív než aplikační, takže poslední
`Fortify::loginView()` patří aplikaci.

**Odkazy se kreslí z týchž přepínačů, které vytvářejí routy.** Odkaz „Zapomněli
jste heslo?" se objeví jen tam, kde je zapnuté `Features::resetPasswords()`,
a odkaz na registraci jen tam, kde `Features::registration()`. Odkaz na routu,
kterou Fortify nikdy nezaregistroval, je 404, o které se aplikace dozví od
uživatele.

**Rám se pojmenovává, nedodává.** Obrazovky se vykreslují uvnitř layoutu, který
tenhle balíček nevlastní. Dva rámy by byly stejných čtyřicet řádků dvakrát, které
se rozejdou při první změně jednoho z nich — takže `auto` se ptá na tři věci
v tomhle pořadí:

1. **Váš vlastní layout** v `resources/views/components/layouts/auth.blade.php`,
   který zapíše instalátor. Je první proto, že rám shellu bere váš stylesheet ze
   slotu `head` — balíček nemůže znát jména vašich Vite entry — takže vykreslení
   rovnou do rámu shellu dá přihlašovací stránku se značkováním frameworku
   a **bez jediného vašeho stylu**, a bez jakékoli chybové hlášky.
2. **Rám shellu**, `wire-admin::auth-layout`: hlavička, rozhodnutí o motivu ještě
   před prvním vykreslením a karta, bez jediné položky navigace. Tohle dostane
   aplikace, která má shell a nikdy nespustila instalátor — chybí styly, zbytek
   je v pořádku.
3. **Nic**, což vyhodí `AuthFrameException` místo vykreslení prázdného jména
   komponenty.

**Odhlášení je řádek v chromu, ne řádek ve vašem layoutu.** Přispívá se jím do
`PageChrome::USER_MENU`, oblasti, kterou shell vykresluje uvnitř uživatelského
menu, a posílá se na vlastní odhlašovací routu Fortify. Modul uživatelů dává do
téže oblasti odkaz na profil, seřazený nad ním. Než ta oblast vznikla, bylo obojí
značkování, které si každá aplikace psala ručně do slotu v layoutu.

**Přihlašovací obrazovka před nehlídaným panelem je dekorace.** Instalátor řekne,
kterou z těch dvou věcí aplikace má: čte `wire-panels.routes.middleware`
a upozorní, když v něm `auth` chybí. Routy psané ručně dostanou tutéž
připomínku — nic tady nedokáže přečíst, v jaké skupině jsou.

## Konfigurace

```php
// config/wire-module-auth.php
'layout' => 'auto',    // 'auto' použije rám shellu, pokud je shell nainstalovaný [tl! focus]

'views' => true,       // odpovídat na sedm view callbacků Fortify

'user_menu' => true,   // dát „Odhlásit se" do uživatelského menu shellu
```

`auto` najde nejdřív layout, který zapsal instalátor, potom rám shellu. Bez
obojího vyhodí vykreslení obrazovky `AuthFrameException` — větu, která jmenuje
tenhle klíč, místo Blade hlášky „unable to locate component" o vrstvu níž.
Aplikace s vlastním rámem si ho pojmenuje, a to jako **komponentu**, ne jako
pohled:

```php
'layout' => 'layouts.guest',   // resources/views/components/layouts/guest.blade.php
```

To, co zapíše instalátor, je obyčejný layout — váš, a k editaci:

```blade
{{-- resources/views/components/layouts/auth.blade.php --}}
@props(['title' => null])

<x-wire-admin::auth-layout :title="$title ?? config('app.name')">
    <x-slot:head>
        @vite(['resources/css/app.css', 'resources/js/app.js'])   {{-- [tl! focus] --}}
    </x-slot:head>

    <x-slot:brand>{{ config('app.name') }}</x-slot:brand>

    {{ $slot }}
</x-wire-admin::auth-layout>
```

## Co dostanete

| Obrazovka | Routa | Je tam, když |
| --- | --- | --- |
| Přihlášení | `login` | vždy |
| Vytvoření účtu | `register` | `Features::registration()` |
| Žádost o odkaz na obnovu | `password.request` | `Features::resetPasswords()` |
| Nastavení nového hesla | `password.reset` | `Features::resetPasswords()` |
| Potvrzení adresy | `verification.notice` | `Features::emailVerification()` |
| Potvrzení hesla | `password.confirm` | vždy |
| Dvoufázová výzva | `two-factor.login` | `Features::twoFactorAuthentication()` |

A k tomu **Odhlásit se** v uživatelském menu.

## Zapnutí jednotlivých funkcí

Které obrazovky existují, rozhoduje konfigurace samotného Fortify. Tohle je celé,
co aplikace napíše, aby měla panel se zavřenou registrací, otevřenou obnovou
hesla a druhým faktorem:

```php
// config/fortify.php
use Laravel\Fortify\Features;

'features' => [
    // Žádné samoobslužné účty: administrační panel bere uživatele    [tl! focus:start]
    // z modulu uživatelů, ne z veřejného formuláře.
    // Features::registration(),

    Features::resetPasswords(),
    Features::emailVerification(),
    Features::updatePasswords(),
    Features::twoFactorAuthentication([
        // Fortify zapíše tajemství ve chvíli, kdy se vygeneruje QR kód,
        // takže bez tohohle se uživatel, který panel otevřel a odešel,
        // počítá jako chráněný. Nechte to zapnuté.
        'confirm' => true,
        'confirmPassword' => true,
    ]),                                                               // [tl! focus:end]
],
```

*Nastavení* dvoufázového ověření — QR kód, záložní kódy, karta, která ho zapíná —
patří profilové stránce modulu uživatelů. Viz
[Týmy a dvoufázové ověření](teams-and-two-factor.md). Tenhle balíček vlastní jen
výzvu na cestě dovnitř.

## Nahrazení jedné obrazovky

Zaregistrujte vlastní pohled v provideru aplikace. Aplikační providery bootují až
po všech balíčkových, takže platí ten váš:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Jen tuhle jednu. Zbylých šest zůstane tak, jak je modul      [tl! focus:start]
        // zaregistroval, takže nahrazení přihlašovací obrazovky
        // neznamená vlastnit i obnovu hesla a dvoufázovou výzvu.
        Fortify::loginView('auth.login');                           // [tl! focus:end]
    }
}
```

Nebo vraťte všech sedm zpátky pomocí `'views' => false`.

## Hlídání panelu

Routy, které registruje `wire-panels`, ve výchozím stavu vyžadují přihlášeného
uživatele:

```php
// config/wire-panels.php
'routes' => [
    'enabled' => true,
    'middleware' => ['web', 'auth'],   // výchozí hodnota [tl! focus]
],
```

Routy psané ručně berou tytéž tři argumenty skupiny — makro registruje uvnitř té
skupiny, ve které ho zavoláte:

```php
// routes/web.php
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])   // [tl! focus]
    ->prefix('admin')
    ->group(function (): void {
        Route::wireResources();
    });
```

`auth` pošle nepřihlášeného návštěvníka na routu jménem `login`, což je právě ta
routa, na kterou odpovídá první obrazovka tohohle balíčku.

## Co nedělá

**Autorizaci.** Být přihlášený není totéž jako mít oprávnění. Každá kontrola
v tomhle stacku je `Gate::allows()` a kdo smí vidět který resource, řeší
[Autorizace](../start/authorization.md).

**Profilovou stránku.** Vlastní účet přihlášeného uživatele — jméno, fotka,
heslo, karta dvoufázového ověření — patří
[modulu uživatelů](users.md).

**`DomainModule`.** `wire-module-*` je jméno pro hotovou část, kterou instalátor
nabízí, a tenhle balíček jí je; není to ale [modul](../panels/modules.md) ve smyslu
manifestu. Modul jmenuje resources, dashboardy a skupinu v menu — a pro
autentizaci žádná administrační stránka není, jen stránky na cestě dovnitř.

## Související

- [Instalace](../start/installation.md) — `wire:install`, který tenhle balíček nabízí vedle ostatních
- [Modul uživatelů](users.md) — profilová stránka a místo, kde se nastavuje dvoufázové ověření
- [Týmy a dvoufázové ověření](teams-and-two-factor.md) — čtyři řádky, které zapnou 2FA ve Fortify
- [Administrační shell](../admin/overview.md) — rám, ve kterém se tyhle obrazovky vykreslují, a menu, které plní
- [Autorizace](../start/authorization.md) — kam smí přihlášený uživatel
