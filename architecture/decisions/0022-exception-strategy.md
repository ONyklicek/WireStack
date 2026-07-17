# ADR 0022: Exception Strategy (domain exceptions, SPL bases, catch boundaries)

## Status

ACCEPTED — implemented 2026-07-15.

Establishes `Exceptions/` per package and a shared `WireException` marker,
migrates all 39 generic throw sites, and fixes wire-boost reporting failures as
successes.

## Context

`AI_CODING_STANDARD.md` says:

> Throw meaningful exceptions. Never silently ignore failures. Prefer a
> dedicated exception class over a generic `RuntimeException` where appropriate.

The repository did not do this. Before this ADR:

| | Count |
|---|---|
| `Exceptions/` directories | 0 |
| Custom exception classes | 1 (`StaleModelException`, outside any `Exceptions/`) |
| Generic throws | 39 — 24 `InvalidArgumentException`, 11 `RuntimeException` (core 21, table 13, forms 5) |
| `catch (Throwable)` sites | ~28 |
| wire-boost throws | 0 — it returned `['error' => ...]` arrays instead |

The messages were good (`'No model or query defined for table.'`,
`'TableImport requires a model() or a createUsing() handler.'`). The problem was
that **nothing could be caught selectively**: an application wanting to handle
"this table has no model" had to catch `RuntimeException` — which also catches
every unrelated runtime failure in the call — and match on message text.

wire-boost had a worse variant of the same problem. Its MCP tools returned
errors *inside a success payload*:

```php
return $this->json(['error' => "Class [{$class}] does not exist."]);
```

`Response::json()` reports `isError: false`. The MCP client is told the call
succeeded and hands the agent a result that happens to contain the word "error".
A test asserted exactly this contradiction, and passed:

```php
WireBoostServer::tool(DatabaseSchema::class, ['connection' => 'bogus'])
    ->assertOk()           // "this is not an error"
    ->assertSee('error');  // "...but it says 'error'"
```

## Decision

### 1. One marker, in the lowest layer

`NyonCode\WireCore\Foundation\Contracts\WireException extends Throwable`. Every
exception the stack throws implements it, so a consumer can catch the whole
stack in one clause without catching PHP's:

```php
try { $table->getQuery(); } catch (WireException $e) { /* wire misconfigured */ }
```

It is a marker: no methods, because the only thing every wire failure shares is
its origin.

### 2. Domain classes extend the SPL base the site already threw

This is the load-bearing rule. `TableHasNoDataSourceException extends
RuntimeException` because `Table::getQuery()` has always thrown
`RuntimeException` there. `TableConfigurationException extends
InvalidArgumentException` because its sites always threw that.

**The SPL base is published behaviour.** `InvalidArgumentException` and
`RuntimeException` are siblings (`LogicException` vs `RuntimeException`), so
"tidying" a site from one to the other silently breaks every `catch` and every
test. This was caught during implementation: grouping table failures into one
`InvalidArgumentException`-based class would have broken
`TableTest.php:320`, which asserts `RuntimeException` for that exact message.

Consequence: adopting a domain exception is **never** a breaking change. All 39
sites migrated with 20 existing tests still asserting the old SPL types, all
green.

Corollary: a domain class may not span two SPL bases. Split by base first, then
by domain — hence `TableConfigurationException` (bad argument) and
`TableHasNoDataSourceException` (bad state) rather than one "table exception".

### 3. Named constructors, not subclass-per-message

`final class`, extends the SPL base, implements `WireException`, and exposes
static named constructors:

```php
throw UnsafeSqlException::reservedIdentifier($identifier);
```

Named constructors are explicitly allowed by the standard's static-method rule.
They keep message text in one place, keep the class count proportional to the
domain rather than to the number of messages, and read at the throw site. The
existing `StaleModelException` (final, extends `RuntimeException`, carries
readonly context) was the template.

Context travels as readonly properties, not string interpolation, where a
handler might want it (`ComponentBuildFailedException::$component`).

### 4. Where the catch boundary sits

An exception is caught at the layer that can answer for it:

- **Support / domain layer**: throws. Never returns an error shape.
- **MCP tool (`BoostTool::handle`)**: catches `WireException` and renders
  `Response::error()`, which sets `isError: true`. A tool must not throw — an
  escaping exception becomes a JSON-RPC *protocol* error, telling the agent the
  server misbehaved rather than that its request was wrong.
- **Non-`WireException` throwables in boost**: left to propagate. They are bugs
  in the package, not bad requests, and must not masquerade as tool results.

Tools implement `run()`; `handle()` is the boundary. A tool that is itself the
boundary (a disabled `tinker`, a rejected non-SELECT query) returns
`Response::error()` directly — no exception needed to travel zero layers.

One deliberate inversion: `validate-wire-component` catches
`ComponentBuildFailedException` and reports it as a *finding*. Reporting problems
is that tool's job; every other tool needs a working component to say anything
and lets the failure reach the boundary.

### 5. Tolerant probes are not swallowed failures

"Never silently ignore failures" bans ignoring a *failure*. It does not ban
answering a question with "nothing". These are legitimate and stay, each with the
reason written down:

| Site | Answers |
|---|---|
| `HasAuthorization::isAuthorized()` | no resolvable guard → **deny** (fail closed) |
| `AuditLogger::resolveUserId()` | no auth context (console/queue) → no actor |
| `ModelIntrospector::isRelation()` | calling an untyped method is the only way to know |
| `ComponentReflector::callValue()` | a getter needing a record contributes nothing |

The test: if the `catch` block is *deciding* something, it is a probe and the
decision gets a comment. If it is discarding information the caller needed, it is
a bug.

## Consequences

- Consumers can catch `WireException`, a package's domain class, or the SPL base
  they always caught. All three work.
- New shared behaviour must pick an SPL base deliberately; "which do I extend?"
  is answered by "what did this site throw before?", and for new sites by bad
  argument (`InvalidArgumentException`) vs bad state (`RuntimeException`).
- wire-boost error responses now carry `isError: true`, so agents can tell a
  failure from a result. Tests that asserted the old contradiction were rewritten
  to `assertHasErrors()`.
- Messages aimed at agents name the recovery move (`list-component-types`,
  `search-wire-docs`) rather than only stating what was wrong.

### Known exception: StaleModelException stays put

`NyonCode\WireForms\Forms\Runtime\StaleModelException` does **not** move to
`Exceptions/`. `docs/forms/save-lifecycle.md` publishes that exact FQCN and tells
applications to `use` and catch it. The same standard that asks for the
`Exceptions/` layout also says public APIs are stable, and stability wins over
tidiness: relocating it breaks working code for no functional gain. It gains
`implements WireException` now and relocates in 2.0.
