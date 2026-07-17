# WireStack Architecture & Coding Standard

Binding standard for all code in this repository, human- or AI-authored. Where
this file and habit disagree, this file wins. `CLAUDE.md` routes here; read it
before writing code, not after.

## Philosophy

WireStack is built around **composition over inheritance**.

The framework is a collection of small, independent capabilities that combine.
Every component stays modular, reusable, extensible, and easy to test. When
multiple implementations are possible, choose the one that maximizes composition
and minimizes inheritance.

## General Principles

- Composition over inheritance.
- Interfaces before implementations.
- Small, reusable building blocks.
- Single Responsibility Principle.
- SOLID compliance.
- Convention over configuration.
- Explicit APIs over magic.
- Prefer readability over cleverness.
- Keep public APIs stable.

## Interfaces

Interfaces define contracts only. They describe capabilities, contain no
implementation, stay small, and expose only public methods.

```php
interface HasLabel
interface HasColor
interface HasIcon
interface HasState
interface CanBeDisabled
interface CanBeHidden
```

One interface represents exactly one capability. Never create large interfaces
containing unrelated methods.

## Traits

Traits provide reusable implementations. A trait implements a single capability,
stays cohesive and reusable, and avoids hidden side effects.

Naming:

```text
InteractsWithLabel
InteractsWithColor
InteractsWithState
CanBeDisabled
CanBeHidden
```

Traits stay lightweight. If a trait becomes difficult to understand, split it.
**Traits must not become mini-frameworks.**

## Business Logic

**Traits are not the place for business logic.** Business logic belongs in:

- Actions
- Services
- Managers
- Pipelines
- Support classes

A trait exposes a clean API and delegates the complex work.

Good:

```php
public function save(): void
{
    app(SaveRecord::class)->execute($this);
}
```

Bad:

```php
public function save(): void
{
    // 400 lines of logic
}
```

## Abstract Classes

Avoid abstract classes. Introduce one only when ALL of the following apply:

- shared protected state is required
- a shared lifecycle exists
- composition would make the code worse

Otherwise use interfaces and traits. Abstract classes should be rare.

## Classes

Classes stay small: under 300 lines, one responsibility, minimal dependencies.
Avoid "God Objects".

## Final Classes

Prefer `final class Service`. Make a class extendable only for a clear
architectural reason.

## Dependency Injection

Never instantiate services manually.

```php
new UserRepository();                    // bad

public function __construct(             // good
    private UserRepository $repository,
) {}

app(UserRepository::class)               // good
```

## Fluent API

Builder methods return `static`.

```php
$field->label('Name')->required()->disabled();
```

## Naming

| Kind | Examples |
| --- | --- |
| Interfaces | `HasLabel`, `HasIcon`, `CanDelete`, `CanSort` |
| Traits | `InteractsWithLabel`, `InteractsWithIcon`, `CanDelete`, `CanSort` |
| Actions | `CreateUser`, `DeleteUser`, `SyncPermissions`, `GenerateColumns` |
| Services | `TranslationService`, `AssetManager`, `NavigationManager` |
| Managers | `PluginManager`, `ComponentManager`, `ThemeManager` |

## Directory Structure

```text
Contracts/      Interfaces
Concerns/       Traits
Actions/        Single-purpose actions
Services/       Stateless services
Managers/       Stateful coordinators
Support/        Helper classes
Enums/
Exceptions/
Builders/
Collections/
ValueObjects/
DTOs/
Events/
Listeners/
Pipelines/
Commands/
Policies/
```

## State

Avoid mutable shared state. Prefer immutable value objects. Configuration is
explicit.

## Static Methods

Avoid static methods. Allowed only for factories, named constructors, and pure
helpers. Never hide business logic behind a static call.

## Facades

Facades stay thin and forward to services. No business logic inside a facade.

## Exceptions

Throw meaningful exceptions. Never silently ignore failures. Prefer a dedicated
exception class over a generic `RuntimeException` where appropriate.

Concretely (see ADR 0022):

- Every exception lives in the owning package's `Exceptions/`, is `final`, and
  implements `NyonCode\WireCore\Foundation\Contracts\WireException` so a consumer
  can catch the whole stack in one clause.
- **Extend the SPL class the failure really is** — `InvalidArgumentException` for
  a bad argument, `RuntimeException` for a bad state. When replacing an existing
  throw, keep the base it already threw: the SPL class is published behaviour,
  and the two are siblings, so switching breaks every `catch`. A domain class
  therefore never spans two bases; split by base first, then by domain.
- Use static named constructors (`UnsafeSqlException::reservedIdentifier($id)`)
  rather than a subclass per message. Carry context a handler might want as
  readonly properties, not only interpolated into the message.
- Never return an error shape (`['error' => ...]`) from a domain or support
  class. Throw, and catch at the layer that can answer for it — the MCP tool
  boundary, the Livewire host. A layer that must not throw catches there, once.
- Wrap a foreign failure instead of flattening it: keep the original as
  `previous`.
- A `catch` that *decides* something (no auth context → deny; an untyped method
  that must be called to be identified) is a probe, not swallowing — but write
  the reason down. A `catch` that discards information the caller needed is a bug.

## Testing

Everything is testable. Avoid hidden dependencies, favor constructor injection,
keep units isolated.

Coverage is enforced, and the rule is **the code you write is covered**: CI fails
a change whose added or edited lines are not run by a test
(`scripts/verify-coverage.php`). The target is 100%; the repository does not
reach it yet, so each package is additionally held to a floor
(`scripts/coverage-floors.json`) that may only ever go up. This is the honest
version of the goal — a blanket `--min=100` today would fail every build and be
switched off within a week, which enforces nothing.

Write the test with the code. A line that genuinely cannot be reached gets a
reason, not a bare `@codeCoverageIgnore` — and note that the annotation does not
cover the `} catch (...) {` line itself, only the body, so an untestable
defensive catch is usually better deleted than annotated.

## Public API

Public APIs are stable. Changing a public method requires backward-compatibility
consideration. Internal implementation may change. Contracts stay stable.

## Performance

Avoid premature optimization. Optimize only when profiling shows a real
bottleneck. Prefer maintainability over micro-optimizations.

## Documentation

Public classes should be self-explanatory. Document public APIs, extension
points, and contracts. Avoid documenting obvious code.

## AI Code Generation Rules

When generating code for WireStack:

- Prefer interfaces over abstract classes.
- Provide reusable traits for interface implementations.
- Split responsibilities aggressively.
- Keep methods small.
- Keep classes focused.
- Never generate God Objects.
- Never place complex business logic inside traits.
- Prefer composition over inheritance.
- Use dependency injection.
- Prefer final classes.
- Follow Laravel coding conventions.
- Produce clean, maintainable, extensible code.

If multiple solutions exist, always choose the one that results in the most
modular and composable architecture.
