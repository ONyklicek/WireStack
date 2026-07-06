---
order: 100
---

# Návod k upgradu

Jak bezpečně přecházet mezi verzemi Wire a kde hledat breaking changes.

---

## Verzování

Ekosystém Wire se dodává jako čtyři balíčky — `wire-core`, `wire-forms`,
`wire-table`, `wire-sortable` — vydávané společně z jednoho monorepa, takže se
jejich verze pohybují v zámku. Instalujte je a omezujte jako celek.

Wire je aktuálně ve větvi **`0.x`**. Podle běžné konvence před 1.0 mohou minor
vydání obsahovat breaking changes, proto si připněte otestovanou verzi a před
zvýšením si přečtěte changelog:

```jsonc
// composer.json
"require": {
    "nyoncode/wire-core":     "^0.1",
    "nyoncode/wire-forms":    "^0.1",
    "nyoncode/wire-table":    "^0.1",
    "nyoncode/wire-sortable": "^0.1"
}
```

---

## Požadavky

| Závislost | Podporováno |
|------------|-----------|
| PHP | 8.2, 8.3, 8.4 |
| Laravel | 10, 11, 12 |
| Livewire | 3.x |
| Tailwind CSS | 3.x nebo 4.x |

Před upgradem ověřte, že je vaše aplikace splňuje.

---

## Kroky upgradu

1. **Přečtěte si changelog.** Zkontrolujte `CHANGELOG.md` pro verze, které
   přeskakujete, zejména jakoukoli sekci **Breaking Changes**.

2. **Aktualizujte balíčky.**

   ```bash
   composer update "nyoncode/wire-*"
   ```

3. **Znovu zkontrolujte publikované soubory.** Pokud jste publikovali konfiguraci,
   pohledy nebo překlady, vaše kopie se **neaktualizují** automaticky. Porovnejte
   je s novými verzemi balíčků a zapracujte relevantní změny:

   - `config/wire-*.php`
   - `resources/views/vendor/wire-*/…`
   - `lang/vendor/wire-*/…`

   Čím méně pohledů přepisujete, tím méně je zde ke sladění — viz
   [Vzhled → Přepis pohledů](theming.md#overriding-views).

4. **Vyčistěte cache a přebuildujte assety.**

   ```bash
   php artisan view:clear
   php artisan config:clear
   npm run build
   ```

5. **Spusťte testovací sadu.** [Testovací sada](testing.md) je nejrychlejší
   způsob, jak odchytit breaking change ve vlastních formulářích a tabulkách.

---

## Hledání breaking changes

`CHANGELOG.md` je zdroj pravdy. Breaking changes jsou vyznačeny pod nadpisem
**Breaking Changes** u každého vydání, často s migrační tabulkou před/po.
Například vydání `0.1.0` přesunulo akce a notifikace z
`NyonCode\WireTable\…` do `NyonCode\WireCore\…`; changelog vypisuje každou
přesunutou třídu, takže můžete `use` příkazy upravit hromadným najít-a-nahradit.

Pokud třída nebo metoda zmíněná v této dokumentaci po upgradu už neexistuje,
byla pravděpodobně přesunuta nebo přejmenována — hledejte původní název
v `CHANGELOG.md`.

---

## Viz také

- [Začínáme](getting-started.md) — požadavky a instalace
- [Konfigurace](configuration.md) — publikovatelná konfigurace
- [Vzhled](theming.md) — udržování přepisů pohledů na minimu
- [Řešení potíží](troubleshooting.md) — problémy, které se objeví po aktualizaci
