---
order: 10
---

# Wizard

Vícekrokový wizard layout: indikátor kroků nad sadou panelů s ovládáním Previous /
Next — samostatný protějšek
[wizardu action modalu](../../modals.md#multi-step-wizard). Všechny kroky zůstávají
v DOM, takže vnořená pole validují společně při odeslání bez ohledu na aktivní
krok.

```php
use NyonCode\WireCore\Foundation\Schema\Step;
use NyonCode\WireCore\Foundation\Schema\Wizard;
```

## Použití

```php
Wizard::make()->schema([
    Step::make('Account')->description('Login details')->icon('user')->schema([
        TextInput::make('name')->required(),
    ]),
    Step::make('Contact')->schema([
        TextInput::make('email')->required(),
    ]),
])
```

Na desktopu každý kruh indikátoru nese label a popis kroku; na
mobilu se indikátor sbalí na číslované kruhy a label a popis aktivního kroku
se vykreslí pod ním.

<a id="per-step-validation"></a>
## Validace po krocích

Uvnitř Livewire hostitele (`WithForms` nebo table action modal) **Next zvaliduje
aktuální krok na serveru před postupem** — stejná pravidla, jaká pole
deklarují (`rules()`, `required()`, pravidla položek repeateru, …), zúžená na ten krok.
Při selhání wizard zůstane na místě a chyby se vykreslí v aktivním panelu; pozdější
kroky se nikdy neoznačí předčasně. Přeskočení přes `skippable()` indikátor přeskočí
validaci, jako ve Filamentu.

Přicházejí s tím dvě související chování:

- **Neúspěšné odeslání skočí na první chybný krok**, takže zpráva z
  dřívějšího kroku nikdy neuvízne ve skrytém panelu.
- **Dynamické kroky zůstanou synchronizované**: když podmínka `visible()` přidá nebo odebere
  krok uprostřed formuláře (roundtrip `live()` pole), indikátor a navigace
  se přerovnají a aktivní krok se ořízne na vykreslený rozsah.

Vykreslen mimo Livewire hostitele, Next spadne zpět na prostou client-side
navigaci a formulář validuje při odeslání jako dřív.

Více wizardů na jednom hostiteli se adresuje podle názvu — dejte každému název
(`Wizard::make('signup')`), aby jeho kroky validovaly nezávisle; nepojmenovaný
wizard se resolvuje na první ve schématu.

## Metody

| Metoda | Na | Popis |
|--------|----|-------------|
| `activeStep(int)` | `Wizard` | Index (od nuly) kroku zobrazeného jako první |
| `skippable()` | `Wizard` | Povolit skok na jakýkoli krok z indikátoru |
| `description(string)` | `Step` | Sekundární řádek pod labelem kroku |
| `icon(string\|Icon)` | `Step` | Ikona kroku |
| `columns(int)` | `Step` | Sloupcový grid pro dětské schéma kroku |
| `visible()` / `hidden()` | `Step` | Podmíněně zahrnout krok (indexy se automaticky přerovnají) |

## Související dokumentace

- [Tabs](tabs.md)
- [Modaly — Vícekrokový wizard](../../modals.md#multi-step-wizard)
