# Forms Package

Owner package: `packages/forms`

## What It Owns

`wire-forms` is the standalone form system built on top of `wire-core`.

It owns:

- field classes
- layout/display components
- form configuration API
- form runtime
- validation collection
- save lifecycle
- Livewire form integration

## First Files To Read

- `packages/forms/src/WireFormsServiceProvider.php`
- `packages/forms/src/Forms/Form.php`
- `packages/forms/src/Forms/WithForms.php`
- `packages/forms/src/Forms/Config/ConfigBuilder.php`
- `packages/forms/src/Forms/Config/FormConfig.php`
- `packages/forms/src/Forms/Runtime/FormRuntime.php`
- `packages/forms/src/Forms/Runtime/SaveHandler.php`
- `packages/forms/src/Validation/FormValidationResolver.php`
- `packages/forms/src/Integration/ActionMacros.php`

## Provider Responsibilities

`WireFormsServiceProvider` is intentionally small:

- binds `Form`
- registers Blade components under `wire-forms`
- boots `ActionMacros`

The important seam is `ActionMacros`, because forms integrate into core actions there.

## Runtime Shape

Users should interact mainly with:

- `Form`
- `WithForms`
- `Concerns\WithActions` — host trait for running standalone actions (modal/slide-over/wizard/confirmation/form + full lifecycle) in any Livewire component without a table. It composes the wire-core engine `Actions\Concerns\InteractsWithActions` (form-agnostic) with `Concerns\InteractsWithActionForms` (the form-hosting half). `WithTable` composes the same two, so the action runtime has one canonical owner. Render the mounted action with `<x-wire-actions::modal-host :component="$this" />`.

Internal shape:

```text
Form
  -> ConfigBuilder
  -> FormConfig
  -> FormRuntime
  -> StateManager
  -> SaveHandler
```

That split matters for safe changes:

- config concerns belong in `Config/`
- execution concerns belong in `Runtime/`

## Main Areas

### `Components/`

Field classes and display/layout components.

Typical edits:

- field API:
  concrete component class
- rendered markup:
  matching Blade view under `packages/forms/resources/views/`
- shared field behavior:
  `packages/forms/src/Components/Field.php`
- editor mentions (`Mention`, `Mention\Source`, the `searchEditorMentions` endpoint in
  `Concerns/InteractsWithFieldActions.php`, and `resources/js/tiptap-editor-mentions.js`):
  the field owns *what may be inserted*; what a stored mention reads as later belongs to
  `WireCore\Foundation\Mentions` — see `architecture/core.md`

### `Forms/Config/`

Fluent accumulation and immutable snapshots.

Use here for:

- new configuration methods
- config normalization
- default values

### `Forms/Runtime/`

Execution path for:

- validation
- state sync
- save lifecycle
- relationship persistence
- notifications during save

Most sensitive files:

- `packages/forms/src/Forms/Runtime/SaveHandler.php`
- `packages/forms/src/Forms/Runtime/StateDehydrator.php`
- `packages/forms/src/Forms/Runtime/StateManager.php`
- `packages/forms/src/Forms/Runtime/RelationshipSaveHandler.php`

`StateDehydrator` owns what a field's state becomes on the way out, for **every**
host. Three ask it today, through two seams in `Concerns/InteractsWithActionForms.php`:

- `SaveHandler`, writing a record.
- An action modal handing its data to a callback —
  `dehydrateMountedActionFormData()`, declared no-op in
  `WireCore\Actions\Concerns\InteractsWithActions` beside the validation seam it
  mirrors.
- A halt carrying its own form — `dehydrateHaltModalFormData()`, called from
  `WithTable::submitHaltModal()`. No core counterpart: nothing in wire-core
  re-executes a halted action.

Adding a fourth means calling a seam, not re-walking the schema. See ADR 0021 and
its amendment.

### `Validation/`

Collects validation data from form fields and delegates to core validation infrastructure.

### `Integration/`

`ActionMacros` is the main forms-to-core seam. If an action should open, render, validate, or submit a form differently, read this before touching downstream code.

## Typical Changes

- field behavior or UI:
  `Components/` + matching Blade views
- form save hooks:
  `Runtime/SaveHandler.php`
- what a field writes, wherever its state is handed over:
  `Runtime/StateDehydrator.php`
- state hydration or fill behavior:
  `Runtime/StateManager.php`
- builder/config API:
  `ConfigBuilder.php`, `FormConfig.php`, `Form.php`
- action + form interaction:
  `Integration/ActionMacros.php` plus relevant core action classes

## Downstream Impact

Forms are consumed directly by users and indirectly by tables/actions.

Be cautious with:

- action form wiring
- save lifecycle hooks
- notification behavior
- state naming and serialization
- shared field wrapper markup

## Tests To Run

Start with:

- `composer test:forms`

Then add:

- core tests if you changed action integration:
  `composer test:core`
- table tests if forms are used inside table actions or filters:
  `composer test:table`
- integration tests for runtime/state changes:
  `vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"`

Useful authored docs:

- `docs/forms/overview.md`
- `docs/forms/save-lifecycle.md`
- `docs/forms/validation.md`
