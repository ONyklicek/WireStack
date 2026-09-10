---
order: 80
summary: Knihovna souborů — nahrání vytvoří záznam, smazání záznamu smaže soubor a náhledy jdou z disku, na kterém soubor leží.
---

# Modul médií

Nahrát, najít, smazat. Záznam a soubor jsou jedna věc: smazání záznamu smaže
soubor, protože knihovna, která po sobě nechává sirotky, zaplní disk, na který se
nikdo nedívá.

```bash
composer require nyoncode/wire-module-media
php artisan wire-module-media:install
php artisan migrate
php artisan storage:link
```

## Jak to funguje

**Disk je uložený u každého záznamu.** Je součástí adresy souboru — stejná cesta
znamená jiný soubor na `public` a na `s3` — takže změna nastaveného disku přesune
nová nahrání a stará nechá čitelná.

**Metadata se čtou z disku, ne z prohlížeče.** Mime typ a velikost jsou to, čím
uložený soubor doopravdy je; co o sobě nahrání tvrdilo, je tvrzení.

**Záznam se zapisuje v kroku perzistence**, přes seam `using()` formuláře. To
není detail, který jde ignorovat, když tohle rozšiřujete: souborová pole se
dehydratují **až po** `mutateDataBeforeSave()`, takže cokoli, co čte `path` dřív,
dostane dočasné nahrání, ne uložený soubor.

**Z editace obrázku vzniká ve výchozím případě nový soubor.** Přepsat bajty pod
cestou, na kterou už míří jiné záznamy, je způsob, jak knihovna tiše změní, co
ukazuje publikovaná stránka — takže obvyklý výsledek editoru je nový záznam a ten
druhý je za varováním, které pojmenuje, co rozbije. Viz
[Editace obrázku](#editace-obrazku).

## Složky

Složka je **záznam, ne adresář**. Když soubor zařadíte jinam, na disku se nehne
nic — a je to záměrná výměna: přesun bajtů by změnil URL souboru, na který už
odkazuje publikovaná stránka, a uklizený bucket za rozbitý obrázek na živé
stránce nestojí. Rozložení na disku zůstává takové, jaké říká `directory`; strom
je to, co vám knihovna ukazuje.

```php
use NyonCode\WireModuleMedia\Models\MediaFolder;

$brand = MediaFolder::createIn(null, 'Brand');       // [tl! focus:3]
$logos = MediaFolder::createIn($brand, 'Logos');

$logos->moveTo(null);                                 // zpátky do kořene
```

Každá složka si ukládá **celou cestu**, místo aby procházela rodiče: drobečková
navigace se kreslí na každé obrazovce knihovny a procházení je jeden dotaz na
úroveň. Přejmenování nebo přesun složky přepíše cesty pod ní — to je cena té
volby a důvod, proč jsou tyhle dvě operace jediné, které sahají na víc než jeden
řádek.

Každé odmítnutí přichází z modelu jako `MediaException` s větou, která říká, co
neudělá a proč:

| Odmítne | Protože |
| --- | --- |
| Dvě složky se stejným názvem vedle sebe | V drobečkové navigaci je nelze rozlišit |
| Přesun složky do sebe nebo do své podsložky | Do té větve by se už nešlo dostat |
| Smazání složky, ve které ještě něco je | Smazání složky nesmí být způsob, jak přijít o soubory bez zeptání |

Obrazovka je odchytí a ukáže notifikaci. Žádné z nich znovu nekontroluje — právě
proto dostane konzolový příkaz i váš vlastní kód stejnou odpověď jako obrazovka.

## Obrazovka knihovny

Úvodní stránka je správce souborů, ne tabulka: vlevo strom složek, vpravo mřížka
nebo seznam a drobečková navigace, po které se dá vyjít ven.

- **Přetáhněte soubory z plochy** kamkoli do pravého panelu a nahrají se do
  složky, na kterou se právě díváte. **Nahrát celou složku** umí tlačítko vedle
  Nahrát — soubory se založí do složky, na kterou se díváte, protože složka je
  tady záznam a vyrobit jich potichu čtyři podle adresáře, který někdo náhodou
  přetáhl, není rozhodnutí, které má tahle obrazovka dělat.
- **Zásobník** hlásí každý soubor dávky: uloženo, už tu bylo — s odkazem na
  záznam, který si knihovna nechala — nebo odmítnuto, i s důvodem. Přežije
  otevření jiné složky, což je přesně to, co člověk dělá, když dlouhá dávka ještě
  běží. „3 se nepodařilo“ v toastu je věta, kvůli které si ty tři musí najít sám.
- **Přetáhněte dlaždici na složku** a založí se tam; složku na složku a přesune
  se celá větev. Dlaždice, která je **součástí výběru, táhne celý výběr**. Sbalená
  větev, nad kterou při tažení chvíli podržíte kurzor, se sama rozbalí a **každý
  stupeň drobečkové navigace je cíl pro puštění** — tak se soubor vrátí o úroveň
  výš, aniž byste jeho složku hledali ve stromu.
- **`⌘X` a `⌘V`** dělají totéž bez myši: vyjmout výběr, otevřít složku, vložit.
  Tažení přes třicet složek hluboký strom není gesto, které zvládne každý, a na
  dotykové obrazovce to není gesto vůbec.
- **Strom se skládá**, pamatuje si, co jste nechali otevřené, a u každé složky
  nese počet souborů z jednoho seskupeného dotazu.
- **Výběr** zaškrtávátky pro hromadný přesun nebo smazání. Lišta, která se
  objeví, zůstává dole a mřížka se posouvá pod ní: výběr vzniká scrollováním
  a lišta nahoře je lišta, ke které se musíte vracet. Mazání jde schválně po
  jednom — hromadné by nespustilo modelovou událost, která maže soubor, a bajty
  by zůstaly bez záznamu, který na ně ukazuje.
- **Seznam je seznam**: řaditelné hlavičky přes název, druh, velikost, rozměry,
  složku a datum nahrání. Hlavičky nastavují tentýž `sort`, který používá lišta
  i URL, takže seřazený pohled je pořád něco, co jde někomu poslat — a čtyři
  slova, která tam bývala, dál fungují v odkazu, který si někdo uložil.
- **Přejmenování** mění, pod čím je soubor *veden*, nikdy kde je uložený.
- **Hledání prochází celou knihovnu**, ne otevřenou složku. Hledat jen tam, kde
  zrovna stojíte, je způsob, jak soubor, který si nikdo nepamatuje založit,
  zůstane ztracený.

## Neveřejné soubory a kdo je smí vidět

Disk, který aplikace **publikuje** — tedy má `url` ve svém záznamu ve
`filesystems.disks` — odpoví vlastní adresou a prohlížeč si soubor stáhne přímo.
To je nejrychlejší možná varianta a nic z tohohle na ni nesahá.

Disk, který nepublikujete, takovou adresu nemá a knihovna dřív odpověděla
`null`: prázdné dlaždice, chybějící odkaz ke stažení, nikde vysvětlení. Takové
soubory teď proudí přes vlastní route modulu.

```php
'route' => [ // [tl! focus:4]
    'enabled' => true,
    'prefix' => 'wire-media',
    'middleware' => ['web', 'auth'],
],
```

**„Publikovaný“ znamená klíč `url` na disku, ne to, co říká `Storage::url()`.**
Laravel odpoví `/storage/{path}` pro *jakýkoli* lokální disk, ať ta adresa vede
kamkoli — je to pohodlí pro disk `public` a chybný odhad pro každý jiný. Věřit
tomu je způsob, jak se neveřejná smlouva ocitne na stránce odkazovaná, jako by
byla veřejná.

Když route vypnete, nepublikovaný disk zase odpoví `null`, a to schválně: rozbitý
obrázek je lepší odpověď než veřejné URL k souboru, který měl zůstat neveřejný.

### Policy

Zaregistrujte ji a platí všude naráz — na obrazovce, ve výběru i na route, která
soubor streamuje:

```php
Gate::policy(NyonCode\WireModuleMedia\Models\Media::class, MediaPolicy::class);
```

Schopnosti jsou Laravelovy vlastní: `viewAny`, `view`, `create`, `update`,
`delete`. Každá měnící metoda správce se ptá dřív, než něco udělá — ne že by view
schovalo tlačítko. Livewire metoda je veřejný endpoint a schované tlačítko není
kontrola.

**Bez zaregistrované policy se neodmítá nic.** Laravelova brána zamítá schopnost,
kterou nikdo nedefinoval, takže ptát se jí bezpodmínečně by při upgradu zamklo
každou existující knihovnu před jejími vlastními soubory. Nenapsat policy je
způsob, jak aplikace říká „tohle není řízené oprávněními“, a bere se to vážně.

## Náhledy

Mřížka dřív načítala originály: sto souborů znamenalo sto fotek v plné velikosti,
které prohlížeč zmenšil až potom, co zaplatil za každý bajt. Nahraný soubor teď
dostane zmenšenou kopii — jeden WebP, nejdelší hrana ve výchozím stavu 400 px — a
mřížka, výběr i pole médií ukazují ji. Detail a odkaz ke stažení míří dál na
originál, kterého se nikdo nedotkne.

```php
'thumbnails' => [
    'enabled' => true,
    'width' => 400,       // dlaždice: nejdelší hrana, poměr se zachová, malé se nezvětšují
    'directory' => 'thumbnails',

    'sizes' => [          // [tl! focus:3]
        'row' => 96,
        'preview' => 1200,
    ],
],
```

**Jedna kopie obsluhovala tři plochy.** 32pixelový řádek v seznamu, dlaždice
v mřížce i náhled v detailu načítaly tentýž 400pixelový WebP — a dvě z těch tří
platily za pixely, které zahodily, na každé obrazovce knihovny. Každá plocha si
teď řekne o velikost, kterou opravdu kreslí, a retina displej dostane přes
`srcset` tu o stupeň větší. Rozlišovací deskriptory, ne šířky: atribut `sizes` je
odhad o layoutu, na který model nevidí.

`width` pořád pojmenovává **dlaždici** a `thumb_path` ji pořád drží. Knihovna po
upgradu vykresluje přesně jako předtím a ostatní velikosti získá s nahrávanými
soubory — nebo naráz:

```bash
php artisan wire-module-media:thumbnails --force              # udělat všechno znovu
php artisan wire-module-media:thumbnails --size=preview       # [tl! focus]
```

`--size` doplní jedno jméno, které do konfigurace přibylo později, a kopie, které
už existují, nechá být. Velikost, která nikdy nevznikla, spadne zpátky na
dlaždici a pak na originál: chybějící varianta není nikdy rozbitý obrázek.

**Neveřejný disk je dostane taky.** Vlastní adresu nemá, takže streamovaná route
bere velikost jako parametr — bez toho zůstala mřížka sta neveřejných fotek stem
fotek v plné velikosti, což je přesně to, čemu mají náhledy bránit, jen se to dělo
těm, kdo je potřebují nejvíc.

**Každá dlaždice je vybarvená dřív, než dorazí obrázek.** Vytvořit kopie je
zmenšení a zmenšení na jeden pixel je při tom zadarmo — takže si záznam uloží
průměrnou barvu obrázku jako `#rrggbb` a mřížka má svůj tvar a hrubé barvy hned,
místo aby se čtyřicetkrát přelila, jak obrázky dobíhají. Uložené `width` a
`height` jdou z téhož důvodu na každý `<img>`.

**Náhled je pohodlí, takže kvůli němu nesmí spadnout nahrávání.** PDF nemá
pixely, SVG rastrovou kopii nepotřebuje, GIF by přišel o animaci, server může být
postavený bez GD a vzdálený disk nemá lokální soubor ke čtení. Každý z těch
případů nechá `thumb_path` prázdný a `previewUrl()` se vrátí k originálu — takže
žádný view nepotřebuje druhou větev a žádné nahrání se kvůli tomu neodmítne.

Mezi puštěním fotky a tím, že ji uvidíte, býval spinner — v lepším případě jeden
round trip, a delší, když se náhled vyrábí na frontě. Prohlížeč ty bajty už má,
takže se dlaždice objeví **okamžitě**, vykreslená přímo ze souboru přes object
URL, a s ukazatelem, který se hýbe. Uložený soubor ji vystřídá, jakmile dorazí, a
object URL se uvolní — každé z nich drží celý soubor v paměti, dokud se tak
nestane, a knihovna je přesně to místo, kam někdo pustí čtyřicet fotek naráz.

Když se náhledy dělají na frontě, obrazovka se sama obnovuje, dokud ty na ní
nedorazí, a pak přestane. Ptá se jen tehdy, když se odpověď může změnit: náhledy
zapnuté, dělané na frontě a soubor nahraný v posledních pár minutách. PNG, které
GD odmítlo před dvěma měsíci, zůstane bez náhledu navždy — a obrazovka nechaná
otevřená na té složce by se na něj jinak ptala každé tři vteřiny, dokud by někdo
nezavřel záložku.

Soubory, které přišly dřív, než náhledy existovaly, se doženou na požádání:

```bash
php artisan wire-module-media:thumbnails          # [tl! focus:2]
php artisan wire-module-media:thumbnails --force  # po změně šířky
```

Jede po dávkách a hlásí dvě čísla: kolik náhledů vyrobil a na kolik souborů se
podíval. Rozdíl není chyba k prošetření — to jsou ta PDF.

### Když nechcete GD

GD je navázané ve výchozím stavu, protože ho většina PHP buildů má. Aplikace s
Imagickem, Interventionem nebo se zmenšovací CDN vymění jednu vazbu:

```php
$this->app->bind( // [tl! focus:4]
    NyonCode\WireModuleMedia\Contracts\MakesThumbnails::class,
    ImagickThumbnailer::class,
);
```

Kontrakt má dvě metody — `supports()` a `make()` — a platí pro ně stejné
pravidlo: odpovědět false, nikdy nevyhazovat výjimku.

## Editace obrázku

Ořez, srovnání, zmenšení. Obrázek se otevře z jeho panelu a všechno se děje
v canvasu v prohlížeči — stejným kódem, kterým souborové pole zmenšuje fotku
z mobilu, rozšířeným o rotaci, volný ořez a stupňovité zmenšování. Ne druhou
kopií.

**V prohlížeči, a je to rozhodnutí.** Serverový editor by zdědil jediné omezení
generátoru náhledů: `MakeThumbnail` to na disku, ze kterého neumí přečíst lokální
soubor, vzdává — takže stejně postavený editor by na S3 tiše neexistoval, což je
konfigurace, na které velká knihovna nejspíš běží. Prohlížeč čte URL a tu má
každý disk.

Uložení má **dva výsledky a dvě tlačítka**, nikdy ne checkbox, který mění, co
dělá jedno tlačítko:

| Tlačítko | Co udělá |
| --- | --- |
| Uložit jako nový soubor | Nový záznam, `derived_from_id` ukazuje na originál, ve stejné složce, s alt textem originálu. Originál zůstane beze změny |
| Nahradit originál | Stejný záznam, stejná cesta, nové bajty |

Panel ukazuje obě strany té vazby — z čeho byl soubor vyříznutý i co bylo
vyříznuto z něj — takže se ořez dá vystopovat zpátky, a ne jen podle názvu, který
někdo napsal. **Smazání originálu jeho ořezy nesmaže**; přijdou jen o ukazatel a
zůstanou.

### Přepis a co stojí

Přepis změní všechna použití souboru naráz, včetně těch, která knihovna
[nevidí](#kde-je-soubor-pouzity). Editor proto řekne, co se chystá rozbít — podle
počtu známých použití — a rovnou dodá, že to číslo je spodní hranice. **Vrátit to
nejde.**

Bajty se zapíšou **schválně na stejnou cestu**. Přesunout je by změnilo URL
souboru, na který už publikovaná stránka odkazuje — a to je přesně ten druh
použití, před kterým nejde varovat — takže zachovaná cesta je to, co dělá přepis
bezpečným pro použití, která nikdo nevyjmenuje. Stejná adresa s jiným obsahem je
to, co prohlížeč i CDN pokazí, takže `url()` nese `updated_at` záznamu:

```
/storage/media/2026/hero.jpg?v=1788766728
```

Všechno, co záznam o souboru tvrdí, se pak načte znovu z disku — velikost,
rozměry, kontrolní součet — a náhled se udělá znovu. Záznam, jehož kontrolní
součet pořád popisuje staré bajty, lže jako první detekci duplicit.

Přepis se ptá policy na vlastní oprávnění:

```php
public function replace(User $user, Media $media): bool
{
    return $user->isAdmin();
}
```

Policy, která `replace` nedefinuje, dostane otázku na `update`, takže knihovna
napsaná dřív, než editor vznikl, funguje přesně jako předtím.

## Práce s knihovnou odjinud

Modul médií, který je jen obrazovka, je jen obrazovka. Soubory dávají smysl ve
chvíli, kdy článek, produkt i rich text editor můžou ukazovat na **stejný
záznam** — jeden soubor, jedno URL, jeden popis, měněný jednou.

### Připojení souborů k jakémukoli záznamu

```php
use NyonCode\WireModuleMedia\Concerns\HasMedia;

class Post extends Model // [tl! focus:3]
{
    use HasMedia;
}

$post->attachMedia($cover, 'cover');   // pojmenovaná kolekce
$post->media('gallery');               // co je v ní
$post->syncMedia([$b, $a], 'gallery'); // přesně tyhle, v tomhle pořadí
```

Vazba je řádek ve `wire_mediables`, nikdy sloupec ve vaší tabulce — díky tomu
můžou dva záznamy sdílet jeden soubor, místo aby si každý držel vlastní cestu.
Kolekce (`cover`, `gallery`, `attachments`) jsou pojmenované sady, takže jeden
záznam unese hlavní obrázek i seznam příloh, aniž by o sobě věděly.

Odpojení zruší vazbu a soubor nechá být. Smazání *souboru* vezme vazby s sebou —
vazba na soubor, který už neexistuje, je rozbitý obrázek na stránce, o které si
nikdo nepamatuje, že ji publikoval.

### Kde je soubor použitý

`wire_mediables` je úplný záznam jednoho druhu použití. Připojení přes pole zapíše
řádek s vazbou a takový řádek se dá dohledat oběma směry.

Rich text editor žádný takový řádek nezapisoval. Uložil `<img src="…">` a nic
víc, takže fotka ve dvanácti článcích hlásila **nula** použití — a potvrzení před
každým smazáním stálo na té nule. Varování, které o souboru z titulní strany
tvrdí „nic ho nepoužívá“, není slabé varování; je to falešné „čisto“.

Editor si teď id nechává a model řekne, které jeho atributy nesou psaný obsah:

```php
use NyonCode\WireModuleMedia\Concerns\HasMedia;
use NyonCode\WireModuleMedia\Concerns\SyncsMediaUsage;

class Post extends Model
{
    use HasMedia;          // [tl! focus:4]
    use SyncsMediaUsage;

    protected array $mediaContent = ['body', 'perex'];
}
```

Při uložení se id z těch atributů přečtou a **sesynchronizují** — ne přidají — do
`wire_mediables` pod rezervovanou kolekci `__content`. Obrázek vyhozený z článku
si vazbu odnese s sebou, jinak počet, který jen roste, přestane cokoli znamenat
při prvním přepsání článku.

Je to stejná tabulka, do které zapisuje pole, takže „kde je tenhle soubor“ je
jeden dotaz a jeden model uvažování. Rezervovaný název je s podtržítky, aby se
nikdy nesrazil s vaší vlastní kolekcí, a `media('gallery')` nikdy nevrátí použití
z obsahu.

**To číslo je spodní hranice a každá obrazovka to říká.** URL ručně vloženou do
Blade šablony, seederu nebo e-mailové šablony tohle nevidí a nikdy neuvidí. Se
spodní hranicí se dá pracovat — „určitě se používá, možná víc“ — kdežto číslo
tvářící se jako úplné by se zneužilo přesně ve chvíli, kdy by na tom záleželo.

Panel souboru vypíše použití, o kterých ví, a prokliká na záznam tam, kde pro
jeho model existuje resource. Mazání **varuje** s tím počtem a neodmítá: vyřadit
soubor, jehož starý článek klidně může skončit s rozbitým obrázkem, je legitimní
přání, a odmítnutí by z knihovny udělalo něco, co nejde uklidit.

Články, které v databázi už jsou, žádné id nenesou, takže se jednorázově spárují
podle URL:

```bash
php artisan wire-module-media:usage --model="App\Models\Post"           # [tl! focus:2]
php artisan wire-module-media:usage --model="App\Models\Post" --dry-run
```

Nahlásí, kolik vazeb zapsal a napříč kolika záznamy, a kolik záznamů ukazovalo na
něco, co knihovna nemá. Ten rozdíl není chyba k vyšetřování — to jsou obrázky,
které v téhle knihovně nikdy nebyly.

### Pole do libovolného formuláře

```php
use NyonCode\WireModuleMedia\Forms\MediaField;

MediaField::make('cover'), // [tl! focus:5]

MediaField::make('gallery')
    ->multiple()
    ->collection('gallery')
    ->accepts('image/'),
```

**Ukládá se samo.** Název pole je kolekce, ne sloupec, takže implementuje
`SavesAfterRecord`: jeho hodnota se z dat vyjme před zápisem záznamu a zapíše se
do pivotu potom, až má záznam klíč. Model potřebuje `HasMedia` a nic dalšího —
žádný sloupec, žádnou migraci, žádnou `afterSave` closure, na kterou se
zapomíná.

### Rich text editor

`TiptapEditor::make('body')->withImages()` se dřív ptal na URL. S nainstalovaným
modulem médií jeho tlačítko pro obrázek otevře knihovnu a alternativní text se
vrátí spolu se souborem.

Nic se kvůli tomu nenastavuje a ani nemohlo: `wire-forms` je pod tímhle balíčkem
a nikdy ho nesmí vyžadovat. Editor tedy práci *nabídne* jako zrušitelnou DOM
událost a ten, kdo ji obslouží, si ji nárokuje:

```js
const request = new CustomEvent('wire-media-picker:open', { // [tl! focus:6]
    cancelable: true,
    detail: { token: 'neco-unikatniho', multiple: false, accepts: 'image/' },
});

window.dispatchEvent(request);

if (! request.defaultPrevented) { /* nikdo neposlouchá — udělejte, co jste dělali dřív */ }
```

Odpověď přijde na `wire-media-picker:picked` i s tokenem, se kterým se otevíralo,
takže si dva výběry na jedné stránce nemůžou zkřížit odpovědi. Váš vlastní kód
může knihovnu otevřít stejně.

### Odkud se bere modal

Výběr musí být na každé stránce a shell, který každou stránku vykresluje
(`wire-admin`), je *nad* tímhle modulem a nikdy o něm neslyšel. Modul si tedy
zaregistruje view a shell vykreslí, co je zaregistrované:

```php
app(NyonCode\WireCore\Foundation\View\PageChrome::class)
    ->add('wire-module-media::picker-modal');
```

Aplikace, která si kreslí vlastní layout místo shellu, si přidá stejný cyklus, a
ta, která nekreslí ani jedno, prostě výběr nemá — tlačítko to řekne, místo aby se
stránka rozbila.

### Jeden soubor, jednou

Knihovna odmítne druhou kopii souboru, který už má. sha-256 uložených bajtů se
porovná s tím, co v knihovně je, a nahrání souboru, který tam už je, vrátí
existující záznam a kopii, kterou právě vytvořilo, smaže. Stejná fotka nahraná ze
tří obrazovek má být jeden záznam s jedním URL a jedním popisem, jinak se každé
její použití rozejde.

Kontrolní součty se počítají jen na discích, za kterými jsou skutečné soubory. Z
S3 se soubor kvůli hashi nestahuje zpátky — detekce duplicit je pohodlí, a
pohodlí, které stahuje každé nahrání dvakrát, pohodlí není.

## Co dostanete

| Obrazovka | Poznámky |
| --- | --- |
| Média | Správce souborů: strom složek, mřížka nebo seznam, drobečky, hledání přes celou knihovnu |
| Nahrání | Přetáhněte soubory na plochu, nebo je vyberte — disk, adresář, povolené typy a limit jsou z configu |
| Jeden soubor | Jen ke čtení, v hlavičce název souboru: náhled ve velikosti, ve které je něco vidět, pak co soubor je (typ, velikost, rozměry, složka, datum nahrání), co k němu někdo napsal (alt text a titulek) a — sbalené — disk a cesta, kde leží. Otevřít a Stáhnout jsou v hlavičce náhledu, jako odkazy: neveřejný disk jde přes streamovanou routu modulu a soubor, který nemá adresu vůbec, nenabídne ani jedno |

## Související

- [Nahrávání souborů](../forms/fields/file-upload.md) — pole, které se tu používá
- [Moduly](../panels/modules.md) — jak balíček dodává takovou oblast

