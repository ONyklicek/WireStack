---
order: 55
---

# Error Handling

Wire fails loudly and catchably. Every failure the stack raises is a `final` exception class from the
owning package's `Exceptions/` namespace, and every one of them implements a shared marker interface, so
you can catch as broadly or as narrowly as you like.

## Catch the whole stack

`NyonCode\WireCore\Foundation\Contracts\WireException` marks a failure as coming from wire rather than from
PHP, Laravel, or your own code:

```php
use NyonCode\WireCore\Foundation\Contracts\WireException;

try {
    $table->getQuery();
} catch (WireException $e) {
    report($e); // a wire component is misconfigured
}
```

It is a marker interface — no methods — because the only thing every wire failure has in common is where
it came from.

## Catch one thing

Each exception is a class of its own, so you can handle exactly the case you care about:

```php
use NyonCode\WireTable\Exceptions\TableHasNoDataSourceException;

try {
    $table->getQuery();
} catch (TableHasNoDataSourceException $e) {
    // Neither ->model() nor ->query() was set
}
```

## The SPL base is part of the contract

Every wire exception extends the SPL class that describes what actually went wrong:

| Base | Means | Example |
|------|-------|---------|
| `InvalidArgumentException` | you passed something invalid | `TableConfigurationException`, `UnsafeSqlException` |
| `RuntimeException` | the object is in a state it cannot act from | `TableHasNoDataSourceException`, `ImportException` |

This is deliberate and stable: **code that already catches the SPL class keeps working.** When wire replaces
a generic `throw new RuntimeException(...)` with a domain class, the domain class extends
`RuntimeException`, so an existing `catch (RuntimeException $e)` is unaffected. Adopting a wire exception is
never a breaking change for you.

## What each package throws

| Package | Exception | Raised when |
|---------|-----------|-------------|
| `wire-core` | `UnsafeSqlException` | an identifier, sort direction or operator heading for SQL is not provably safe |
| | `InvalidRelationPathException` | a dot-notation path (`author.company.name`) cannot be parsed |
| | `InvalidAggregateException` | an aggregate function, strategy or column is not valid |
| | `IconSetRegistrationException` | an icon set is registered under a reserved or ambiguous prefix |
| | `PluginRegistrationException` | a plugin id is taken, or a dependency is not registered |
| | `ModelNotRegisteredException` | metadata is requested for an unregistered model |
| | `InvalidChartDataException` | a chart widget is given data or options it cannot render |
| `wire-forms` | `FormConfigurationException` | a form has no model, or its form methods contradict each other |
| | `StaleModelException` | an optimistic-lock check found the record changed underneath ([save lifecycle](forms/save-lifecycle.md)) |
| `wire-table` | `TableHasNoDataSourceException` | a table is queried with no `model()` or `query()` |
| | `TableConfigurationException` | a poll interval, `groupBy()` path or summary type is not valid |
| | `RelationManagerException` | a relation manager is misconfigured, or the relationship cannot support the operation |
| | `ImportException` | an import file is missing required columns, or the import has no model/handler |

## Exceptions carry context

Where a handler would want more than a sentence, the exception carries it as readonly properties rather
than only interpolating it into the message:

```php
} catch (StaleModelException $e) {
    $e->model;       // the record that moved
    $e->lockColumn;  // the column that detected it
}
```

## What wire does *not* throw for

Wire does not raise an exception for an absent thing where absence is a legitimate answer. A component
with no authenticated user is not an error — it is simply not authorized, and hides. An audit entry
written from a console command has no actor, and is still written.

One case deserves calling out because it is silent by design: **an unknown color does not throw.**
`->color('bleu')` resolves to gray rather than breaking the page. That is a deliberate trade — a typo
should not take down a view — but it does mean a misspelt color renders quietly. If you want it caught,
`Color::tryResolve()` returns `null` for a name that is not a color, and
[wire-boost](boost/mcp-tools.md)'s `validate-wire-component` tool reports it, along with unregistered
icons and column names your model cannot resolve.

## Related

- [Save Lifecycle](forms/save-lifecycle.md) — where `StaleModelException` fits
- [Authorization](authorization.md) — why a denied component hides instead of throwing
- [Wire Boost](boost/mcp-tools.md) — tooling that finds the failures that stay quiet
