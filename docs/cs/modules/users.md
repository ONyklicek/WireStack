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
„co tam ještě je", nad stovkou názvů, které si nikdo nepamatuje. Takže na stránce
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
současné heslo. Pole s poznámkou „nechte prázdné pro zachování hesla" je ovládací
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
    'delete_account' => false,   // ve výchozím stavu vypnuté — viz níž
],

'two_factor' => 'auto',   // 'auto' hledá Fortify se zapnutou funkcí

'teams' => [
    'enabled' => 'auto',   // 'auto' se řídí permission.teams
    'model' => 'App\\Models\\Team',
    'relation' => 'teams',
    'label_attribute' => 'name',
    'session_key' => 'wire.team',
],                                               // [tl! focus:end]

'navigation' => [
    'group' => 'access',
    'icon' => 'outline:users',
    'sort' => 90,
],
```

Všechno v zvýrazněném bloku je volitelné a každé `auto` výš umí čistě odpovědět
„ne". Aplikace, která nenastaví nic z toho, dostane správu uživatelů, jakou měla.

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
editační stránka není" je žádné Upravit. Tam, kde naroutovaná *je* i s
oprávněním (`RoutePage::make(EditUser::class)->permission('users.update')`), si
tlačítko přečte ability z téže deklarace — skryté tlačítko a hlídaná routa se
tak nemůžou rozejít.

**Sám sebe ze seznamu uživatelů smazat nemůžete.** Není to zdvořilost:
administrátor, který odstraní vlastní řádek, se uprostřed requestu odhlásí do
aplikace, kam už se nedostane — a pokud byl jediný, nedostane se tam nikdo.
Zavření vlastního účtu je věc [stránky profilu](#vlastni-ucet), kde se ptá
dvakrát a chce heslo.

## Vlastní účet

`EditProfile` je stránka přihlášeného uživatele o něm samotném — a není to jeden
formulář. Každá karta pod tou první je samostatná Livewire komponenta: jiná
otázka, jiné tlačítko, jiná věc, která může selhat.

Není to preference rozvržení. Jeden formulář se třemi sekcemi musí umět
vysvětlit, co se stalo, když neprojde ta prostřední — a *„jméno se uložilo, heslo
ne"* není zpráva, kterou by stránka profilu měla kdy vyprodukovat.

| Karta | Komponenta | Zobrazí se, když |
| --- | --- | --- |
| Údaje o profilu | samotný `EditProfile` | vždycky — formulář resource bez rolí a hesla |
| Změna hesla | `UpdatePassword` | `profile.password` |
| Dvoufázové ověření | `TwoFactorAuthentication` | `profile.two_factor` **a** je nainstalovaný Fortify |
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
který je potřeba otevřít, a heslo účtu napsané do něj — a co „smazat" znamená,
zůstává vašemu modelu, takže tabulka `users` se soft delete soft-deletuje.

Routuje se se vším ostatním — `Route::wireResources()` mu dá
`{prefix}/users/profile` pod jménem `wire.users.profile` — a v `pages()` je
deklarovaná **před** `view` schválně: neznámý klíč stránky routuje na
`{prefix}/{name}`, takže `users/profile` a `users/{record}` mají stejný tvar URL
a deklarovaná až za ním by se „profile" hledalo jako klíč uživatele a skončilo
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
