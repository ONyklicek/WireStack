# ADR 0002: JS/Alpine Distribution

## Status
Accepted

## Context
The Wire ecosystem uses Livewire 3 for reactivity and Tailwind CSS for styling. Some interactive UI elements (dropdowns, modals, keyboard shortcuts) use inline Alpine.js directives in Blade templates.

## Decision
No custom JavaScript files are shipped with any package. All interactivity is handled via:

1. **Livewire 3** – reactive updates, form submission, action execution.
2. **Inline Alpine.js** – small directives in Blade templates (`x-data`, `x-show`, `x-on:click`, `@keydown`).
3. **Livewire events** – `$dispatch()` for cross-component communication.

Each package's Blade views contain their own Alpine.js directives. There is no shared JS bundle or asset pipeline to manage.

## Consequences
- **Good:** Zero build step for JS. No npm dependencies. No asset publishing.
- **Good:** Each package is self-contained – its views include everything needed.
- **Trade-off:** Complex interactions are harder to implement without a shared JS layer. If needed in the future, a `wire-ui` package could provide shared Alpine.js components.
