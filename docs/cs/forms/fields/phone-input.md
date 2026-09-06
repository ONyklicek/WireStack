---
summary: Výběr předvolby vedle národního čísla, uložený jako jeden E.164 řetězec.
---

# PhoneInput

Mezinárodní telefonní číslo: předvolba země se vybírá ze seznamu, zbytek se píše
a obojí je jedna hodnota. Sáhněte po něm, když se na číslo bude volat, bude se
exportovat nebo párovat — `TextInput::make()->tel()` přijme cokoli, co člověk
napíše, včetně národního čísla, na které nikdo mimo tu zemi nedovolá.

```php
use NyonCode\WireForms\Components\PhoneInput;
```

## Jak to funguje

**Předvolba žije uvnitř hodnoty, ne vedle ní.** Stav drží napsané mezinárodní
číslo — `+420 123 456 789` — a controller ho při každém vykreslení rozdělí pro
zobrazení a při každém stisku klávesy zase složí. Pole, které by zemi drželo
zvlášť, by potřebovalo druhý sloupec, aby ji uložilo, nebo by ji ztratilo: na
číslo, jehož předvolba existuje jen v UI, se z databáze nedovoláte.

**Ukládá se stejné číslo bez mezer**, tedy E.164: `+420123456789`. Mezery jsou
prezentace — přidá je `hydrateState()` na cestě dovnitř a odebere
`dehydrateState()` na cestě ven.

**Seskupuje se po trojicích a poslední osamocená číslice se přidá ke skupině před
sebou** — `+1 212 555 1234`, ne `+1 212 555 123 4`. Controller píše v prohlížeči
stejné seskupení jako PHP na serveru.

**Národní část se během psaní nepřeformátovává.** Přeskupování při každém stisku
klávesy odsune kurzor na konec vstupu, což znemožňuje editaci uprostřed čísla.

**Seznam zemí je kurátorovaná tabulka** (`Support\DialingCodes`), ne kopie
registru ITU: každá položka nese předvolbu a rozsah počtu číslic, které daná země
vydává, což je to jediné, co dělá napsané číslo kontrolovatelným. Možnosti se
čtou jako vlajka a předvolba — `🇨🇿 +420` — a vlajka se počítá z ISO kódu, místo
aby se ukládala, takže se s ním nemůže rozejít.

**Předvolba není jedinečná.** `+1` je celý severoamerický plán, takže přiřazení
čísla k zemi odpoví první položkou, která tu předvolbu drží. Volba je kosmetická:
rozhoduje, kterou vlajku select ukáže, nikdy o platnosti čísla — země sdílející
předvolbu sdílejí i rozsah číslic.

**Validace se ptá ve třech krocích** a každý má vlastní hlášku: je číslo vůbec
mezinárodní (úvodní `+`), je jeho předvolba mezi nabízenými a má národní část
tolik číslic, kolik daná země vydává. Země mimo tabulku se drží mezí samotného
E.164 — 4 až 15 číslic — což je maximum, co může říct kontrola bez tabulky.

**Nabídku lze nastavit jednou pro celou aplikaci.** `config('wire-forms.phone.countries')`
a `default_country` jsou to, na co pole spadne; nastavovat je u pole je pro výjimky.

## Základní použití

```php
PhoneInput::make('phone')
```

Všechny země z tabulky, otevřené na Česku.

## Omezení nabídky zemí

```php
PhoneInput::make('phone')
    ->countries(['CZ', 'SK', 'DE', 'AT'])   // v pořadí, v jakém jsou vypsané
```

Seznam je zároveň validací: číslo `+49` pole, které nabízí jen `CZ` a `SK`,
odmítne. ISO kód, který tabulka nezná, se zahodí, místo aby se nabídl jako mrtvá
možnost.

## Kde se pole otevírá

```php
PhoneInput::make('phone')
    ->countries(['CZ', 'SK', 'DE'])
    ->defaultCountry('DE')      // jinak první nabízená země
```

Výchozí země platí, dokud je pole prázdné; pole s číslem se otevře na zemi toho
čísla.

## Rozšířený příklad

```php
use Livewire\Component;
use NyonCode\WireForms\Components\PhoneInput;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditContact extends Component
{
    use WithForms;

    public array $data = [];

    public function mount(Contact $contact): void
    {
        $this->form->fill($contact->attributesToArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(Contact::class)
            ->statePath('data')
            ->schema([
                TextInput::make('name')->required(),
                PhoneInput::make('phone')                  // [tl! focus:start]
                    ->countries(['CZ', 'SK', 'PL', 'DE'])
                    ->defaultCountry('CZ')
                    ->required(),                          // [tl! focus:end]
            ]);
    }
}
```

Sloupec drží `+420123456789`, připravené k vytočení, exportu nebo spárování
s číslem z jiného systému bez předchozí normalizace.

## API PhoneInput

Telefonní část. Popisek, hint, placeholder, viditelnost a zbytek jsou sdílené API
pole, zdokumentované ve [Formulářová pole](index.md).

```php
->countries(array $countries)       // ISO 3166-1 alpha-2 kódy — výchozí: config('wire-forms.phone.countries')
->defaultCountry(string $country)   // kde se otevře prázdné pole — výchozí: config('wire-forms.phone.default_country')
->getCountries(): array             // array<int, DialingCode>
->getDefaultCountry(): ?DialingCode
->getCountryOptions(): array        // array<int, array{country: string, dialingCode: string, label: string}>
```

## Související

- [Formulářová pole](index.md) — sdílené API pole
- [TextInput](text-input.md) — pro číslo, na které nikdy nebude volat program
- [Validace](../validation.md) — jak se implicitní pravidla přidávají k vašim
