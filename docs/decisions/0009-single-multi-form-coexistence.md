# ADR 0009: Single/Multi-Form Coexistence

## Status
Accepted

## Context
The `WithForms` trait supports two patterns:
- **Single form:** Component has a `form(Form $form): Form` method → accessed as `$this->form`
- **Multi-form:** Component has `*Form(Form $form): Form` methods → accessed as `$this->profileForm`, `$this->settingsForm`

Should both patterns be allowed in the same component?

## Decision
**Combining `form()` and `*Form()` methods in one component is prohibited.** The `WithForms` trait throws `InvalidArgumentException` at initialization if both are detected.

Error message:
```
Component [App\Livewire\EditUser] cannot define both form() and *Form() methods.
Use either a single form() method or multiple *Form() methods, not both.
```

### Rationale
1. **Ambiguity.** If `form()` and `profileForm()` both exist, what does `$this->form` resolve to? The single form or a form named "form"?
2. **Simplicity.** Forcing one pattern per component makes code easier to read and maintain.
3. **Migration path.** If a component outgrows single form, rename `form()` to `mainForm()` and add other `*Form()` methods.

### Alternative: explicit `getForms()`
Users who want custom method names (not ending in `Form`) can override `getForms()`:
```php
protected function getForms(): array
{
    return ['profile', 'settings'];
}
```
When `getForms()` is overridden, auto-detection is skipped entirely.

## Consequences
- **Good:** No ambiguity in form resolution.
- **Good:** Clear error message guides migration.
- **Trade-off:** Users must choose one pattern upfront. Switching from single to multi requires renaming the method.
