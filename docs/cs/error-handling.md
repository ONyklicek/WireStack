---
order: 55
---

# Ošetření chyb

Wire selhává hlasitě a odchytitelně. Každé selhání, které stack vyvolá, je `final` exception třída
z namespace `Exceptions/` vlastnícího balíčku, a všechny implementují sdílený marker interface — takže si
můžete chytat tak široce nebo tak úzce, jak potřebujete.

## Chytit celý stack

`NyonCode\WireCore\Foundation\Contracts\WireException` označuje selhání jako pocházející z wire, ne z PHP,
Laravelu nebo vašeho vlastního kódu:

```php
use NyonCode\WireCore\Foundation\Contracts\WireException;

try {
    $table->getQuery();
} catch (WireException $e) {
    report($e); // wire komponenta je špatně nakonfigurovaná
}
```

Je to marker interface — bez metod — protože jediné, co mají všechna wire selhání společné, je jejich původ.

## Chytit jednu konkrétní věc

Každá výjimka je vlastní třída, takže můžete ošetřit přesně ten případ, který vás zajímá:

```php
use NyonCode\WireTable\Exceptions\TableHasNoDataSourceException;

try {
    $table->getQuery();
} catch (TableHasNoDataSourceException $e) {
    // Nebylo nastaveno ani ->model(), ani ->query()
}
```

## SPL předek je součást kontraktu

Každá wire výjimka dědí ze SPL třídy, která popisuje, co se reálně pokazilo:

| Předek | Znamená | Příklad |
|------|-------|---------|
| `InvalidArgumentException` | předali jste něco neplatného | `TableConfigurationException`, `UnsafeSqlException` |
| `RuntimeException` | objekt je ve stavu, ze kterého nemůže jednat | `TableHasNoDataSourceException`, `ImportException` |

Je to záměrné a stabilní: **kód, který už chytá SPL třídu, funguje dál.** Když wire nahradí generický
`throw new RuntimeException(...)` doménovou třídou, ta doménová třída dědí z `RuntimeException`, takže
existující `catch (RuntimeException $e)` to nijak nezasáhne. Přechod na wire výjimku pro vás nikdy není
breaking change.

## Co které balíčky hází

| Balíček | Výjimka | Kdy nastane |
|---------|-----------|-------------|
| `wire-core` | `UnsafeSqlException` | identifikátor, směr řazení nebo operátor mířící do SQL není prokazatelně bezpečný |
| | `InvalidRelationPathException` | tečkovou cestu (`author.company.name`) nelze naparsovat |
| | `InvalidAggregateException` | agregační funkce, strategie nebo sloupec není platný |
| | `IconSetRegistrationException` | sada ikon je registrovaná pod rezervovaným nebo nejednoznačným prefixem |
| | `PluginRegistrationException` | id pluginu je obsazené, nebo závislost není registrovaná |
| | `ModelNotRegisteredException` | jsou požadována metadata pro neregistrovaný model |
| | `InvalidChartDataException` | chart widget dostal data nebo options, které neumí vyrenderovat |
| `wire-forms` | `FormConfigurationException` | formulář nemá model, nebo si jeho form metody protiřečí |
| | `StaleModelException` | optimistic-lock kontrola zjistila, že se záznam mezitím změnil ([save lifecycle](forms/save-lifecycle.md)) |
| `wire-table` | `TableHasNoDataSourceException` | tabulka je dotazovaná bez `model()` nebo `query()` |
| | `TableConfigurationException` | poll interval, cesta v `groupBy()` nebo typ summary není platný |
| | `RelationManagerException` | relation manager je špatně nakonfigurovaný, nebo vztah danou operaci nepodporuje |
| | `ImportException` | importovanému souboru chybí povinné sloupce, nebo import nemá model/handler |

## Výjimky nesou kontext

Tam, kde by handler chtěl víc než jednu větu, nese ho výjimka jako readonly properties, ne jen
interpolovaný do zprávy:

```php
} catch (StaleModelException $e) {
    $e->model;       // záznam, který se posunul
    $e->lockColumn;  // sloupec, který to zachytil
}
```

## Na co wire výjimku *nehází*

Wire nevyvolá výjimku kvůli nepřítomné věci tam, kde je nepřítomnost legitimní odpovědí. Komponenta bez
přihlášeného uživatele není chyba — prostě není autorizovaná a skryje se. Audit záznam zapsaný z konzolové
komandy nemá aktéra a stejně se zapíše.

Jeden případ stojí za zmínku, protože je tichý záměrně: **neznámá barva nevyhodí výjimku.**
`->color('bleu')` se vyresolvuje na šedou, místo aby rozbila stránku. Je to vědomý kompromis — překlep
nemá položit view — ale znamená to, že se špatně napsaná barva vyrenderuje potichu. Pokud to chcete
zachytit, `Color::tryResolve()` vrací pro název, který není barvou, `null`, a nástroj
`validate-wire-component` z [wire-boostu](boost/mcp-tools.md) to nahlásí — spolu s neregistrovanými
ikonami a názvy sloupců, které váš model neumí vyresolvovat.

## Související

- [Save Lifecycle](forms/save-lifecycle.md) — kam zapadá `StaleModelException`
- [Autorizace](authorization.md) — proč se zamítnutá komponenta skryje místo házení výjimky
- [Wire Boost](boost/mcp-tools.md) — nástroje, které najdou selhání, jež zůstávají tichá
