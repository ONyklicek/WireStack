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

**Pole jsou z `wire-forms`, odesílá je prohlížeč.** Každý input na těchhle
obrazovkách pochází ze schématu deklarovaného v PHP a vykresleného v režimu
nativního odeslání: `name` a `value=old(…)` tam, kde by formulář panelu nesl
`wire:model`, a chyby se čtou ze sdíleného balíku `$errors`, který Fortify stejně
plní. Jsou to tedy stejná pole, stejná výbava i stejné Alpine jako u formulářů za
dveřmi — včetně přepínače pro odhalení hesla — a přihlášení funguje i s vypnutým
JavaScriptem, protože na kritické cestě ho nic nepotřebuje. Co tím získáte, je
[sekce o polích](#pole): obrazovka získá pole bez publikovaného pohledu.

**Providery aplikace bootují jako poslední, takže vyhrává váš.** Pojmenování
vlastního pohledu v provideru aplikace nahradí jeden z těchhle, aniž by vypnulo
zbytek — balíčkové providery bootují dřív než aplikační, takže poslední
`Fortify::loginView()` patří aplikaci.

**Odkazy se kreslí z týchž přepínačů, které vytvářejí routy.** Odkaz „Zapomněli
jste heslo?“ se objeví jen tam, kde je zapnuté `Features::resetPasswords()`,
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

Každý klíč má i override přes prostředí — `WIRE_AUTH_LAYOUT`, `WIRE_AUTH_VIEWS`
a `WIRE_AUTH_USER_MENU` — takže nasazení může obrazovky vrátit zpátky aplikaci
nebo je nasměrovat na jiný rám bez druhého konfiguračního souboru.

`auto` najde nejdřív layout, který zapsal instalátor, potom rám shellu. Bez
obojího vyhodí vykreslení obrazovky `AuthFrameException` — větu, která jmenuje
tenhle klíč, místo Blade hlášky „unable to locate component“ o vrstvu níž.
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

## Přizpůsobení obrazovek

Pět příček, od nejlevnější, a každá si nechává ty pod sebou: změna jednoho slova
neznamená vlastnit formulář, přidání pole neznamená vlastnit značkování kolem něj
a vlastnictví jedné obrazovky neznamená vlastnit zbylých šest.

| Co chcete změnit | Sáhněte po | Co pak vlastníte |
| --- | --- | --- |
| Slovo, nadpis, jazyk | `lang/vendor/wire-module-auth/` | klíče, které jste napsali |
| Stránku kolem karty | `wire-module-auth.layout` | vlastní layout |
| Pole ve formuláři | `AuthForms::extend()` | closure, kterou jste napsali |
| Značkování kolem nich | publikované pohledy | pohledy, které jste si nechali |
| Celou obrazovku | `Fortify::loginView()` | tu jednu obrazovku |

### Texty

Každý řetězec na těchhle obrazovkách je klíč v `wire-module-auth::messages` —
popisky, nadpisy, věta pod každým nadpisem, odkazy, „Odhlásit se“. Laravel slučuje
soubor aplikace **přes** soubor balíčku, klíč po klíči, takže celou změnou je
soubor, ve kterém je jen to, s čím nesouhlasíte:

```php
// lang/vendor/wire-module-auth/en/messages.php
return [
    'sign_in_heading' => 'Přihlášení pro zaměstnance',
    'sign_in_description' => 'Účty vydává kancelář; registrace tu není.',
    'remember_me' => 'Zůstat přihlášen na tomhle zařízení',
];
```

Instalátor publikuje oba dodávané jazyky celé a tag udělá totéž sám o sobě. Úplná
kopie je snadný začátek a zlozvyk: klíč, který jste nezměnili, je klíč, který
přestal sledovat balíček — ořežte tedy soubor na řádky, které jste mysleli vážně.
Jazyk, který balíček nedodává — dodává `en` a `cs` — je adresář vedle nich, ne
fork, a klíč, který v něm chybí, spadne zpátky na `fallback_locale`:

```bash
php artisan vendor:publish --tag=wire-module-auth::translations
# lang/vendor/wire-module-auth/{en,cs}/messages.php — vedle nich přidejte de/, pl/, …
```

### Rám

Layout, ve kterém se obrazovky vykreslují, je jeden klíč konfigurace — a je to
změna, kterou je potřeba udělat dřív než jakoukoli jinou: právě ta dostane váš
styl, vaši značku a vaše pozadí na všech sedm najednou, aniž byste sáhli na jediný
pohled. Viz [Konfigurace](#konfigurace) výše.

### Pole

**Inputy už nejsou značkování.** Každé pole na těchhle obrazovkách je pole
z `wire-forms` deklarované v PHP, v `Forms\AuthForms`, vykreslené v režimu
nativního odeslání — nese `name` a `value=old(…)` místo `wire:model`, takže
formulář odesílá prohlížeč přesně tak, jako když bylo značkování psané ručně.
Přidání pole na přihlašovací obrazovku je díky tomu closure v provideru, ne
publikovaný pohled, který pak vlastníte navždycky:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireModuleAuth\Forms\AuthForm;
use NyonCode\WireModuleAuth\Forms\AuthForms;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        app(AuthForms::class)->extend(                    // [tl! focus:start]
            AuthForm::Register,
            // Dovnitř pole, jak jsou deklarovaná; ven to, co se má vykreslit.
            // Přidejte k nim, nahraďte jedno, zahoďte jedno, nebo vraťte vlastní.
            fn (array $fields): array => [
                ...$fields,
                TextInput::make('company')
                    ->label(__('Firma'))
                    ->required()
                    ->autocomplete('organization'),
            ],
        );                                                // [tl! focus:end]
    }
}
```

Callbacky běží v pořadí registrace, každý nad tím, co vrátil ten předchozí, a
provider aplikace bootuje až po všech balíčkových — takže se tohle s modulem,
který si přidává vlastní pole, skládá, místo aby s ním závodilo.

| Case `AuthForm` | Obrazovka | S čím se dodává |
| --- | --- | --- |
| `Login` | Přihlášení | `email` (podle toho, co pojmenovává `fortify.username`), `password`, `remember` |
| `Register` | Vytvoření účtu | `name`, `email`, `password`, `password_confirmation` |
| `ForgotPassword` | Žádost o odkaz na obnovu | `email` |
| `ResetPassword` | Nastavení nového hesla | `token` (skrytý), `email`, `password`, `password_confirmation` |
| `ConfirmPassword` | Potvrzení hesla | `password` |
| `TwoFactorCode` | Dvoufázová výzva | `code`, jako šest políček |
| `TwoFactorRecovery` | Dvoufázová výzva | `recovery_code` |

Poslední obrazovka má dva casy, protože je to jeden formulář odesílaný na jednu
URL s jiným polem podle toho, co má člověk po ruce — a náhrada inputu na kód není
důvod zdědit i to, co jste udělali s tím záložním.

Pole pro identitu se řídí `fortify.username`: přebírá jeho jméno a inputem
`type="email"` je jen tam, kde se to jméno jmenuje `email`. Formátové pravidlo
prohlížeče na poli s osobními čísly odmítne každou hodnotu, pro kterou ho
aplikace nastavila — a řekne to vlastními slovy dřív, než se cokoli odešle.

**Dostat input na stránku je půlka nového pole.** Co s hodnotou udělá požadavek,
je věc akce ve Fortify a vždycky byla: `Fortify::createUsersUsing()` je místo, kde
se zapisuje sloupec čtený při registraci, a pole přidané tady bez toho, abyste
o něm té akci řekli, je input, jehož hodnotu nikdo nevaliduje a nikdo neukládá.

**Pole, které by prohlížeč neuměl odeslat, je při vykreslení odmítnuté**, jménem,
výjimkou `FormConfigurationException`. Cokoli, co uprostřed formuláře potřebuje
roundtrip, se váže jen přes Livewire a nenese `name` — pole s `live()`, `Select`
hledající na serveru, `FileUpload`, `Repeater` — takže na téhle straně dveří by
vykreslilo input, který tiše odešle prázdnou hodnotu. Výjimka se jménem pole je
levnější verze téhle zkušenosti. Viz
[přehled formulářů](../forms/overview.md#rendering).

Formuláře se dají číst i z vlastních pohledů, a právě to dělá náhradní obrazovku
levnou — viz [níž](#vlastni-obrazovka-ve-stejnem-ramu):

```php
app(AuthForms::class)->login();               // Form z wire-forms, připravený k vypsání
app(AuthForms::class)->resetPassword($token, $email); // co nesl odkaz z mailu
```

### Značkování

```bash
php artisan vendor:publish --tag=wire-module-auth::views
```

Devět pohledů přistane v `resources/views/vendor/wire-module-auth/`, kam se Laravel
dívá dřív než do balíčku. Jeden stojí za přečtení dřív, než začnete editovat cokoli
dalšího: **`screen.blade.php`** — volání rámu, nadpis, věta pod ním, stavová
hláška z session od Fortify a souhrn chyb. Všech sedm obrazovek se vykresluje skrz
něj, takže změna tady je změnou každé z nich.

Co v těch pohledech naopak *už není*, jsou pole: každá obrazovka vypíše objekt
formuláře a vlastní jen výbavu kolem něj — `<form>`, `@csrf`, odesílací tlačítko,
odkazy. Publikování je tedy na změnu téhle výbavy a
[sekce výš](#pole) je na změnu inputů.

**Publikování zkopíruje všech devět a kopie přestane sledovat balíček.** Oprava
vydaná v balíčku se dostane do jeho pohledu, ne do vašeho, a na stránce po ní není
žádná chyba — jen značkování z minulé verze, které se vykresluje v pořádku. Smažte
tedy soubory, kvůli kterým jste nepřišli, a nechte si ty, kvůli kterým ano.

### Vlastní obrazovka ve stejném rámu

`<x-wire-module-auth::screen>` je šev mezi obrazovkou a jejím rámem a vaše vlastní
pohledy se v něm můžou vykreslovat. Zůstane vám tím vyhodnocení layoutu, nadpis,
stavová hláška od Fortify („odkaz je na cestě“) i souhrn chyb, který ohlásí
neúspěšné přihlášení tam, kde ho uživatel uvidí — chyba přihlášení je hlášená pod
`email` bez ohledu na to, které pole bylo špatně, takže hláška vykreslená jen pod
vlastním inputem je hláška pod tím nesprávným.

Pole můžou přijít odtamtud, odkud si je bere dodávaná obrazovka, takže vlastní
pohled je jen výbava a nic víc — nebo si
[`Form`](../forms/overview.md) postavíte sami a zavoláte na něm `->nativeSubmit()`:

```blade
{{-- resources/views/auth/login.blade.php --}}
<x-wire-module-auth::screen                                     {{-- [tl! focus:start] --}}
    :title="__('Přihlášení')"
    :heading="__('Přihlášení pro zaměstnance')"
    :description="__('Použijte adresu, kterou vám vydala kancelář.')"
>                                                               {{-- [tl! focus:end] --}}
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        {{-- Stejná pole, jaká vykresluje dodávaná obrazovka, i s rozšířeními. --}}
        {{ app(\NyonCode\WireModuleAuth\Forms\AuthForms::class)->login() }}

        <x-wire::button type="submit" class="w-full">{{ __('Přihlásit se') }}</x-wire::button>
    </form>
</x-wire-module-auth::screen>
```

Pak ji pojmenujte, stejně jako sekce níž pojmenovává jakoukoli náhradu:
`Fortify::loginView('auth.login')`.

Na co smí obrazovka odkazovat, odpovídá Fortify, ne vy — a
`NyonCode\WireModuleAuth\Support\Screens` je místo, kde se na to ptát:

```php
use NyonCode\WireModuleAuth\Support\Screens;

Screens::canRegister();        // Features::registration()
Screens::canResetPassword();   // Features::resetPasswords()
Screens::mustVerifyEmail();    // Features::emailVerification()
Screens::hasTwoFactor();       // Features::twoFactorAuthentication()
Screens::canSignOut();         // Route::has('logout') — odhlášení není feature
```

Zeptejte se dřív, než odkaz vykreslíte. „Zapomněli jste heslo?“ pod formulářem,
jehož routu Fortify nikdy nezaregistrovalo, je 404, o které se aplikace dozví od
uživatele.

### Nahrazení jedné obrazovky

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

### Cesta ven

`'user_menu' => false` vyndá „Odhlásit se“ z uživatelského menu shellu — pro
aplikaci, která si [slot toho menu](../admin/layout.md#kdo-je-prihlaseny) vyplňuje
ručně, nebo pro tu, jejíž cesta ven je někde úplně jinde. Vaše vlastní řádky
přicházejí stejnou cestou jako řádek tohohle balíčku a pořadí drží `sort`, protože
tuhle oblast nevlastní nikdo: modul uživatelů přispívá odkazem na profil se `10`,
tenhle balíček odhlášením se `100`.

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use NyonCode\WireCore\Foundation\View\PageChrome;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(PageChrome::class)->add(   // [tl! focus:start]
            'menu.support',                         // resources/views/menu/support.blade.php
            PageChrome::USER_MENU,
            sort: 50,                               // za Profilem (10), před Odhlásit se (100)
        );                                          // [tl! focus:end]
    }
}
```

Do toho pohledu dejte `<x-wire::menu-item>` — komponentu, kterou používá i samotný
řádek s odhlášením — a bude vypadat jako menu, ve kterém sedí, aniž by závisel na
shellu, který ho kreslí.

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
