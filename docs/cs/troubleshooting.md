---
order: 90
---

# Řešení potíží

Běžné problémy a jejich nápravy. Většina jsou nesoulady konfigurace v hostitelské
Laravel aplikaci, ne bugy ve Wire.

---

## Tlačítka a inputy jsou neviditelné

**Příznak:** Tlačítka, badge a focus ringy se vykreslí jako bílé-na-průhledném nebo
zmizí úplně.

**Příčina:** Tailwind barva `primary` není definovaná. Wire používá `primary` pro
každý akcent, takže bez ní tyto prvky nemají barvu.

**Náprava:** Definujte `primary` v konfiguraci Tailwindu — viz
[Začínáme → Barva primary](getting-started.md#barva-primary) a
[Vzhled → Barvy](theming.md#barvy).

---

<a id="components-render-unstyled"></a>
## Komponenty se vykreslují bez stylů

**Příznak:** Markup se objeví, ale bez Tailwind stylování.

**Příčina:** Tailwind neskenuje vendor pohledy Wire, takže se jejich třídy
odstraní z buildu.

**Náprava:** Přidejte cesty k pohledům Wire do pole `content` v Tailwindu:

```js
content: [
    './vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
    './vendor/nyoncode/wire-core/src/**/*.php',
    './vendor/nyoncode/wire-forms/resources/views/**/*.blade.php',
    './vendor/nyoncode/wire-forms/src/**/*.php',
    './vendor/nyoncode/wire-table/resources/views/**/*.blade.php',
    './vendor/nyoncode/wire-table/src/**/*.php',
    './vendor/nyoncode/wire-sortable/resources/views/**/*.blade.php',
    './vendor/nyoncode/wire-sortable/src/**/*.php',
],
```

Cesty `src/**/*.php` jsou důležité: některé komponenty skládají své utility třídy
v PHP (pozicování, šířka, výška), takže skenování jen Blade pohledů nechá tyto
třídy odstraněné. Na Tailwindu 4 přidejte odpovídající `@source` řádky pro
`resources/views` **i** `src` (viz průvodce začínáme).

Poté přebuildujte assety (`npm run build` nebo `npm run dev`).

---

## Slide-over je ukotvený vlevo, přetéká nebo nejde scrollovat

**Příznak:** Slide-over akce (`->slideOver()`) se objeví přišpendlený **vlevo**
se ztmavenou stránkou vpravo, jeho obsah **přetéká** za viewport, patička sedí
**mimo obrazovku dole** a tělo nejde scrollovat.

**Příčina:** Slide-over skládá své utility pro pozicování, šířku a výšku v PHP
(`SlideOverComponent`), ne v Blade souboru — třídy jako `sm:right-0`,
`sm:pl-10`, `sm:h-full`, `max-h-[85vh]` a `sm:max-w-2xl`. Pokud Tailwind skenuje
jen `resources/views` a ne `src` balíčku, tyto třídy se odstraní: bez `sm:right-0`
panel spadne vlevo a bez výškových utilit není nikdy omezený na výšku, takže
přetéká místo scrollování těla.

**Náprava:** Přidejte cesty `src/**/*.php` (Tailwind 3) nebo odpovídající `@source`
řádky (Tailwind 4) podle [Komponenty se vykreslují bez stylů](#komponenty-se-vykresluji-bez-stylu),
pak přebuildujte. Viz také průvodce začínáme.

---

## „No publishable resources for tag"

**Příznak:** `vendor:publish` hlásí žádné zdroje pro tag jako
`wire-forms-config`.

**Příčina:** Publish tagy Wire používají oddělovač `::`, ne pomlčku.

**Náprava:** Použijte správný formát tagu:

```bash
php artisan vendor:publish --tag=wire-forms::config   # ✅
php artisan vendor:publish --tag=wire-forms::views
php artisan vendor:publish --tag=wire-forms::translations
```

Skupiny jsou `config`, `views`, `translations` a (kde je to relevantní)
`migrations` — každá s prefixem krátkého názvu balíčku a `::`.

---

## Chyby Alpine nebo komponenty reagující dvakrát

**Příznak:** Chyby v konzoli o dvojité inicializaci Alpine, nebo direktivy
spouštějící se dvakrát.

**Příčina:** Alpine byl nainstalován a nastartován samostatně. Livewire 3 už
Alpine dodává a startuje.

**Náprava:** Odstraňte jakoukoli samostatnou instalaci Alpine a volání
`Alpine.start()`. Nechte ho poskytnout Livewire.

---

## JavaScript pole neběží uvnitř modalu

**Příznak:** JS-based pole funguje na normální stránce, ale je mrtvé při otevření
v modalu nebo po Livewire updatu.

**Příčina:** Prostý `<script>` tag injektovaný přes DOM morphing Livewire se nikdy
nespustí.

**Náprava:** Načtěte skript pomocí direktivy `@assets` Livewire (vestavěný
`TiptapEditor` to dělá). Pokud stavíte vlastní JS pole, následujte stejný
vzor — viz
[Rozšíření formulářů → JS-based pole](forms/custom-fields.md#js-based-pole).

---

## `save()` vyhodí chybu bez modelu

**Příznak:** Volání `$form->save()` vyhodí chybu.

**Příčina:** Formulář nemá model (`model(null)` nebo žádný nastavený), takže není
co uložit.

**Náprava:** Buď nastavte model (`->model(User::class)` pro create,
`->model($user)` pro update), poskytněte vlastní perzistenci pomocí
`->using(...)`, nebo místo toho zavolejte `->validate()`, když potřebujete jen
data. Viz [Režimy modelu](forms/overview.md#rezimy-modelu).

---

## Notifikace / toasty se neobjevují

**Příznak:** Akce uspějí, ale žádný toast ani notifikace se nezobrazí.

**Příčina:** V layoutu chybí kontejner notifikací.

**Náprava:** Přidejte ho jednou blízko konce `<body>`:

```blade
<x-wire-notifications::toast-container />
```

Layout viz [Začínáme](getting-started.md#sablona-layoutu) a
[Core → Notifikace](core/notifications.md).

---

## Validační chyby se nezobrazují

**Příznak:** Validace selže (uložení je zablokováno), ale pod polem se nevykreslí
žádná zpráva.

**Příčina:** `statePath` formuláře neodpovídá vlastnosti komponenty držící stav,
takže se klíče chyb a cesty polí rozcházejí.

**Náprava:** Ujistěte se, že `->statePath('data')` odpovídá veřejné vlastnosti
(`public array $data = []`) a že formulář vykreslujete pomocí `{{ $this->form }}`.
Chyby polí jsou klíčované plnou state cestou (například `data.email`) — na tuto
cestu asertujte v testech.

---

## Pořád zaseknutí?

- Znovu si přečtěte dokumentaci konkrétního balíčku pro danou funkci (Forms, Table, Sortable, Core).
- U problémů s runtime/stavem spusťte Integration sadu, zda se chování reprodukuje:
  `vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"`.
- Zkontrolujte [Návod k upgradu](upgrade.md), pokud problém začal po aktualizaci.
