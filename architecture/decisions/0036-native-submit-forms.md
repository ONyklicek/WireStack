# ADR 0036: Native Submit — wire-forms Renders the Signed-Out Screens, Fortify Still Posts Them

## Status

ACCEPTED — 2026-09-09. Requested by the repo owner, who put the question as
"využít výhod WireStack → forms a zároveň fortify" and then "pust se do toho".

**Amended 2026-09-10**, on the owner's follow-up — *"modul auth a plné využití
wireForms zjednodušit úpravy formulářů"*. The two items this ADR left open under
"Not decided here" are now decided the other way: **all six remaining screens are
migrated**, and `Checkbox` and `Hidden` implement the contract. The mode is also
applied one step later than it was, and a `Hidden` field's rules run for the
first time. See [§7](#7-every-screen-not-only-the-sign-in-one) and
[§8](#8-the-mode-is-applied-to-the-schema-that-renders-not-the-one-declared);
§6's rule is unchanged and is what §7 satisfies, because the migration is what
named the consumers. [§10](#10-the-dependency-was-never-the-question) records what
this ADR argued too hard about, on the owner's reading: the dependency.

Sits under [ADR 0032](0032-authentication-surface.md), whose §1 it must not
weaken: Fortify owns authentication and this repository owns none of it. It
also depends on [ADR 0024](0024-js-asset-delivery-and-registration.md) being
true — the whole decision rests on wireStack already having put Alpine in the
signed-out document.

## Context

`wire-module-auth` ships seven Blade screens whose inputs are hand-written
markup. Changing a field on one of them means publishing views and editing HTML,
and that friction is what prompted this.

Underneath it was a duplication that had already gone wrong. Fourteen views
across five packages spelled out the same text-control class vocabulary, and the
signed-out field's copy had drifted from `wire-forms`' — it carried `mt-1` and
put `text-sm` in a different group. That half is fixed separately:
`Foundation\Support\TextControl` in `wire-core` now owns the vocabulary and both
views read it. This ADR is about the other half — the *shape* of a field, not
its classes.

The obvious move, "use `wire-forms` on the auth screens", looked blocked. Three
measurements say it is not — though the first two answer a question the package
graph had already settled, which the 2026-09-10 amendment records in
[§10](#10-the-dependency-was-never-the-question):

1. **`wire-core` already requires `livewire/livewire ^4.0`.** So
   `wire-module-auth` already has Livewire in its dependency tree, transitively.
   Adding `wire-forms` adds one first-party package and no new external
   dependency. The "auth is Livewire-free" property is about what runs, not about
   what composer resolves.
2. **The signed-out layout already emits `@wireStackScripts`**
   (`wire-admin::auth-layout`). Every Alpine controller in the stack is in that
   document before the page paints.
3. **The fields these screens need are Alpine-driven, not Livewire-driven.**
   Counted in their views:

   | field | `wire:model` | `wire:click` / `wire:loading` | `x-data` / `x-mask` |
   |---|---|---|---|
   | `text-input` | 1 | 0 | 3 |
   | `checkbox` | 1 | 0 | 0 |
   | `otp-input` | 0 | 0 | 1 |

   (Only `text-input` is implemented here — see §6.)

   The only Livewire coupling is the binding attribute. The behaviour a login
   screen actually wants — the password reveal toggle, input masks, the OTP
   boxes — is Alpine, and (2) already delivered it.

So a `wire-forms` field on a plain POST form is not a degraded field. It is the
same field with a different binding, and for this surface nothing is missing.

What genuinely does not survive the loss of Livewire is anything needing a
round-trip mid-form: `live()` and reactive fields, `Select` with server-side
search, `FileUpload` (Livewire temporary uploads), `Repeater`, `Builder`.

## Decision

### 1. `wire-forms` grows a native-submit render mode

`Form::nativeSubmit()` switches the fields: they carry `name=` and
`value=old(…)` instead of `wire:model`. Errors come from the shared `$errors`
bag, which is where Fortify already puts them.

**The `<form>` element is not part of it.** The action, the `@csrf` and the
submit button stay with the screen doing the rendering — a sign-in page owns its
own chrome, and a form object that emitted a `<form>` tag would be deciding
where the page posts. This switches fields and nothing around them.

The native binding is resolved **in PHP** and echoed as one escaped string, the
same shape `getExtraInputAttributesHtml()` already has. Only that branch moves:
the Livewire binding stays in each view, because the views do not build it
identically — a text input appends its debounce modifier and a checkbox does
not — and one owner for that branch would have changed what the checkbox
renders.

### 2. A field opts in, and a field that cannot opt in throws

Support is a contract — `Contracts\SupportsNativeSubmit` — not an assumption.
A schema in native mode containing a field that does not implement it raises
`FormConfigurationException::cannotSubmitNatively()`, naming the field and its
type. A named constructor on the existing exception rather than a new class:
that exception already says "a form was asked to do something its definition
does not support", which is this exactly.

The check walks the **declared schema**, not the form's flat component list.
`FormRuntime::flattenComponents()` skips a `Repeater` deliberately, so a
repeater — one of the fields that most certainly cannot submit natively — would
never reach a guard written over the flat list.

**This is the load-bearing half of the decision.** A `wire:model`-only input
dropped into a native form has no `name`, so the browser posts nothing for it,
and the failure is a login form that submits an empty password with no error
anywhere. A silent no-op here is worse than an exception at render, so it is an
exception at render.

### 3. Fortify keeps everything it has

The route, the credential check, the throttle keyed on address and IP, the
session regeneration, the hand-off to the two-factor challenge. The form posts
to Fortify's own URL exactly as the hand-written markup did. ADR 0032 §1 is
untouched: this package still answers view callbacks and authenticates nothing.

### 4. Signing in keeps working with JavaScript off

A consequence of choosing native submit over a Livewire login component, and
worth stating because it is the reason for that choice rather than a side
effect. Alpine enhances these screens; nothing on the critical path needs it.

### 5. `wire-module-auth` takes a dependency on `wire-forms`

This changes the package graph in `CLAUDE.md` and `AI_BLUEPRINT.md`:
`wire-module-auth -> wire-core + wire-forms + laravel/fortify`.

### 6. It grows by named consumer, not by symmetry

[ADR 0030](0030-hook-surface.md) §6 applies unchanged. `TextInput` implements
the contract and nothing else does, because the sign-in screen's credentials are
the only named consumer. `Checkbox` was written and then taken back out: the
"remember me" control is a bespoke inline row beside the reset link, which a
field wrapper does not reproduce, so implementing it would have added public API
with no caller — the exact thing this rule forbids.

**Amended 2026-09-10 — `Checkbox` and `Hidden` are in, with callers.** The paragraph above
still reads the way it did, because the reasoning was right at the time: what
changed is not the rule but the facts under it. Migrating the remaining screens
made "stay signed in" a field in the login schema, and opened a seam
(`AuthForms::extend()`) whose most obvious first use — a "terms" box on the
register form — would have raised without it. `Hidden` arrived the same way: the
reset screen's token is a value the browser has to carry, and §7 says why the
field that exists for exactly that could not do it. `OtpInput` followed once the
challenge asked for boxes — three fields, three named consumers, and nothing
implemented for symmetry.

### 7. Every screen, not only the sign-in one

The six that were parked now render from `Forms\AuthForms`, and the hand-written
`partials/field.blade.php` is gone: nine views ship instead of ten, and none of
them contains an input. What each screen keeps is its chrome — the `<form>`, the
`@csrf`, the submit button, the links — which is §1 unchanged.

**The registry, not seven static factories.** `AuthForms` is a container
singleton (the shape `PageChrome` already has) holding one builder per form and
an `extend(AuthForm, Closure)` seam: the fields as declared go in, what should
render comes out. That seam is the point of the whole amendment — *"zjednodušit
úpravy formulářů"* — because without it "the fields are in PHP now" only moves
where the package writes them, and an application still publishes a view to add
one. `Hook::FormConfiguring` fires as well (every form dispatches it), but its
`HookTarget` carries a Livewire component and a model, and these forms have
neither — so it cannot tell the login form from the register one, and a screen-
scoped seam is not a second answer to a question the hook already answers.

**Seven cases for six screens.** `AuthForm::TwoFactorCode` and
`TwoFactorRecovery` are separate: one `<form>`, one URL, a different field
depending on what the person has to hand, and replacing one is no reason to
inherit the other. `verify-email` gets no case — a button and no fields.

**The notices are the panel's own surface too.** The status Fortify flashes and
the error summary were hand-written green and red Tailwind in `screen.blade.php`
— the same second opinion `TextControl` had just been extracted to end, in the
one place a user compares the two sides of the door. Both are now
`<x-wire::callout>`, the component the panel behind the door already uses. The
screen keeps the spacing and the hook name, because those are its own.

**No input stayed markup, and `Hidden` is why.** The reset token was written
into the view first, on the reading that `Hidden` was broken: it marks itself
`hidden()` in its constructor, so every render loop skips it and it emits no
element. Its own documentation says exactly that, and says it deliberately — the
value lives in **form state**, filled in PHP and carried in the Livewire
snapshot, so there is nothing for the DOM to hold. Not a bug: a field whose name
means what it says.

What that reading missed is that the reasoning ends where Livewire does. A
native-submit form has no snapshot; the browser posts what is in the document
and nothing else. So a `Hidden` in one carried *nothing at all* — the same
silent-empty-value failure §2 refuses, arrived at from the other direction. The
fix is one condition: `Hidden::isVisible()` is true while `submitsNatively()` is,
and the view gains the native branch every other field has. The constructor's
promise — "the user has no business choosing this" — is untouched; what changes
is where the value is allowed to live. The token is now a field like the rest,
and `@csrf` is the only thing in these views that is not.

**`Checkbox` implements the contract, and so — after a second look — does
`OtpInput`.** "Stay signed in" is
now a field rather than a bespoke inline row, which is what §6 was waiting for —
a named consumer — and it also closes the trap the seam would otherwise have: a
register form's obvious second field is a "terms" checkbox, and without this it
would have raised. Its binding is its own, because a checkbox does not carry
state in `value`: it posts a constant and answers with its *presence*, and the
tick is decided by whether there is any old input at all (`old()` alone cannot
tell "unticked" from "fresh page", so a box defaulted on would re-tick itself
after a user cleared it).

`OtpInput` was parked here as "a consumer, but not a cheap one": its boxes carry
no `name` and its controller writes through `$wire.set()`, so it needed more than
a view branch. It is in now, and the design is what made it cheap after all —
**the boxes are the enhancement, not the field.** Native mode renders one real
text input beside them with the field's name on it, bound `:value="digits.join('')"`,
which is the whole write path: the controller never learns the element exists and
only stops touching `$wire`. Alpine hides that input with `x-show="false"` rather
than the view rendering `type="hidden"`, and cloaks the boxes until it boots — so
a browser that never runs Alpine shows a plain input with the code's name on it.
§4 survives intact on the one screen that could have broken it.

### 8. The mode is applied to the schema that renders, not the one declared

`toHtml()` used to switch the fields of `getSchema()` — the declared array —
before rendering. But a form's *rendered* schema is what `form.configuring`
leaves behind, and that hook may add fields. So a plugin's field went out bound
by `wire:model`, with no `name`: the input that looks right and posts nothing,
which is the exact failure §2 exists to refuse. The switch now happens in
`configuredSchema()`, immediately after the hook, and `nativeSubmit()`
invalidates the memoized config so a form switched after something read its
config still reaches its fields.

### 9. A `Hidden` field's rules now run

Found while deciding whether `Hidden` could carry the token, and unrelated to
native submit: `FormValidationResolver` skips components whose `isVisible()` is
false, which is right for a `visibleWhen()` sibling — a `required()` on a field
the user cannot see must never block a submit — and wrong for this one. A
`Hidden` is invisible by design, and its value is still filled, still carried in
a snapshot the browser can edit, and still written on save. Its rules were the
only thing standing between the record and whatever came back, and they had
never run: `Hidden::make('type')->rules(['in:post,page'])` — the form the field's
own documentation recommends — validated nothing.

The resolver now excludes every invisible component except a `Hidden`. This is a
behaviour change: an application whose hidden field would fail its own rules
starts seeing that failure, which is the point.

### 10. The dependency was never the question

Recorded on the owner's reading of this ADR: *"co adr neřeší… modul už je
vrstvena nad wireStackem a rozšiřuje admin, tam je použití wireForms v pořádku."*

Context (1) and (2) above read as a defence of taking a dependency, and that
defence was never needed. `wire-module-auth` sits **on top** of the stack and
extends the admin; a package in that position uses the stack's forms the way
every other surface does. The rule the Context was arguing against turned out not
to exist in any decision — it lived in one sentence of `AI_BLUEPRINT.md` that no
module had ever obeyed. It is now written properly, and scoped, in
[ADR 0029 §5](0029-modules-as-installable-packages.md#5-what-a-package-may-depend-on-and-where-that-stops-mattering):
edges point down inside the stack, and above the line the stack is fair game.

What (3) measures is the real question, and the only one this decision had to
answer: whether a `wire-forms` field still works with no Livewire component under
it. That is what §1 and §2 are for. Everything else in the Context is background.

## Consequences

### Positive

- Adding a field to a signed-out screen is a closure in a provider plus the
  Fortify action the application already owns, instead of publishing a view and
  owning it forever.
- One owner for how a field looks and behaves, on both sides of the sign-in
  door. The drift `TextControl` just fixed cannot recur in a new shape.
- The screens gain masks, the reveal toggle and field-level validation messages
  without this package writing any of it.

### Negative / risks

- **A partly-implemented mode is a trap**, which §2 exists to close. The guard
  is the feature; without it this decision would be a bug generator.
- One more package in `wire-module-auth`'s tree. Cheap (see Context 1), not free.
- `Form` grows public API that only makes sense outside Livewire, which is a
  second mode to keep in mind when changing form rendering.

### Not decided here

- Native mode for the remaining 28 field types. None has a consumer; the three
  that do are the three the signed-out screens are made of.
- Whether `wire-panels` or any signed-in surface would ever want this. Nothing
  suggests it does.
