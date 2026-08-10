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

<a id="wirex-is-not-defined-after-a-wire-navigate-visit"></a>
## `wireX is not defined` po návštěvě přes `wire:navigate`

**Příznak:** Stránka funguje, když se načte přímo, ale při příchodu přes
`wire:navigate` — nebo tlačítky Zpět/Vpřed v prohlížeči — je tabulka či formulář
mrtvý. V konzoli je `ReferenceError` se jménem Wire komponenty
(`wireRecordSelection`, `wireDropdown`, `wireSortable`, …). Nejhlasitější varianta
je šedý scrim přes celou stránku nad mrtvou tabulkou: každý backdrop mobilního
sheetu je navázaný na stav, který nikdy nevznikl.

**Příčina:** Bundle definující komponentu dorazil *spolu* s novou stránkou a cesta
cachovaného Zpět/Vpřed v Livewire nečeká na nově injektované `<head>` skripty, než
na prohozeném markupu inicializuje Alpine.

**Náprava:** Dejte direktivu do `<head>` layoutu, ať jsou controllery v dokumentu
už od prvního načtení stránky, kde je nemá co předběhnout:

```blade
<head>
    @wireStackScripts
</head>
```

Viz [Začínáme → JavaScriptové assety](getting-started.md#javascriptove-assety).

---

## JavaScriptové 404 a `wireX is not defined`

**Příznak:** Tentýž `ReferenceError` jako v předchozí sekci, ale na *každé* stránce
a bez ohledu na to, jak jste se na ni dostali — objeví se i po tvrdém reloadu.
V network tabu jsou 404 na
`/wire-core/assets/dropdown.js`, `/wire-table/assets/records.js` nebo sourozence
pod `/wire-forms/…` či `/wire-sortable/…`.

**Příčina:** Sešly se dvě věci. Balíčky si normálně bundly zkopírují do
`public/vendor/<balíček>` a emitují *tyhle* cesty, takže PHP nic neřeší — URL
`/wire-core/assets/…` ve vašem markupu znamená, že se kopie nepovedla a zaskakuje
za ni routa balíčku. A váš webserver na tu routu odpovídá sám, místo aby ji předal
PHP. Standardní nginx konfigurace Laravelu posílá cokoliv, co není na disku, do
`index.php`, konfigurace s blokem pro statické assety už ne:

```nginx
location ~* \.(js|css)$ {
    try_files $uri =404;      # routa není soubor na disku → 404, PHP to nikdy neuvidí
}
```

Nic z toho není specifické pro tyhle balíčky: tentýž blok vrací 404 i na Livewire
vlastní `/livewire/livewire.js`.

**Řešení — zapisovatelné `public/`, nebo kopie při buildu.** Obvyklou příčinou je
`public/`, do kterého webový uživatel nesmí zapisovat, nebo read-only kontejner.
Buď zápis povolte, nebo kopii udělejte, dokud je filesystém ještě zapisovatelný:

```bash
php artisan vendor:publish --tag=laravel-assets --force
```

**Nebo zpřístupněte routu** tím, že necháte blok propadnout do front controlleru —
správná odpověď tam, kde zapisovatelné `public/` opravdu není ve hře:

```nginx
location ~* \.(js|css)$ {
    try_files $uri /index.php?$query_string;   // [tl! focus]
}
```

Příbuzné varování, když kopie existují, ale po upgradu je nešlo obnovit:

```text
wireStack: the published copies of wire-core/dropdown are older than the bundles
the packages ship, and are what this page just loaded.
```

Stránka funguje dál — starý bundle je lepší než žádný — ale stojí za tím tentýž
problém se zápisem. Viz
[Začínáme → JavaScriptové assety](getting-started.md#javascriptove-assety).

---

<a id="reordering-stops-working-or-my-own-code-loses-window-sortable"></a>
## Řazení přestalo fungovat, nebo můj kód přišel o `window.Sortable`

**Příznak:** Po upgradu vyhazuje vlastní JavaScript vaší aplikace
`Sortable is not defined`.

**Příčina:** SortableJS je nově zkompilovaný do bundlu `wire-sortable` a
`config('wire-sortable.sortablejs_cdn')` je ve výchozím stavu `null` — CDN skript,
který po sobě nechával globál, se tedy už nenačítá. Vlastního řazení Wire se to
netýká; drag controller používá zabundlovanou kopii.

**Náprava:** Pokud váš kód globál potřebuje, řekněte si o něj — buď nastavte
konfigurační klíč zpět na CDN URL, nebo si dejte `npm install sortablejs` a
`window.Sortable` přiřaďte sami. Viz
[Instalace Sortable → SortableJS](sortable/installation.md#sortablejs).

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
