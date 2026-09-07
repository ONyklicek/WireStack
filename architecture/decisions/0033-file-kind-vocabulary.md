# ADR 0033: One Vocabulary For What A File Is

## Status

ACCEPTED — 2026-09-07, implemented the same day. Requested by the repo owner
while reviewing the media UI proposal ("náhledy obsahu pokud lze jinak označení
příponou"), and placed in `core` by the owner's own call when the alternative —
a media-local enum — was put beside it.

Phase 1 of [`plans/media-manager-ui.md`](../plans/media-manager-ui.md).

## Context

Six places answer "what does this file look like" and all six answer it the same
naive way — an image, or one grey `outline:document`:

| Where | Converted |
| --- | --- |
| `forms/resources/views/components/file-upload.blade.php` | yes — the field's file list |
| `module-media/resources/views/manager.blade.php` | yes — the grid tile and the list row |
| `module-media/resources/views/components/media-field.blade.php` | yes — the picked-file thumbnails |
| `forms/resources/views/partials/file-upload-avatar.blade.php` | **no** — an avatar is one picture of one person, and a type card in a round frame is not a better answer than the placeholder it already has |
| `module-media/src/Resources/MediaResource.php` | **no** — an `ImageEntry` in an infolist, whose documented behaviour is to show nothing rather than draw a PDF through `<img>`. Converting it means a new entry type, not a new vocabulary |

A folder of a catalogue PDF, a price list, a contract and a print-ready archive
renders as four identical grey rectangles. Three of those five surfaces are
converted here; the last two are listed with the reason they stay as they are,
because "every call site" was the first draft's claim and it was wrong. The information needed to tell them
apart is already on the row — `mime_type` and the file's own name — and nothing
reads it.

This is the shape CLAUDE.md § Architectural Invariants names outright: a
capability shared across packages and component types, currently re-encoded
locally in each. The colour vocabulary it needs already exists
(`Foundation/Colors/Color.php`, `Foundation/Concerns/HasColor.php`, the complete
Tailwind palette as literal class strings), and so does the icon one
(`Foundation/Icons`, `Foundation/Support/IconResolver.php`).

## Decision

### 1. A closed enum in the lowest layer that can own it

`packages/core/src/Foundation/Enums/FileKind.php` — a backed enum whose cases are
**families**, not formats:

```
Image  Video  Audio  Document  Spreadsheet  Presentation  Archive  Code  Other
```

Each case answers three questions and nothing else: `color(): Color`,
`icon(): string`, `label(): string`. The colour is a `Color` case, so no new
class string enters the codebase and ADR 0005's Tailwind support policy is
untouched.

It sits in `core` rather than in `module-media` because two packages already have
the problem and only one of them is the media module. A media-local `MediaKind`
would leave `wire-forms` with its grey icon and produce the second wheel the
repo's own note warns about — an abstraction that diverges from its copy inside
one commit.

**Closed, with an `Other` case.** Not a config map: a vocabulary an application
can rewrite is one where a downstream package cannot rely on `Spreadsheet`
meaning a spreadsheet. Everything unmatched is `Other`, which renders as the
extension on a neutral ground and is a perfectly good answer.

### 2. The mime type decides; the file name is the fallback

`FileKind::for(?string $mime, ?string $name = null)`.

The mime type is read from the disk on upload rather than from what the browser
claimed, so it is the trustworthy input and it goes first. The name is consulted
in exactly two cases, both real:

- **`mime_type` is null** — rows written before it was recorded, and rows on a
  disk that could not answer.
- **The mime type is too generic to mean anything** — `application/octet-stream`
  for most archives, `text/plain` for a `.csv` that is a spreadsheet to every
  person who opens it.

### 3. The extension is not the enum's job

The enum answers the family. The **wordmark on the tile is the file's own
extension**, taken from its name: `XLSX` and `ODS` are both `Spreadsheet` and
must not both read "XLSX". A file whose name has no extension shows the family's
label instead.

### 4. One render helper, not six conditionals

A Blade component in core — `<x-wire::file-thumb :kind :url :name>` — draws the
image when there is one and the type card otherwise. The six call sites above
each lose their `@if`, and a seventh surface that needs a file thumbnail gets it
by asking rather than by copying.

This is the "owner-facing render helpers for Blade/views" half of the
canonical-ownership rule; the enum alone would leave every surface to compose the
same markup.

## Consequences

- **`wire-forms` changes**, and it is a package below the one that asked for
  this. Deliberate, and the reason the enum is in `core` at all: an upload of a
  PDF now says PDF in the field as well as in the library.
- **A null mime type now resolves by extension**, where it previously resolved to
  the grey icon. A behaviour change on old rows, and the better one.
- **`Media::isImage()` stays.** It answers a different question — whether there
  are pixels to show — and half its callers want that rather than a family.
  `FileKind::Image` is derived from the same fact and the two must not drift:
  `isImage()` becomes `FileKind::for(...) === FileKind::Image`, one owner again.
- Testing is a table: `core/tests/Unit/Foundation/Enums/FileKindTest.php` over
  mime → kind including both fallback cases,
  `core/tests/Unit/Foundation/View/FileThumbTest.php` over what the component
  draws, and three assertions in `module-media/tests/Feature/MediaLibraryTest.php`
  that the wordmark reaches the grid. The three media browser drivers and the
  three image ones pass unchanged — the markup around the `@if` moved, and
  Livewire still morphs the grid the same way.

## Alternatives Rejected

**A media-local `MediaKind`.** Smaller blast radius, and it leaves the same
grey icon in `wire-forms` — where a person uploading a contract sees exactly the
ambiguity this ADR exists to remove.

**A config-driven map of mime patterns.** Flexible, and it makes the vocabulary
unreliable for any package that consumes it. An application that needs a family
this list does not have is describing a new case, and a new case is a pull
request, not a config key.
