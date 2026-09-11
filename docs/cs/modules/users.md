---
order: 20
summary: Hotová správa uživatelů instalovaná jako balíček — resource uživatelů, jeho stránky a správa rolí všude, kde aplikace role má.
---

# Modul uživatelů

[Modul](../panels/modules.md), který přichází jako composer balíček: nainstalujete ho
a aplikace má správu uživatelů, aniž byste kamkoli vypsali třídu nebo sáhli do
configu.

```bash
composer require nyoncode/wire-module-users
php artisan wire-module-users:install
```

Zároveň je to referenční implementace té cesty — samoregistrace přes plugin
manager, manifest, který jmenuje své resources, a vlastní skupina v menu.

## Jak to funguje

**Nad vaším modelem uživatele, nikdy nad vlastním.** Balíček, který by si přinesl
vlastní tabulku uživatelů, by byl nepoužitelný v každé aplikaci, která ji už má —
tedy ve všech. `config('wire-module-users.model')` míří na `App\Models\User`
a tři sloupce, kterých se modul dotýká (`name`, `email`, `password`), jsou taky
konfigurace, protože `users` je ta jediná tabulka, kterou každá aplikace změnila.

**Heslo je část, kterou je potřeba ošetřit, ne jen zobrazit.** Hash se ze stavu
formuláře odstraňuje, místo aby se spoléhalo na to, že je skrytý — model aplikace
bez `$hidden` by ho jinak poslal do Livewire snapshotu a při dalším uložení znovu
zahashoval. Prázdné pole znamená *nechat současné heslo*; vyplněné se cestou do
databáze zahashuje.

**Role se objeví jen tam, kde jsou.** S nainstalovaným
`nyoncode/laravel-permission-extended` **a** s modelem uživatele, který nese
**jeho** trait `HasRoles`, modul zaregistruje resource rolí a přidá pole rolí do
formuláře uživatele. Kontrolují se obě podmínky: balíček může být nainstalovaný,
zatímco model trait nikdy nevzal — a každé uložení rolí by pak spadlo na
posledním kroku.

Tenhle balíček je permission vrstva celého stacku — ne alternativa ke
`spatie/laravel-permission`, který vyžaduje a rozšiřuje, ale wildcard matching,
super-admin gate a události o změně oprávnění, které tyhle obrazovky
předpokládají. Model na holém Spatie se záměrně nedetekuje; `'roles' => true`
ten pohled přebije.

**Oprávnění jsou zaškrtávací seznam, ne multi-select.** Ty dvě věci odpovídají na
různé otázky: multi-select ukazuje, co jste *vybrali*, a zbytek schová — což je
správně pro dvě tři role u uživatele. U oprávnění je to naopak: otázka zní vždycky
„co tam ještě je“, nad stovkou názvů, které si nikdo nepamatuje. Takže na stránce
je každé oprávnění, s vyhledávacím polem, dvojicí Vybrat vše / Zrušit výběr
a **skupinami podle resource, o kterém oprávnění je** — podle úseku před první
tečkou (`invoices.view`, `invoices.*` → *invoices*).

Seskupí se jen tam, kde to něco oddělí. Jedna skupina je tentýž seznam s nadpisem
nad ním a aplikace, jejíž oprávnění nejsou s tečkou — `view invoices`, ta druhá
běžná konvence — by dostala jednu skupinu na každé oprávnění; obojí se proto
vykreslí naplocho. Název bez resource se nezahodí: sesbírá se pod jeden nadpis,
protože oprávnění, které ve formuláři chybí, je oprávnění, které nikdo nemůže
udělit.

**Role se zapisují až po vzniku záznamu.** Žijí v pivot tabulce, takže se z dat
cestou k `save()` odeberou a synchronizují se potom — jediné pořadí, které
funguje pro *nového* uživatele, který do té chvíle nemá id.

**Autorizace nikdy nepatří tomuhle modulu.** Každá kontrola v tomhle stacku je
`Gate::allows()`, do kterého se oba permission balíčky registrují samy, takže
wildcard (`invoices.*`) i super-admin bypass fungují, aniž by o nich modul věděl.
Modul spravuje data; kdo je smí spravovat, je vaše policy.

**Všechno volitelné se detekuje, nepředpokládá.** Role, avatary, dvoufázové
ověření i týmy mají nastavení `auto`, které hledá tu věc samotnou, ne příznak,
který jste si vzpomněli nastavit — třídu role *a* trait na vašem modelu, sloupec
v tabulce uživatelů, Fortify se zapnutou funkcí, `permission.teams`. Kde ta věc
chybí, chybí i plocha — místo aby tam byla a nefungovala. `php artisan about`
jmenuje všechny čtyři a instalátor taky, protože nahrávání fotky, které se nikdy
neobjeví kvůli chybějícímu sloupci, je k nerozeznání od rozbitého balíčku, dokud
to někdo neřekne.

**Avatar je sloupec, který vlastníte vy.** `wire-module-users.avatar.column`
(ve výchozím stavu `avatar_path`) drží cestu na disku
`wire-module-users.avatar.disk` a shell ji kreslí přes kontrakt `HasAvatar`
z `wire-core` — nikdy ne přes tenhle balíček. Horní lišta tak ukáže tvář, aniž by
věděla, odkud je, a aplikace, která řeší Gravatar nebo identity providera,
implementuje stejné rozhraní, místo aby brala trait tohohle modulu. Uložená
hodnota, která *už* je URL, projde beze změny — a právě to tohle umožňuje.

**Heslo se z profilu odstěhovalo.** Má vlastní kartu, která se nejdřív zeptá na
současné heslo. Pole s poznámkou „nechte prázdné pro zachování hesla“ je ovládací
prvek administrátora — a na stránce profilu je administrátorem sám držitel účtu,
takže obě obrazovky ho nemůžou sdílet. Admin, který edituje někoho jiného, žádné
současné heslo nemá. V tom je celý rozdíl.

## Konfigurace

```php
// config/wire-module-users.php
'model' => 'App\Models\User',

'fields' => [
    'name' => 'name',
    'email' => 'email',
    'password' => 'password',
],

'roles' => 'auto',        // 'auto' se podívá, true a false odpoví za vás [tl! focus]

'avatar' => [                                    // [tl! focus:start]
    'enabled' => 'auto',   // 'auto' hledá sloupec níž
    'column' => 'avatar_path',
    'disk' => 'public',
    'directory' => 'avatars',
],

'profile' => [
    'password' => true,
    'two_factor' => true,
    'passkeys' => true,
    'delete_account' => false,   // ve výchozím stavu vypnuté — viz níž
    'menu_item' => true,         // odkaz na profil v uživatelském menu shellu
],

'two_factor' => 'auto',   // 'auto' hledá Fortify se zapnutou funkcí
'passkeys' => 'auto',     // 'auto' hledá laravel/passkeys se zapnutou funkcí

'teams' => [
    'enabled' => 'auto',   // 'auto' se řídí permission.teams
    'model' => 'App\\Models\\Team',
    'relation' => 'teams',
    'label_attribute' => 'name',
    'session_key' => 'wire.team',
],                                               // [tl! focus:end]

'navigation' => [
    'group' => 'access',
    'label' => null,   // null použije vlastní nadpis skupiny modulu
    'icon' => 'outline:users',
    'sort' => 90,
],
```

Všechno v zvýrazněném bloku je volitelné a každé `auto` výš umí čistě odpovědět
„ne“. Aplikace, která nenastaví nic z toho, dostane správu uživatelů, jakou měla.

## Co dostanete

| Obrazovka | Poznámky |
| --- | --- |
| Seznam uživatelů | Jméno a e-mail k hledání i řazení, fotka tam, kde je, sloupec rolí tam, kde role jsou. Detail, Upravit a Smazat na řádku; Nový uživatel v toolbaru |
| Nový uživatel | Heslo jednou povinně, role se přiřadí při uložení |
| Editace uživatele | Heslo volitelně — prázdné nechá současné; role načtené ze záznamu |
| Detail uživatele | Jen ke čtení, v hlavičce jméno člověka: fotka, jméno, e-mail a role, které má |
| Seznam, nová a editace rolí | Jen tam, kde role jsou. Detail, Upravit a Smazat na řádku; oprávnění jsou zaškrtávací seznam s vyhledáváním, seskupený podle resource, a editují se jako názvy, takže wildcard je prostě název |
| Detail role | Jen ke čtení, v hlavičce název role. Nahoře co role *je*, pod tím co *uděluje* — a tam, kde permission vrstva matchuje wildcardy, je `invoices.*` vypsaný zvlášť od oprávnění, která pokrývá |

**Každé tlačítko se řídí tím, co aplikace naroutovala.** Řádková akce je skrytá
tam, kde její stránka naroutovaná není — místo odkazu, který skončí na 404.
Aplikace může namountovat jen seznam a nic dalšího, a poctivá odpověď na „žádná
editační stránka není“ je žádné Upravit. Tam, kde naroutovaná *je* i s
oprávněním (`RoutePage::make(EditUser::class)->permission('users.update')`), si
tlačítko přečte ability z téže deklarace — skryté tlačítko a hlídaná routa se
tak nemůžou rozejít.

**Sám sebe ze seznamu uživatelů smazat nemůžete.** Není to zdvořilost:
administrátor, který odstraní vlastní řádek, se uprostřed requestu odhlásí do
aplikace, kam už se nedostane — a pokud byl jediný, nedostane se tam nikdo.
Zavření vlastního účtu je věc [stránky profilu](#vlastni-ucet), kde se ptá
dvakrát a chce heslo.

## Kdo Se K Těmto Obrazovkám Dostane

**Tyto obrazovky vyžadují oprávnění, a to je ve 2.0 nové.** Formulář pro úpravu
uživatele nastavuje ostatním lidem hesla a přiděluje jim role, takže obrazovka
otevřená komukoli, koho pustil `auth` middleware panelu, je obrazovka, která
z libovolného účtu udělá správce. Před 2.0 se dodávaly otevřené a selhání bylo
tiché — panel vypadal správně, zatímco podával správu uživatelů každému, kdo se
dokázal přihlásit.

Nově tedy selhávají zavřeně. Každá obrazovka pojmenuje oprávnění, to se stane
Laravelím vlastním `can:` middleware na route, a tlačítko, které tam vede, se
skryje podle téže deklarace:

```php
// config/wire-module-users.php — výchozí hodnoty [tl! focus:start]
'permissions' => [
    'users' => [
        'viewAny' => 'users.viewAny',
        'view' => 'users.view',
        'create' => 'users.create',
        'update' => 'users.update',
    ],

    'roles' => [
        'viewAny' => 'roles.viewAny',
        'view' => 'roles.view',
        'create' => 'roles.create',
        'update' => 'roles.update',
    ],
], // [tl! focus:end]
```

Nic tady autorizační kontrolu neimplementuje znovu — na každou z nich odpovídá
`Gate`, a právě proto funguje zástupný znak (`users.*`), policy i obejití pro
super-admina z `nyoncode/laravel-permission-extended`, aniž by o nich tento modul
věděl. Na tom balíčku super-admin projde vždy.

**Na instalaci, kde žádné takové oprávnění definované není, tyto obrazovky
odpoví 403.** To je záměr: viditelný problém se zřejmou opravou je lepší než
tichý. Definujte oprávnění, přidělte je roli, nebo obrazovku znovu otevřete
pomocí `null`:

```php
'permissions' => [
    'users' => [
        'viewAny' => 'users.viewAny',
        'view' => null,              // otevřené komukoli, koho panel pustil [tl! focus]
        'create' => 'users.create',
        'update' => 'users.update',
    ],
],
```

Prázdný řetězec se počítá jako `null`, ne jako oprávnění, které nikdo nemůže
mít — takový tvar vyrobí nenastavené `.env` a `can:` nad ním by odepřelo přístup
všem bez možnosti ho udělit.

**Profilová stránka na tomto seznamu záměrně není.** Je to vlastní účet
přihlášeného člověka, získaný z `Auth::user()` a ne z URL, takže administrátorské
oprávnění před ní by každého uživatele zamklo od jeho vlastního hesla a nastavení
dvoufázového ověření.

`php artisan about` hlásí, jak je to na této instalaci nastavené, a installer to
řekne při instalaci — instalace, která tyto obrazovky otevřela, by to měla mít
možnost vidět.

### Role Se Kontrolují I Tam, Kde Se Zapisují

Route guard není jediný zámek. Přidělení role prochází druhou kontrolou přímo
u zápisu do pivot tabulky, protože cest k formuláři je mnoho a ne všechny jsou
route — hromadná akce, krok průvodce, vlastní stránka aplikace skládající
`SyncsRoles`. Uložení, které role **mění**, vyžaduje oprávnění `users.update`;
uložení, které je nechává být, ne — takže oprava překlepu ve jméně nikdy nikomu
role nesebere.

### Změněná adresa přestane být ověřená

Fortifyho `UpdateUserProfileInformation` při úpravě adresy vynuluje
`email_verified_at` a pošle nové ověření. Tenhle modul tu akci nahrazuje vlastním
formulářem, takže to pravidlo zopakuje, místo aby ho ztratil — jinak by si člověk
mohl napsat adresu, kterou nevlastní, a zůstat na ní označený jako ověřený.
Cokoli za Laravelím middleware `verified` nebo jakákoli policy ptající se
`hasVerifiedEmail()` by pak platily pro adresu, kterou nikdo nedoložil.

Pravidlo žije na **modelu**, ne ve form hooku, a to záměrně: ten sloupec tu
zapisují tři obrazovky — profil, administrátorská úprava, vytvoření — a
`Form::afterSave()` drží přesně jednu closure, takže pravidlo umístěné tam by
příští stránka, která si přidá hook, tiše odstranila. Vlastní stránka aplikace
nebo konzolový příkaz by pokryté nebyly nikdy.

Je úzké. Spustí se jen když se adresa opravdu změnila, a ustoupí vždy, když
tentýž zápis nastavuje i `email_verified_at` — seeder, doplnění dat v migraci
nebo administrátorský nástroj označující adresu za ověřenou řekly, co chtějí:

```php
// Respektováno: volající měl názor.
$user->forceFill(['email' => $new, 'email_verified_at' => now()])->save();

// Vynulováno, a odejde nové ověření.
$user->forceFill(['email' => $new])->save();
```

Vypněte tam, kde si aplikace ten příznak řeší po svém:

```php
// config/wire-module-users.php
'reverify_on_email_change' => false, // [tl! focus]
```

## Úpravy obrazovek

`fields` mapuje tři **jména sloupců**, nic víc. Je tu pro tabulku uživatelů,
jejíž sloupce se jmenovaly dřív, než tenhle modul přišel — odpovídá na otázku
„ve kterém sloupci je jméno", nikdy na „z kolika polí se jméno skládá":

```php
'fields' => ['name' => 'full_name', 'email' => 'login', 'password' => 'password'],
```

Všechno ostatní na seznamu, ve formuláři i na detailu se upravuje přes
[hooky](../core/plugins/hooks.md#zuzeni-hooku-na-jednu-komponentu) zúžené na
klíč, pod kterým se modul zaregistroval — `users`. **Dědění z `UserResource`
nefunguje**: potomek si nechá klíč rodiče a registr odmítne dvě třídy na jednom
klíči — a přesně to dělá z modulu něco upravitelného místo
[něčeho, co se forkuje](../panels/modules.md#balicek-pridava-neprepisuje).

### Křestní jméno a příjmení ve dvou sloupcích

Případ, který konfigurace neumí vyjádřit a hooky ano. Čtyři kroky, a jen ten
poslední má s tímhle modulem vůbec co do činění.

**1. Sloupce.** Obyčejná migrace; `name` si nechte nebo zahoďte, jak chcete —
po kroku 2 ho jako sloupec nikdo nečte:

```php
Schema::table('users', function (Blueprint $table): void {
    $table->string('first_name')->after('id')->default('');        // [tl! focus:start]
    $table->string('last_name')->after('first_name')->default('');  // [tl! focus:end]
});

// Naplňte data dřív, než něco zahodíte: půlky jména se ze sloupce, který už
// není, zpátky nedostanou.
DB::table('users')->orderBy('id')->each(function (object $user): void {
    [$first, $last] = array_pad(explode(' ', (string) $user->name, 2), 2, '');

    DB::table('users')->where('id', $user->id)->update([
        'first_name' => $first,
        'last_name' => $last,
    ]);
});
```

**2. Model.** Oba sloupce fillable a accessor, aby všechno, co jméno jen
*zobrazuje*, fungovalo dál — roh shellu, iniciály v avataru i sloupec aktéra
v audit logu čtou `$user->name` a je jim jedno, odkud se vzalo:

```php
// app/Models/User.php
use Illuminate\Database\Eloquent\Casts\Attribute;

protected $fillable = ['first_name', 'last_name', 'email', 'password'];

protected function name(): Attribute                                      // [tl! focus:start]
{
    return Attribute::get(fn (): string => trim($this->first_name.' '.$this->last_name));
}                                                                         // [tl! focus:end]
```

**3. Řádek konfigurace.** Nasměrujte `fields.name` na sloupec, podle kterého má
seznam řadit — na skutečný sloupec, protože `sortable()` a výchozí řazení se
promění v SQL a accessor sloupec není:

```php
// config/wire-module-users.php
'fields' => ['name' => 'last_name', 'email' => 'email', 'password' => 'password'],
```

**4. Obrazovky**, a tady přicházejí na řadu hooky:

```php
namespace App\Wire\Plugins;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\Hooks\FormConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\InfolistConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\TableComposingPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireForms\Components\Field;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WireTable\Columns\TextColumn;

final class SplitUserName implements Plugin
{
    public function getId(): string
    {
        return 'split-user-name';
    }

    public function register(PluginManager $manager): void
    {
        $manager->hook(Hook::FormConfiguring, function (FormConfiguringPayload $payload): FormConfiguringPayload {   // [tl! focus:start]
            $payload->schema = $this->splitName($payload->schema);

            return $payload;
        }, for: 'users');                                                                                            // [tl! focus:end]

        $manager->hook(Hook::TableComposing, function (TableComposingPayload $payload): TableComposingPayload {
            $payload->columns = array_map(
                fn (object $column): object => $column->getName() === UserResource::field('name')
                    // Jeden sloupec a obě půlky v něm: hledání čte oba sloupce,  [tl! focus:start]
                    // řazení zůstane na tom skutečném pod ním.
                    ? TextColumn::make(UserResource::field('name'))
                        ->label(__('Jméno'))
                        ->state(fn (Model $record): string => trim($record->first_name.' '.$record->last_name))
                        ->searchable(['first_name', 'last_name'])
                        ->sortable()                                            // [tl! focus:end]
                    : $column,
                $payload->columns,
            );

            return $payload;
        }, for: 'users');
    }

    public function boot(PluginManager $manager): void {}

    /**
     * Schéma modulu s jedním inputem na jméno nahrazeným dvěma.
     *
     * Rekurzivně, protože resource skládá pole do sekcí — ze stejného důvodu,
     * z jakého je rekurzivní i filtr profilové stránky.
     *
     * @param  array<int, mixed>  $schema
     * @return array<int, mixed>
     */
    private function splitName(array $schema): array
    {
        $name = UserResource::field('name');
        $out = [];

        foreach ($schema as $component) {
            if ($component instanceof LayoutComponent) {
                $out[] = $component->schema($this->splitName($component->getSchema()));

                continue;
            }

            if ($component instanceof Field && $component->getName() === $name) {
                $out[] = TextInput::make('first_name')->label(__('Křestní jméno'))->required();   // [tl! focus:start]
                $out[] = TextInput::make('last_name')->label(__('Příjmení'))->required();         // [tl! focus:end]

                continue;
            }

            $out[] = $component;
        }

        return $out;
    }
}
```

Zaregistrovaný tak, jak se registruje každý plugin aplikace:

```php
// config/wire-core.php
'plugins' => [
    App\Wire\Plugins\SplitUserName::class,
],
```

Detail je tytéž tři řádky se třetím hookem a tady odvede práci accessor
z kroku 2 — infolist jen zobrazuje, takže se nic nehledá ani neřadí:

```php
$manager->hook(Hook::InfolistConfiguring, function (InfolistConfiguringPayload $payload): InfolistConfiguringPayload {
    $payload->schema = array_map(
        fn (object $entry): object => $entry->getName() === UserResource::field('name')
            ? TextEntry::make('name')->label(__('Jméno'))   // accessor, ne sloupec [tl! focus]
            : $entry,
        $payload->schema,
    );

    return $payload;
}, for: 'users');
```

Tři věci stojí za to vědět, než to spustíte:

- **`Hook::FormConfiguring` dosáhne i na profilovou stránku.** [Vlastní
  účet](#vlastni-ucet) skládá tentýž formulář resourcu a jen z něj dvě pole
  vyndá, takže obě obrazovky dostanou dvojici z jednoho callbacku.
  `Hook::ExportConfiguring` udělá totéž pro obsah stažení.
- **Samotný accessor by nestačil.** Formulář i detail naplní naprosto v pořádku
  a pak se `searchable()` a `defaultSort()` seznamu zeptají databáze na sloupec,
  který neexistuje — proto krok 3 nasměruje modul na skutečný.
- **Fortify ani balíčky na oprávnění jméno nevidí**, takže zbytek stacku nemá na
  počet sloupců názor.

Texty na těchhle obrazovkách jsou publikovatelný překladový soubor a jejich
markup publikovatelný pohled — `wire-module-users::translations` a `…::views`;
co to stojí, říká [Vzhled → Lokalizace](../start/theming.md#lokalizace) a
[Přepis pohledů](../start/theming.md#prepis-pohledu).

## Vlastní účet

`EditProfile` je stránka přihlášeného uživatele o něm samotném — a není to jeden
formulář. Každá karta pod tou první je samostatná Livewire komponenta: jiná
otázka, jiné tlačítko, jiná věc, která může selhat.

Není to preference rozvržení. Jeden formulář se třemi sekcemi musí umět
vysvětlit, co se stalo, když neprojde ta prostřední — a *„jméno se uložilo, heslo
ne“* není zpráva, kterou by stránka profilu měla kdy vyprodukovat.

| Karta | Komponenta | Zobrazí se, když |
| --- | --- | --- |
| Údaje o profilu | samotný `EditProfile` | vždycky — formulář resource bez rolí a hesla |
| Změna hesla | `UpdatePassword` | `profile.password` |
| Dvoufázové ověření | `TwoFactorAuthentication` | `profile.two_factor` **a** je nainstalovaný Fortify |
| Passkeys | `PasskeyManagement` | `profile.passkeys` **a** Fortify routuje `Features::passkeys()` |
| Smazání účtu | `DeleteAccount` | `profile.delete_account` — **ve výchozím stavu vypnuté** |

**Záznamem je přihlášený uživatel, nikdy parametr routy.** Stránka profilu, která
by brala id, by byla editace s přívětivějším jménem — a při prvním přepsání čísla
v URL převzetí cizího účtu.

**Role jsou ze schématu odstraněné**, ne jen ignorované při uložení. Kdo edituje
vlastní účet, se nesmí sám přidat do role, a pole, které se vykreslí a pak zahodí,
je jeden refaktor od pole, které se vykreslí a pak respektuje.

**Změna hesla vás nechá přihlášené.** Laravelí middleware `AuthenticateSession`
porovnává kopii hashe hesla v session s tou uživatelovou při každém requestu,
takže změna, která tu kopii neposune, vás odhlásí hned při dalším kliknutí — a to
právě v aplikacích, které ten middleware zapínají, tedy v těch, kterým na tom
záleží nejvíc. Karta ji posune.

**Smazání vlastního účtu je ve výchozím stavu vypnuté**, a je to rozhodnutí, ne
opatrnost: v administraci je člověk na téhle stránce většinou zaměstnanec
a administrátor, který se dvěma kliknutími odstraní, je budoucí ticket na
podporu. Tam, kde jsou účty samoobslužné, to zapněte. Ptá se dvakrát — dialog,
který je potřeba otevřít, a heslo účtu napsané do něj — a co „smazat“ znamená,
zůstává vašemu modelu, takže tabulka `users` se soft delete soft-deletuje.

Routuje se se vším ostatním — `Route::wireResources()` mu dá
`{prefix}/users/profile` pod jménem `wire.users.profile` — a v `pages()` je
deklarovaná **před** `view` schválně: neznámý klíč stránky routuje na
`{prefix}/{name}`, takže `users/profile` a `users/{record}` mají stejný tvar URL
a deklarovaná až za ním by se „profile“ hledalo jako klíč uživatele a skončilo
404.

### Karta na vlastní stránce

Každá z nich je obyčejná Livewire komponenta bez argumentů, protože záznam, na
kterém pracuje, je vždycky ten přihlášený. Aplikace, která chce kartu hesla na
vlastní stránce nastavení, ji tam dá a tady ji vypne:

```php
// config/wire-module-users.php
'profile' => [
    'password' => false,   // [tl! focus]
],
```

```blade
{{-- resources/views/settings/security.blade.php --}}
<x-wire-admin::layout title="Zabezpečení">
    <div class="mx-auto max-w-3xl space-y-4">
        @livewire(\NyonCode\WireModuleUsers\Livewire\UpdatePassword::class)      {{-- [tl! focus:start] --}}
        @livewire(\NyonCode\WireModuleUsers\Livewire\TwoFactorAuthentication::class) {{-- [tl! focus:end] --}}
    </div>
</x-wire-admin::layout>
```

## Fotka pro vaše uživatele

Přidejte do vlastní tabulky uživatelů nullable string sloupec a nahrávání se
objeví — v tom je celá detekce `auto`:

```php
// database/migrations/…_add_avatar_to_users_table.php
Schema::table('users', function (Blueprint $table): void {
    $table->string('avatar_path')->nullable();   // [tl! focus]
});
```

Pak modelu řekněte, že má tvář. Kontrakt patří `wire-core` a jeho čtení tomuhle
modulu — a právě tenhle rozdělený vztah nechá shell kreslit avatar, aniž by věděl,
že tenhle balíček existuje:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use NyonCode\WireCore\Foundation\Contracts\HasAvatar;                 // [tl! focus:start]
use NyonCode\WireModuleUsers\Concerns\InteractsWithAvatar;

class User extends Authenticatable implements HasAvatar
{
    use InteractsWithAvatar;                                            // [tl! focus:end]

    protected $fillable = ['name', 'email', 'password', 'avatar_path'];
}
```

To stačí na tři plochy najednou: kulaté nahrávání na profilu, tvář v seznamu
uživatelů a fotku v horní liště místo iniciály.

**Vlastní odpověď místo toho.** Implementujte `getAvatarUrl()` sami a trait
vynechte — Gravatar, identity provider, druhá tabulka. Chrome se ptá rozhraní:

```php
class User extends Authenticatable implements HasAvatar
{
    public function getAvatarUrl(): ?string                              // [tl! focus:start]
    {
        return 'https://www.gravatar.com/avatar/'.md5(strtolower($this->email));
    }                                                                    // [tl! focus:end]
}
```

Vrátit `null` je platná odpověď, ne selhání: tak vypadá člověk, který nic
nenahrál, a každá plocha spadne zpátky na jeho iniciálu.

## Související

- [Moduly](../panels/modules.md) — co modul je a jak ho balíček dodává
- [Admin shell](../admin/overview.md) — rám, ve kterém se tyhle stránky vykreslují
- [Resources](../panels/resources.md) — kontrakty, které resources modulu implementují
