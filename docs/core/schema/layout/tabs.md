---
order: 10
---

# Tabs

Tabbed layout: a horizontal tab bar over a set of panels, switched client-side
(no server round trip). All panels stay in the DOM, so nested fields validate
together on submit regardless of the active tab.

```php
use NyonCode\WireCore\Foundation\Schema\Tab;
use NyonCode\WireCore\Foundation\Schema\Tabs;
```

## Usage

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

The tab label defaults to a headline of the tab's name. On narrow screens the
tab bar scrolls horizontally instead of wrapping into multiple rows.

## Methods

| Method | On | Description |
|--------|----|-------------|
| `activeTab(int)` | `Tabs` | Zero-based index of the tab shown first |
| `icon(string\|Icon)` | `Tab` | Icon rendered before the label |
| `columns(int)` | `Tab` | Column grid for the tab's child schema |
| `visible()` / `hidden()` | `Tab` | Conditionally include a tab (indices re-align automatically) |

## Related Docs

- [Wizard](wizard.md)
- [Grid](grid.md)
- [Section](section.md)
- [Flex](flex.md)
