---
order: 50
summary: "Samostatné `<x-wire::*>` komponenty — tlačítka, odznaky, ikony i layoutové — použitelné v libovolném Blade view, bez schématu kolem."
---

# Blade komponenty

Všechno, co framework kreslí, je k dispozici i jako obyčejná Blade komponenta.
View, které není formulář, tabulka ani infolist, může použít tentýž button, badge,
ikonu i layout — a právě díky tomu nevypadají vlastní obrazovky aplikace jako jiný
produkt.

## Blade komponenty

Foundation poskytuje základní komponenty pod namespace `wire::`. `color` a `size`
mluví všude stejným slovníkem — `primary`, `danger`, `success`, `warning`, `info`
a názvy odstínů vedle nich — protože každá z nich se rozhoduje přes kanonické
vlastníky (`HasColor`, `HasSize`), místo aby si nesla vlastní paletu. `outlined`
vymění plnou výplň za obrys.

```blade
{{-- Ikona --}}
<x-wire::icon name="check" />

{{-- Badge --}}
<x-wire::badge color="success">Active</x-wire::badge>

{{-- Tlačítko --}}
<x-wire::button color="primary" size="sm">Save</x-wire::button>

{{-- Dropdown --}}
<x-wire::dropdown>
    <x-slot:trigger>Options</x-slot:trigger>
    <x-wire::dropdown.item>Edit</x-wire::dropdown.item>
    <x-wire::dropdown.item>Delete</x-wire::dropdown.item>
</x-wire::dropdown>

{{-- Soubor: obrázek, když je co ukázat, jinak přípona na barvě své rodiny --}}  {{-- [tl! focus:start] --}}
<span class="flex h-10 w-10 overflow-hidden rounded-lg">
    <x-wire::file-thumb :name="$file->name" :mime="$file->mime_type" :url="$file->previewUrl()" size="md" />
</span>                                                          {{-- [tl! focus:end] --}}
```

**Řádek menu** je `<x-wire::menu-item>` — odkaz, nebo tlačítko odesílající
formulář kolem sebe, nakreslené tak, jak takový řádek kreslí menu frameworku:

```blade
<x-wire::menu-item :href="route('profile')" icon="outline:user-circle" wire:navigate>
    Profil
</x-wire::menu-item>
```

Tohle je komponenta z core; `<x-wire-admin::menu-item>` je tentýž řádek uvnitř
uživatelského menu [admin shellu](../../admin/layout.md) — takže cokoli tam dáte
vypadá jako to, co v něm už je.

`file-thumb` vyplní jakýkoli box, do kterého ho dáte — stejná komponenta je
32pixelový náhled v řádku i dlaždice přes celou šířku — a `size` (`sm`, `md`,
`lg`) škáluje, co se kreslí uvnitř. Obrázek vykreslí jen tehdy, když soubor je
obrázek **a** dostal `url`; PDF s naprosto platnou URL dostane kartu, protože PDF
protažené přes `<img>` je ikona rozbitého obrázku tvrdící, že je soubor
poškozený.

## Layoutové komponenty

Kanonický layout slovník žije v `NyonCode\WireCore\Foundation\Schema\*` a je sdílený jak
**formuláři**, tak **infolisty** (forms `Layout\*` třídy rozšiřují core verze). Použijte je v jakémkoli
`->schema([...])` poli místo ad-hoc Blade gridů.

| Komponenta | Účel |
|-----------|---------|
| `Grid` | Responzivní sloupcový grid |
| `Section` | Titulovaná karta s nadpisem/popisem |
| `Fieldset` | Ohraničená skupina s legendou |
| `Flex` | Flexbox řada vedle sebe, která se na mobilu skládá |
| `Tabs` / `Tab` | Záložkové panely |
| `Wizard` / `Step` | Vícekrokový layout |
| `Callout` | Jemný barevný upozorňovací box |
| `EmptyState` | Ikona + nadpis + popis + akce |

```php
use NyonCode\WireCore\Foundation\Schema\{Grid, Section, Flex, Callout};

Section::make('Team')
    ->description('People with access.')
    ->schema([
        // Int reflow, nebo mapa podle breakpointů ve stylu Filamentu.
        Grid::make()->columns(['default' => 1, 'md' => 2, 'lg' => 3])->schema([...]),
    ]);

// Flex: řídit rozdělení, zarovnání, mezery, wrap a růst potomků.
Flex::make()->from('md')->justify('between')->align('center')->gap(6)->wrap()->grow(false)->schema([...]);

// Callout — odstíny barev delegují na kanonickou alert paletu.
Callout::make()->warning()->heading('Heads up')->icon('exclamation-triangle')->dismissible()
    ->content('Something worth noticing.');
```

`Callout` je sdílený vlastník upozorňovacího povrchu; forms `Alert` pole je jeho field-style alias.
Počty sloupců (`Grid`, `CheckboxList`, `Section`, …) přijímají int **nebo** mapu podle breakpointů, klíčovanou
`default`/`sm`/`md`/`lg`/`xl`/`2xl`.

### Samostatné Blade tagy

Stejné layouty jsou také vystaveny jako slot-based `wire::` tagy pro prosté Blade pohledy (bez schema pole):

```blade
<x-wire::callout color="warning" heading="Storage almost full" icon="exclamation-triangle" dismissible>
    You have used 95% of your quota.
</x-wire::callout>

<x-wire::grid :columns="['default' => 1, 'md' => 2, 'lg' => 3]" gap="gap-3">…</x-wire::grid>

<x-wire::flex from="md" justify="between" align="center" :gap="4">…</x-wire::flex>

<x-wire::section heading="Profile" description="Basic info">…</x-wire::section>
<x-wire::fieldset legend="Billing address">…</x-wire::fieldset>

<x-wire::empty-state icon="outline:inbox" heading="No invoices yet" description="They will show up here.">
    <button>New invoice</button> {{-- slot se stane řádkem akcí --}}
</x-wire::empty-state>

{{-- Alpine-driven; jen client-side stav (bez validace jednotlivých kroků) --}}
<x-wire::tabs>
    <x-wire::tab label="Profile">…</x-wire::tab>
    <x-wire::tab label="Security">…</x-wire::tab>
</x-wire::tabs>

<x-wire::wizard>
    <x-wire::step label="Account">…</x-wire::step>
    <x-wire::step label="Confirm">…</x-wire::step>
</x-wire::wizard>
```

Pro validované vícekrokové toky použijte action-modal wizardy (`HasModal::steps()`) nebo form schema `Wizard`
místo toho — samostatné `<x-wire::tabs>` / `<x-wire::wizard>` jen přepínají panely client-side.

## Související

- [Schéma](../schema/overview.md) — tytéž layouty jako deklarativní komponenty
- [Barvy](colors.md) a [Ikony](icons.md) — atributy, které tyhle komponenty berou
- [Admin shell](../../admin/overview.md) — rám postavený z těchhle komponent
- [Motivy a přizpůsobení](../../start/theming.md) — přebití views za nimi
