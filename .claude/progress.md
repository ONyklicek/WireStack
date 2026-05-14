# Wire Ecosystem – Implementation Progress

## Current State
- **Working on:** Phase 5 – wire-forms FormKit API (in progress)
- **Last updated:** 2026-04-28
- **Tests:** Core+Table: 353 pass | Forms: 130 pass | Total: 483 pass, 0 fail ✅

## Overall Phase Status

| Phase | Status | Description |
|-------|--------|-------------|
| 0 | ✅ DONE | Monorepo & Infrastructure |
| 1 | ✅ DONE | wire-core Foundation |
| 2 | ✅ DONE | wire-core Modals |
| 3 | ✅ DONE | wire-core Notifications |
| 4 | ✅ DONE | wire-core Actions |
| 5 | 🔧 IN PROGRESS | wire-forms FormKit API |
| 6 | ⬜ NOT STARTED | Migrate wire-table to new forms |
| 7 | ⬜ NOT STARTED | Finalization |

---

## Phase 0: Monorepo & Infrastructure ✅ DONE
- Root composer.json with path repositories for core/forms/table
- CI workflows: tests.yml, static-analysis.yml, split.yml
- PHPStan level 6, Pint, .gitattributes
- Pest 3/4 as test framework
- monorepo-builder for splitting

## Phase 1: wire-core Foundation ✅ DONE
- Foundation/Concerns/: 22 traits (HasLabel, HasName, HasId, HasState, HasDefault, HasExtraAttributes, HasSize, HasHelperText, HasHint, HasTooltip, HasPlaceholder, HasPrefixAndSuffix, CanBeDisabled, CanBeReadOnly, CanBeLive, HasDebounce, HasIcon, HasColor, HasVisibility, HasColumnSpan, BelongsToComponent, HasLivewire)
- Foundation/Components/: Component, ViewComponent, LayoutComponent
- Foundation/Icons/: IconSet contract, IconManager, DefaultIconSet
- Foundation/Colors/: Color value object
- Foundation/View/: Icon, Badge, Button, Dropdown Blade components (`<x-wire::*>`)
- Foundation/Support/: EvaluatesClosures, ArrayDotHelper
- Foundation/Contracts/: HasIcon, HasLabel, HasVisibility interfaces
- WireCoreServiceProvider with modular boot (bootFoundation, bootActions, bootNotifications, bootModals)

## Phase 2: wire-core Modals ✅ DONE
- Modal, ConfirmationDialog, SlideOver, Wizard classes
- Contracts: ModalContract interface
- Concerns: HasModalProperties, HasFooterActions
- Blade components: `<x-wire-modals::modal>`, `<x-wire-modals::confirmation>`, `<x-wire-modals::slide-over>`
- Config: wire-core.modals.* (default_width, slide_over_width, close_on_click_away, close_on_escape)
- **82 tests, 162 assertions** – all passing

## Phase 3: wire-core Notifications ✅ DONE
- Notification (immutable VO), NotificationManager, InteractsWithNotifications trait
- Drivers: SessionDriver, LivewireEventDriver, FlasherDriver (guarded), NullDriver
- NotificationDriver contract
- Deprecated aliases: TableNotification, TableNotificationManager (BC → remove in v2.0)
- Blade: `<x-wire-notifications::toast-container>`
- Config: wire-core.notifications.default (session/livewire/flasher/null)
- **9 tests, 24 assertions** – all passing
- ⚠️ Low test count – missing driver-specific tests, Blade component tests

## Phase 4: wire-core Actions ✅ DONE
- 10+ action classes: BaseAction, Action, BulkAction, HeaderAction, ActionGroup, DeleteAction, DeleteBulkAction, EditAction, ViewAction, ModalFooterAction, ModalStep, ActionHalt
- 9 concern traits: HasColor, HasButtonStyles, HasDynamicProperties, HasIcons, HasKeyboardShortcut, HasLifecycle, HasLoadingState, HasModal, HasVisibility
- Contracts/HasForm.php – marker interface for form integration
- Blade: `<x-wire-actions::button>`, `<x-wire-actions::group>`, `<x-wire-actions::bulk-button>`
- HasLifecycle decoupled from Notifications (uses class_exists + late-static resolution)
- **214 tests, 350 assertions** – all passing
- Module isolation verified (no cross-module imports)

---

## Phase 5: wire-forms FormKit API 🔧 IN PROGRESS

### 5.1 Kostra & orchestrátor ✅ DONE
All core form infrastructure files exist:

**Config + Runtime separation:**
- `Forms/Form.php` – public API, Htmlable, fluent interface
- `Forms/Config/FormConfig.php` – immutable configuration
- `Forms/Config/ConfigBuilder.php` – internal builder from fluent calls
- `Forms/Runtime/FormRuntime.php` – runtime operation orchestration
- `Forms/Runtime/StateManager.php` – wire:model, state path, fill
- `Forms/Runtime/SaveHandler.php` – save lifecycle
- `Forms/WithForms.php` – Livewire trait (lazy resolution, cache, multi-form)
- `Validation/FormValidationResolver.php` – validation rules resolver
- `Rendering/FormRenderer.php` – Htmlable delegation
- `Integration/ActionMacros.php` – Action::macro('form', ...) registration
- `Contracts/HasForms.php` – optional marker interface
- `Contracts/HasValidation.php` – validation contract
- `Concerns/CanBeAutofocused.php`, `HasFormValidation.php`
- `WireFormsServiceProvider.php`

**Tests for orchestrator:**
- `Unit/Config/ConfigBuilderTest.php` ✅
- `Unit/Config/FormConfigTest.php` ✅
- `Unit/Validation/FormValidationResolverTest.php` ✅
- `Standalone/FormStandaloneTest.php` ✅

### 5.2 Field types – přepis na FormKit API

| # | Field | PHP Class | Blade | Tests | Status |
|---|-------|-----------|-------|-------|--------|
| 1 | Hidden | ✅ | ✅ | ✅ FieldTypesTest | ✅ DONE |
| 2 | TextInput | ✅ | ✅ | ✅ TextInputTest | ✅ DONE |
| 3 | Textarea | ✅ | ✅ | ✅ FieldTypesTest | ✅ DONE |
| 4 | Checkbox | ✅ | ✅ | ✅ FieldTypesTest | ✅ DONE |
| 5 | Toggle | ✅ | ✅ | ✅ FieldTypesTest | ✅ DONE |
| 6 | Radio | ✅ | ✅ | ✅ FieldTypesTest | ✅ DONE |
| 7 | CheckboxList | ✅ | ✅ | ✅ FieldTypesTest | ✅ DONE |
| 8 | Select | ✅ | ✅ | ✅ SelectTest | ✅ DONE |
| 9 | ColorPicker | ✅ | ✅ | ✅ FieldTypesTest | ✅ DONE |
| 10 | DateTimePicker | ✅ | ✅ | ✅ DateTimePickerTest | ✅ DONE |
| 11 | FileUpload | ✅ | ✅ | ✅ FieldTypesTest | ✅ DONE |
| 12 | RichEditor | ✅ | ✅ | ✅ FieldTypesTest | ✅ DONE |

### 5.3 Layout components

| # | Component | PHP Class | Blade | Tests | Status |
|---|-----------|-----------|-------|-------|--------|
| 13 | Grid | ✅ | ✅ | ✅ LayoutTest | ✅ DONE |
| 14 | Fieldset | ✅ | ✅ | ✅ LayoutTest | ✅ DONE |
| 15 | Section | ✅ | ✅ | ✅ LayoutTest | ✅ DONE |

### 5.4 Display components

| # | Component | PHP Class | Blade | Tests | Status |
|---|-----------|-----------|-------|-------|--------|
| 16 | Placeholder | ✅ | ✅ | ✅ DisplayTest | ✅ DONE |
| 17 | Alert | ✅ | ✅ | ✅ DisplayTest | ✅ DONE |
| 18 | Html | ✅ | ✅ | ✅ DisplayTest | ✅ DONE |
| 19 | ViewField | ✅ | ✅ | ✅ DisplayTest | ✅ DONE |

### 5.5 Action ↔ Form integration
- `Integration/ActionMacros.php` – ✅ exists

### 5.6 Notifications integration
- Via service container (app()->bound(NotificationManager::class)) – implementation in SaveHandler

### 5.7 Standalone verification
- `Standalone/FormStandaloneTest.php` ✅ exists

### Resources
- **Blade views:** 20 templates (form.blade.php, 14 components, 3 layouts, 2 partials)
- **Lang files:** en + cs (fields.php, messages.php)
- **Config:** wire-forms.php

### Fixed issues (2026-04-28)
1. **Html::make()** – Overrode `make()` to accept optional `?string $name` matching the constructor (static factories like `divider()`, `spacer()` don't need a name)
2. **Select::boolean()** – Wrapped `trans()` in try/catch with fallback to 'Yes'/'No' for standalone usage without translator service
3. **DateTimePicker::getFirstDayOfWeek() / getFormat()** – Added try/catch around `config()` calls with sensible defaults for standalone usage

---

## Phase 6: Migrate wire-table to new forms ⬜ NOT STARTED
- Wire-table adds hard dep on wire-forms ^0.1
- Delete NyonCode\WireTable\Forms\ → replace with NyonCode\WireForms\ imports
- ADR 0003: inline editing columns decision
- Update table tests & docs

## Phase 7: Finalization ⬜ NOT STARTED
- Per-package README
- docs/ per module/per field
- Migration guide with find/replace table
- CHANGELOG with Breaking Changes
- All ADR completed (0001-0012)
- Version 0.1.0, tag, split workflow verification

---

## ADR Status

| ADR | Title | Status |
|-----|-------|--------|
| 0001 | Action-Form Integration | ✅ Written |
| 0002 | JS/Alpine Distribution | ✅ Written |
| 0003 | Inline Editing Columns | ✅ Written |
| 0004 | Notification Driver Defaults | ✅ Written |
| 0005 | Tailwind 4 Support | ⬜ TODO |
| 0006 | Modular Core Extraction Strategy | ⬜ TODO |
| 0007 | Internal Module Dependencies | ⬜ TODO |
| 0008 | DateTimePicker Unification | ⬜ TODO |
| 0009 | Single/Multi-Form Coexistence | ⬜ TODO |
| 0010 | Form Save Notifications Integration | ⬜ TODO |
| 0011 | Form Config/Runtime Separation | ⬜ TODO |
| 0012 | Form::make() Standalone Usage | ⬜ TODO |

---

## Key Metrics

| Package | PHP files | Tests | Assertions | Status |
|---------|-----------|-------|------------|--------|
| core | ~94 | 305 | ~536 | ✅ All pass |
| forms | ~35 | 130 | ~229 | ✅ All pass |
| table | ~29 | 48 | ~60 | ✅ All pass |
| **Total** | **~158** | **483** | **~825** | **All pass ✅** |

## Cross-package dependencies
- **table → core:** Actions/*, Notifications/*, Concerns/HasColor, Foundation/View/*
- **table → forms:** (not yet migrated, Phase 6)
- **forms → core:** Foundation/Components/Component (base class)
- **No circular deps** ✅

## Next Steps (Priority Order)
1. Write ADRs 0005-0012
2. Phase 6: Migrate wire-table to new wire-forms
3. Phase 7: Finalization, docs, migration guide, v0.1.0 tag
