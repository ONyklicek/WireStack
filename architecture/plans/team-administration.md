# Team administration — odsouhlasený plán

Vlastnící balíček: **wire-module-users** (obrazovky, `Support\Teams`,
`Support\Accounts`). Mechanismus pod tím zůstává
**nyoncode/laravel-permission-extended** nad Spatie teams — žádný druhý systém
oprávnění.

Stav: **odsouhlaseno 2026-09-14** (rozhodnutí v §7). Hotové kroky §6: **0**
(permission-extended `hasGlobalPermission()`) a **1** (obrazovka uživatelů
omezená na tým: seznam, detail, úprava, akce řádků; nový účet správce týmu se
přidá do týmu) a **2** (obrazovka rolí omezená na tým — globální role jen ke
čtení, role týmu ke změně; role super-admin se v UI nemění nikdy, role
`admin` jen super-adminem; nová role správce týmu patří jeho týmu) a **3**
(`Support\RoleGrants`: nikdo nerozdá víc, než sám má — formuláře nabízejí jen
to, a uložení se zúží, neodmítne) a **4** (`team-admin` a `admin` vznikají při
prvním přidělení s oprávněními obrazovek; `wire:assign-role --global`). Oprávnění
`teams.*` z O7 zatím nejsou, protože modul žádnou obrazovku týmů nemá. Zbytek
zatím není implementovaný.
Hotové a pod nimi dostupné:

- permission-extended 1.1 (větev `global-roles`): `assignGlobalRole()`,
  `hasGlobalRole()`, brána super-admina jen pro globální přiřazení, a
  **oprávnění globálních rolí platí ve všech týmech** (`can()`, middleware
  `permission:`, wildcardy);
- wire: `wire:assign-role --super-admin`, instalátor s potvrzením, super-admin
  mimo formuláře.

---

## 1. Problém

Se zapnutými týmy dnes platí:

| Co | Jak se to chová | Proč je to špatně |
|---|---|---|
| `UserResource` | vypisuje **všechny** uživatele aplikace | kdo má `users.viewAny` v týmu A, vidí i uživatele týmu B |
| `RoleResource` | vypisuje **všechny** role, globální i cizích týmů | správce týmu A vidí a edituje role týmu B |
| role „admin týmu" | neexistuje | jediná správa je super-admin, a ten má být výjimka |
| oprávnění `users.*` / `roles.*` | kontrolovaná per tým (Spatie), ale **data** obrazovek per tým nejsou | Gate řekne „ano v týmu A", dotaz vrátí data celé aplikace |
| role super-admin v `RoleResource` | lze přejmenovat / smazat / dát jí oprávnění | přejmenováním přestane fungovat brána — tichá ztráta přístupu |

Jádro: **autorizace je per tým, ale dotazy obrazovek ne.** To je bezpečnostní
díra, ne chybějící feature.

## 2. Cílový model

```text
super-admin (globální)        obchází všechno; jen CLI; spravuje i ostatní super-adminy
admin (globální)              explicitní oprávnění, platná ve všech týmech; nikdy nad super-adminem
  └─ tým
       ├─ admin týmu          spravuje členy a role SVÉHO týmu
       ├─ role týmu           (editor, support, …) definované týmem nebo z globální šablony
       └─ člen                jen své role v rámci týmu
```

**Super-admin vs. admin:** super-admin nemá seznam oprávnění — brána ho pustí
všude a nedá se mu nic odebrat. Admin je obyčejná role s oprávněními, jen
přiřazená globálně: může, co role nese, ve všech týmech, a odebráním oprávnění
z role o něj přijde. Admin je tedy „správce celé aplikace", super-admin
„majitel instalace".

### 2.1 Role

- **Globální role** (`roles.team_id = NULL`) — šablony dostupné ve všech týmech
  (Spatie je tak najde odkudkoli). Spravuje je super-admin a globální admin —
  ten ale nikdy role `admin` a `super-admin`.
- **Role týmu** (`roles.team_id = <tým>`) — vytvořené v týmu, viditelné jen
  v něm. Spravuje je admin týmu.
- **Admin (globální)** — konfigurovatelná globální role
  (`wire-module-users.admin_role`, výchozí `admin`), přiřazovaná **globálně**
  (`assignGlobalRole`). Výchozí oprávnění `users.*`, `roles.*`, `teams.*`
  (O7). Jmenuje ji jen super-admin nebo CLI (O8); instalátor ji nenabízí.
- **Admin týmu** — jedna konfigurovatelná globální role
  (`wire-module-users.teams.admin_role`, výchozí `team-admin`), přiřazovaná
  **v týmu**. Výchozí oprávnění `users.*` a `roles.*` (O6), která pak platí
  jen v týmu, kde ji člověk má (to už Spatie dělá sám). Název i oprávnění
  konfigurovatelné.

### 2.2 Kdo smí co

| Akce | Admin týmu | Admin (globální) | Super-admin |
|---|---|---|---|
| vidět uživatele | jen členy aktuálního týmu | všechny | všechny |
| přidat / odebrat člena týmu | ano, ve svém týmu | ano, v každém týmu | ano |
| založit úplně nový účet | ano, rovnou jako člena týmu | ano | ano |
| editovat jméno | ano, ve svém týmu | ano, kromě super-adminů | ano |
| editovat e-mail a heslo | **ne** — jen poslat odkaz na reset hesla (O2) | ano, kromě super-adminů | ano |
| smazat účet | **ne** — jen odebrat z týmu (O1) | ano, kromě super-adminů | ano |
| vidět role | globální šablony (jen číst) + role svého týmu | všechny | všechny |
| založit / upravit roli týmu | ano, ve svém týmu | ano | ano |
| upravit globální roli | ne | ano, kromě role admin a super-admin | ano |
| přidělit roli členovi | jen role svého týmu / globální šablony, jen v aktuálním týmu | ano, v kterémkoli týmu | ano |
| přidělit oprávnění roli | **jen oprávnění, která sám má** | **jen oprávnění, která sám má** | ano |
| udělat někoho adminem týmu | ano, ve svém týmu | ano | ano |
| udělat někoho globálním adminem | ne | **ne** (O8) | ano |
| udělat někoho super-adminem | **nikdy** (jen CLI) | **nikdy** (jen CLI) | jen CLI (`--super-admin`) |

### 2.3 Ochrana super-adminů

Admin (i globální) nesmí na účet super-admina sáhnout: editovat, smazat,
odebrat mu role ani ho odebrat z týmu. Jinak by si admin mohl super-admina
„vypnout" (změnit mu heslo a přihlásit se jako on). Kanonicky jedna policy nad
účtem: *je cíl super-admin (`hasGlobalRole(Roles::superAdmin())`) a aktér ne →
zamítnout*. Totéž pro roli super-admin a roli admin v `RoleResource`
(přejmenování, oprávnění, smazání) — mění je jen super-admin.

Posledního super-admina nejde odebrat ani smazat z UI; z CLI jen s `--force`
a varováním, že instalace zůstane bez majitele (O9).

### 2.4 Anti-eskalace (jeden kanonický vlastník)

Nová `Support\RoleGrants` (O3; `SyncsRoles` na ni deleguje) odpovídá na jedinou
otázku: *smí aktér dát tuto sadu rolí / oprávnění
tomuto účtu v tomto týmu?*

- role, která nese oprávnění, jež aktér sám nemá → ne;
- role cizího týmu → ne;
- super-admin → nikdy z UI (už hotové: `Roles::options()` ji nevrací,
  `SyncsRoles` ji ignoruje i zachová);
- aktér super-admin → vždy ano;
- globální admin → jako admin týmu, jen bez omezení na tým (oprávnění si
  nepřidá, super-admina ani admina nevytvoří).

Stejný vlastník hlídá formulář uživatele, formulář role (permissions) i
hromadné akce — dnes je kontrola jen v `SyncsRoles`.

## 3. Obrazovky

### 3.1 `UserResource`

- Dotaz: se zapnutými týmy a pro aktéra, který `users.viewAny` nemá
  **globálně** (super-admin ani globální admin),
  `whereHas(Teams::relation(), fn ($q) => $q->whereKey(Teams::currentId()))`.
  Rozlišit „oprávnění z globální role" od „oprávnění v týmu" potřebuje veřejné
  API v permission-extended (`hasGlobalPermission()` — dnes je to chráněná
  `hasPermissionViaGlobalRole()`); doplnit v 1.1.
  Kanonicky přes `Teams` (nový `Teams::scopeMembers(Builder)`), ne lokální
  `where` v resource.
- Vytvoření účtu adminem týmu → účet se rovnou připojí do aktuálního týmu.
- „Odebrat z týmu" jako akce vedle „smazat"; smazat účet smí jen globální
  admin a super-admin — účet může být ve více týmech (O1).
- Admin týmu mění členovi jen jméno; e-mail a heslo ne, místo toho akce
  „poslat odkaz na reset hesla" (O2).
- Záznam mimo tým → 404 (ne 403), aby se nedalo zjistit, že účet existuje.

### 3.2 `RoleResource`

- Dotaz: `whereNull(team) OR team = currentId` (přesně Spatie `findByParam`
  sémantika); super-admin a globální admin vidí vše.
- Globální role: pro admina týmu jen čtení.
- Role super-admin: **nikdy editovatelná v UI** (přejmenování rozbije bránu),
  nezobrazuje oprávnění (má všechno).
- Nová role vytvořená adminem týmu dostane `team_id = currentId`.

### 3.3 Přepínač týmu

Beze změny (`PageChrome::TOPBAR`). Super-admin a globální admin navíc vidí
**všechny** týmy, nejen své (jinak by nemohli spravovat tým, jehož nejsou
členy) (O4).

## 4. CLI a instalátor

- `wire:assign-role <email> --role=team-admin --team=3` funguje už teď (role
  týmu v týmu, kde je člen).
- Instalátor s týmy tým **nezakládá** (O5): aplikace týmy vlastní a model může
  mít povinná pole. Jen poradí, že super-admin tým nepotřebuje a jak přidat
  členy a admina týmu (`wire:assign-role <email> --role=team-admin --team=<id>`).
- Odebrání posledního super-admina z CLI jen s `--force` (O9).

## 5. Dopady a rizika

- **Žádný vypínač filtrování** (rozhodnuto): staré chování je bezpečnostní
  díra a 2.0.1 ještě nevyšla, takže není komu zachovávat kompatibilitu.
  Obrazovky se zapnutými týmy filtrují vždy.
- Výkon: `whereHas` na seznamu uživatelů — index na pivotu `team_user`
  (aplikace), dokumentovat.
- Testy: nový `TeamAdministrationTest` (matice z §2.2), browser driver pro
  přepínač + seznam uživatelů.

## 6. Pořadí implementace (po odsouhlasení)

0. permission-extended: veřejné `hasGlobalPermission()`; vydat 1.1.0.
1. `Teams::scopeMembers()` + scoping `UserResource` (uzavře díru) + testy.
2. Scoping `RoleResource` + ochrana role super-admin v UI.
3. `RoleGrants` — anti-eskalace pro role i oprávnění.
4. Role admina týmu a globálního admina (config; globální přiřazení z CLI
   přes `wire:assign-role <email> --role=admin --global`, jen z CLI nebo
   super-adminem; docs).
4b. Ochrana super-adminů (§2.3) — policy nad účtem a nad rolemi.
5. Docs EN/CS (`teams-and-two-factor.md`, `users.md`), boost guideline,
   CHANGELOG; ověření na čerstvé aplikaci se dvěma týmy a třemi účty.

## 7. Rozhodnutí (2026-09-14)

| # | Otázka | Rozhodnutí |
|---|---|---|
| O1 | Smí admin týmu smazat účet? | Ne — jen odebrat z týmu. Smazat smí globální admin a super-admin. |
| O2 | Smí admin týmu měnit jméno, e-mail, heslo? | Jméno ano; e-mail a heslo ne — jen poslat odkaz na reset hesla. |
| O3 | Kde je anti-eskalace? | Nová `Support\RoleGrants`; `SyncsRoles` deleguje. |
| O4 | Vidí SA a globální admin v přepínači všechny týmy? | Ano, oba. |
| O5 | Zakládá instalátor první tým? | Ne, jen poradí. |
| O6 | Admin týmu | `team-admin`, výchozí `users.*` + `roles.*`, konfigurovatelné. |
| O7 | Globální admin | `admin`, výchozí `users.*` + `roles.*` + `teams.*`; instalátor ho nenabízí. |
| O8 | Smí globální admin jmenovat globálního admina? | Ne, jen super-admin nebo CLI. |
| O9 | Poslední super-admin | Z UI nejde odebrat ani smazat; z CLI jen s `--force`. |
| — | Vypínač filtrování obrazovek | Ne, filtrovat vždy. |
