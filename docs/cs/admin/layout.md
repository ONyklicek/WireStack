---
order: 20
summary: Blade komponenta, kterou váš layout jmenuje, sloty, které vystavuje, přihlašovací rám vedle ní a kam patří přihlášený uživatel.
---

# Layout

Shell je Blade komponenta, ne konfigurační objekt. Váš layout ji jmenuje a plní
její sloty — proto jsou brand, přihlašování i chrome markup, který napíšete, a ne
třída, kterou nastavíte.

## Sloty

| Slot | Kam se dostane | Typický obsah |
| --- | --- | --- |
| `head` | do `<head>`, před vlastní tagy Livewire | `@vite(...)`, meta tagy, font |
| `brand` | vlevo v horní liště | název aplikace, logo |
| `topbar` | horní lišta, za spouštěčem palety | přepínač tenantů, drobečky |
| `user` | konec horní lišty | účet, odhlášení |
| *(výchozí)* | element `<main>` | stránka |

Sloty místo konfigurace jsou záměr: třída, která drží brand, barvy a auth, je
přesně to, co z shellu udělá panel builder — a všechno, co by nesla, je markup,
který umíte napsat sami.

## Přihlašování

Shell má rám pro přihlášení a **žádnou autentizaci**: `<x-wire-admin::auth-layout>` je vycentrovaná karta se stejnou hlavou — rozhodnutí o motivu, assety, interakční vrstva — a bez menu, palety i zvonku, protože nic z toho před existencí uživatele nic neznamená.

```blade
{{-- resources/views/auth/login.blade.php, vykreslené Fortify nebo Breeze --}}
<x-wire-admin::auth-layout :title="__('Přihlášení')">
    <x-slot:brand>{{ config('app.name') }}</x-slot:brand>
    <x-slot:footer><a href="{{ route('password.request') }}">{{ __('Zapomenuté heslo?') }}</a></x-slot:footer>

    <form method="POST" action="{{ route('login') }}">@csrf
        {{-- vaše pole --}}
    </form>
</x-wire-admin::auth-layout>
```

Zbytek vlastní Laravel, a je to záměr: **Fortify** (headless) nebo **Breeze** (scaffolding) už nesou omezení počtu pokusů, tokeny pro reset hesla a jejich expiraci, ověření e-mailu, dvoufaktor i regeneraci session. Panel, který si tohle napíše znovu, vlastní bezpečnostní plochu a nezíská funkci — takže tenhle balíček dodává kartu a ani jednu přihlašovací cestu. Tam, kde obrazovku pro nastavení dvoufázového ověření v panelu *chcete*, ji dodá [modul uživatelů](../modules/teams-and-two-factor.md) a volá vlastní akce Fortify — obrazovka nad vlastníkem, ne druhý vlastník.

Spojuje je middleware, který stejně píšete:

```php
Route::middleware(['web', 'auth'])->prefix('admin')->group(fn () => Route::wireResources());
```

Pro každou [zónu](../panels/routing.md#zony), když jich aplikace má víc — `can:admin` na jedné skupině, `can:business` na druhé — a pro jednotlivé stránky přes `RoutePage::make($page)->permission('users.manage')`, což se stane Laravelím `can:` middlewarem. Všechno odpovídá Gate, takže role ze Spatie i wildcardy z `nyoncode/laravel-permission-extended` fungují, aniž by o nich tenhle balíček věděl.

## Kdo je přihlášený

Shell ukazuje přihlášeného uživatele v rohu, i s menu. Nedodává **žádnou stránku
profilu ani odhlašovací route**, protože nevlastní auth — ty přijdou slotem
`user-menu` a `<x-wire-admin::menu-item>` existuje proto, aby to, co do něj dáte,
vypadalo jako menu, ve kterém to sedí:

```blade
<x-slot:user-menu> <!-- [tl! focus:8] -->
    <x-wire-admin::menu-item :href="route('profile')" icon="outline:user-circle" wire:navigate>
        Profil
    </x-wire-admin::menu-item>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <x-wire-admin::menu-item type="submit" icon="outline:arrow-right-start-on-rectangle">
            Odhlásit se
        </x-wire-admin::menu-item>
    </form>
</x-slot:user-menu>
```

[Modul uživatelů](../modules/users.md) dodává stránku profilu pro přihlášeného
uživatele, pokud ji nechcete psát sami.

## Související

- [Sidebar](sidebar.md) — menu, které tenhle layout staví vedle stránky
- [Branding a motiv](branding.md) — logo a rozhodnutí o motivu v hlavičce
- [Auth modul](../modules/auth.md) — hotové obrazovky pro rám výše
- [Users modul](../modules/users.md) — profilová stránka do rohového menu
