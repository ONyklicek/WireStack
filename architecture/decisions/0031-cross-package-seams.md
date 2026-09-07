# ADR 0031: Two Seams a Package Above You Cannot Reach — Page Chrome and Fields That Save Themselves

## Status

ACCEPTED — 2026-09-06, implemented the same day.

Follows from [ADR 0029](0029-modules-as-installable-packages.md), which ruled that
a module arrives as a package and may only **add**. That ADR answered how a module
registers itself with the layers *below* it. This one answers the two cases where
adding requires something from a layer **above** — and where the package graph
makes naming it impossible.

Written after the media module grew a picker, because both problems appeared in
one afternoon and neither had a mechanism.

## Context — measured 2026-09-06

### The graph runs one way, and two features need it to run both

```text
wire-admin  ──> wire-panels ──> wire-table ──> wire-forms ──> wire-core
wire-module-media ──> wire-panels
```

`wire-module-media` sits beside `wire-admin`, not under it. Nothing in the module
may name a class or a view in the shell, and nothing in the shell has ever heard
of the module. That is the property three ADRs were written to protect, and it is
correct.

Two things the media picker needs run straight into it.

**1. A modal has to be in every page.** A picker opened from a form, from a rich
text editor, from a table row action, is opened from something that will be
re-rendered — so the modal cannot live inside it. It has to be rendered once, by
whatever draws the page. That is `wire-admin`'s layout, which cannot name
`wire-module-media::picker-modal`, and the module cannot reach into the layout to
put it there.

The workarounds available before this were all worse than the problem: publish
the shell's layout and edit it by hand (an application that can never take an
upgrade), require the module from the shell (inverting the graph for one modal),
or make every caller mount its own copy (several modals on a page, all listening
for the same event, all answering).

**2. The rich text editor cannot know the library exists.** `TiptapEditor` lives
in `wire-forms`, four layers below the media module. Its image button asked for a
URL with `prompt()`. Making it open the library would mean `wire-forms` requiring
`wire-module-media` — the inversion again, and this time for a package that most
applications install without any media module at all.

### A field whose name is not a column

`MediaField::make('gallery')` writes to a pivot, not to a `gallery` column. Left
in the data, it dehydrates an attribute that does not exist and fatals.

`SaveHandler` already knew this shape three times over:

```php
foreach ([...$this->relationshipRepeaterNames(), ...$this->tagsRelationshipNames()] as $name) {
    unset($data[$name]);
}

foreach ($this->morphToSelectFields() as $field) {
    unset($data[$field->getName()]);
    // …and put the two real columns back
}
```

Three special cases, enumerated by class, in a method inside `wire-forms`. A
fourth was not available to a field defined in another package: `wire-forms`
cannot import `MediaField` any more than it can import the library.

## Decision

### 1. `Foundation\View\PageChrome` — a registry of views the page renders once

Neither end names the other. A package registers a view name; whatever draws the
chrome renders whatever is registered.

```php
// in the module's service provider, at boot
app(PageChrome::class)->add('wire-module-media::picker-modal');
```

```blade
{{-- in wire-admin's layout --}}
@foreach (app(PageChrome::class)->views() as $chromeView)
    @include($chromeView)
@endforeach
```

It lives in `Foundation/` (L0) because it is the lowest layer that can own it and
it imports nothing. It holds **names, not markup**: rendering happens in the
request, where a view can see the current user and the current locale — neither of
which is true at boot.

`add()` is idempotent by name. A provider runs twice more often than anyone
expects — a package required by two others, a test that boots the app again — and
two copies of one modal in a document means two things listening for one event and
both answering.

**Amended 2026-09-06 — two regions, still one registry.** `wire-module-users`
needed a team switcher in the *top bar*: same dependency problem, different
answer to "where". A modal only has to **exist**, so it goes at the end of the
document; a switcher has to be **seen**. `add($view)` still means `BODY`, and
`add($view, PageChrome::TOPBAR)` is the other one. Two registries would have been
two answers to one question, and the second would have grown the same
deduplication, the same idempotence and the same ordering caveat below.

```php
app(PageChrome::class)->add('wire-module-users::team-switcher', PageChrome::TOPBAR);
```

The registered view is where the *whether* lives, not the registry: the team
switcher's view renders nothing at all unless this installation has teams and
this person belongs to more than one. A top bar without a switcher, rather than a
switcher with one option in it.

An application rendering its own layout adds the same three lines. One that
renders neither has no chrome, which is a page without a picker rather than a page
that breaks.

### 2. A cancelable DOM event is how a lower layer offers work to a higher one

The editor cannot call the library. It can *offer* the job and see whether anyone
takes it:

```js
const request = new CustomEvent('wire-media-picker:open', {
    cancelable: true,
    detail: { token, multiple: false, accepts: 'image/' },
});

window.dispatchEvent(request);

if (! request.defaultPrevented) {
    // nobody is listening — do exactly what this did before
}
```

The listener claims it with `preventDefault()`. The caller's check is synchronous,
so there is no timeout, no registry, no configuration, and no dependency pointing
the wrong way. The answer comes back on `wire-media-picker:picked`, carrying the
token it was opened with so two pickers on one page cannot cross.

**The fallback is the feature.** An application with no media module gets the URL
prompt it always had. That is what makes this safe to put in a package four layers
down: the offer costs nothing when it is refused.

### 3. `Contracts\SavesAfterRecord` — declared, not enumerated

```php
interface SavesAfterRecord
{
    public function saveAfterRecord(Model $record, mixed $state): void;
}
```

`SaveHandler` removes such a field's name from the data before writing the record,
and calls it afterwards — after, because a new record has no key until it is
saved, and a pivot row needs that key. It is given the **raw state**, because these
fields carry no validation rule of their own and `validate()` drops what it was
not asked about.

The three existing special cases are deliberately **left as they are** for now.
Converting them is a refactor of a hot file with no functional change, and this
ADR is not the place to spend that risk; the contract is what the fourth case
needed, and the fourth case is in another package. They should adopt it when one
of them is next touched for its own reasons.

### 4. Failing here fails the save

A field that cannot write its own value does not swallow it. A gallery that
silently did not attach is worse than a save that says it did not finish, because
the first is discovered by somebody looking at the page a week later.

## Consequences

### Positive

- A module can put a modal, a banner or a listener on every page without the shell
  knowing it exists, and without an application publishing a layout it can never
  upgrade.
- `wire-forms` gained a media picker without gaining a dependency. Any other
  package can claim the same event.
- A field defined anywhere can persist to a relation. The rule is declared on the
  field rather than listed in `SaveHandler`.
- `PageChrome` is not media-specific. The toast container and the command palette
  are the same shape and could move onto it.

### Negative / risks

- **`PageChrome` is a global list with no ordering and no scoping.** Every
  registered view renders on every page the shell draws — in both regions. That is right for a modal
  and wrong for anything expensive; a package that registers a heavy view will
  make every page slower, and nothing here prevents it. The `x-if` around the
  media picker's own component is the pattern to copy, not an implementation
  detail — the modal is a few elements until it is opened.
- **The event contract is convention, not a type.** A typo in
  `wire-media-picker:open` fails as "nobody claimed it", which looks exactly like
  the module not being installed. Only the browser driver
  (`verify-media-picker.mjs`) can tell the two apart, which is why that driver
  asserts the *absence* of the prompt fallback rather than only the presence of the
  modal.
- **`SavesAfterRecord` runs after the record is saved and is not in its
  transaction** unless the caller wrapped one. A field that throws leaves a saved
  record with an unwritten collection. Documented; a transaction around the whole
  save is the application's to open.
- Three enumerated cases in `SaveHandler` still do by hand what the contract now
  expresses. Two ways to say one thing, until they are converted.

## Related

- [ADR 0028](0028-optional-panel-shell.md) — why the shell is a package above the graph
- [ADR 0029](0029-modules-as-installable-packages.md) — a module adds, never overwrites
- [ADR 0030](0030-hook-surface.md) — the PHP-side interception this sits beside
- `docs/modules/media.md` § Using the Library From Everywhere Else
