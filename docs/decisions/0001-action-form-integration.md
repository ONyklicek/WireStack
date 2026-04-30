# ADR 0001: Action ↔ Form Integration

## Status
Accepted

## Context
Actions (in `wire-core`) can display modal forms. The form fields (in `wire-forms`) must be rendered inside action modals. However, `wire-core` must not have a hard dependency on `wire-forms`.

## Decision
Actions accept form field definitions via `->form([...])` as an array of `Field` objects. The Action classes are agnostic about the concrete Field implementations – they call `->toArray()` on each field and pass the serialized data to the Blade views for rendering.

The integration point is:
1. **Action::form()** (in `wire-core`) accepts any array of objects that implement a `toArray()` method.
2. **Field::toArray()** (in `wire-forms`) serializes field configuration for rendering.
3. The Blade views in `wire-table` (or any consuming package) handle the actual rendering.

This means:
- `wire-core` has zero knowledge of `wire-forms` – no imports, no interfaces.
- `wire-forms` has zero knowledge of `wire-core` Actions – Fields are standalone.
- `wire-table` ties them together: its views render form fields in action modals.

## Consequences
- **Good:** Clean dependency graph. Core doesn't know Forms. Forms can be used standalone.
- **Good:** Any package can provide field-like objects to Actions, as long as they implement `toArray()`.
- **Trade-off:** No type safety on `Action::form()` parameter – it accepts any array. Runtime validation happens in the Blade views.
