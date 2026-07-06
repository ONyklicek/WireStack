---
order: 10
---

# Tabs

Záložkový layout: vodorovná lišta záložek nad sadou panelů, přepínaná client-side
(bez server round tripu). Všechny panely zůstávají v DOM, takže vnořená pole validují
společně při odeslání bez ohledu na aktivní záložku.

```php
use NyonCode\WireCore\Foundation\Schema\Tab;
use NyonCode\WireCore\Foundation\Schema\Tabs;
```

## Použití

```php
Tabs::make()->schema([
    Tab::make('Profile')->icon('user')->columns(2)->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->required(),
    ]),
    Tab::make('Preferences')->schema([
        Toggle::make('is_active'),
    ]),
])
```

Label záložky je ve výchozím stavu headline z názvu záložky. Na úzkých obrazovkách se
lišta záložek scrolluje vodorovně místo zalamování do více řádků.

## Metody

| Metoda | Na | Popis |
|--------|----|-------------|
| `activeTab(int)` | `Tabs` | Index (od nuly) záložky zobrazené jako první |
| `icon(string\|Icon)` | `Tab` | Ikona vykreslená před labelem |
| `columns(int)` | `Tab` | Sloupcový grid pro dětské schéma záložky |
| `visible()` / `hidden()` | `Tab` | Podmíněně zahrnout záložku (indexy se automaticky přerovnají) |

## Související dokumentace

- [Wizard](wizard.md)
- [Grid](grid.md)
- [Section](section.md)
- [Flex](flex.md)
