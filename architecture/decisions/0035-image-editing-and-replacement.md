# ADR 0035: Editing An Image, And Replacing An Original

## Status

ACCEPTED — 2026-09-07, implemented the same day. Requested by the repo owner
("možnost editovat obrázky"), with two calls made by the owner directly:

- **replacement is offered on every file, behind a warning** — chosen over
  offering it only where nothing links to the file, and over never offering it;
- **where the pixels are recomputed was left to this ADR** ("co bude nejlepší?").

Depends on [ADR 0034](0034-where-a-file-is-used.md): the warning this feature
rests on is a lie until the library knows where a file is used. Phase 8 of
[`plans/media-manager-ui.md`](../plans/media-manager-ui.md).

## Context

`docs/modules/media.md` states the current position:

> **Editing a file is deliberately absent.** Replacing the bytes under a path
> other records already point at is how a library quietly changes what a
> published page shows, so a new file is a new row.

The danger in that sentence is real and this ADR does not dispute it. What
changed is that the owner weighed it against the expectation every person
arriving from WordPress carries — that a crooked photograph can be straightened
where it lives — and chose to take the risk with a warning in front of it.

That choice is only defensible if the warning is true, which is why 0034 comes
first.

## Decision

### 1. The pixels are recomputed in the browser

This is the call left open, and the deciding fact is in the code rather than in
the abstract:

```php
// packages/module-media/src/Actions/MakeThumbnail.php
$source = $storage->path((string) $media->path);
if (! is_file($source)) {
    return false;   // not a local disk, or not there
}
```

**A server-side editor inherits that.** The module already declines to pull a
file back down from S3 to make a thumbnail, and for the same reason — a
convenience that downloads every file twice is not one. An editor built on the
same seam would be an editor that silently does not exist on remote disks, which
is the configuration a large library is most likely to be on.

The browser has the opposite property: it reads the image through a URL, so every
disk that can produce one can be edited. And the code is already written —
`packages/forms/resources/js/image-processor.js` crops to a ratio, places the
frame interactively and downscales, dependency-free, in a canvas.

So: **the editor is that module, extended, and never a second copy of it.** It
gains `rotate` (90° steps), `flip`, a free-ratio crop, and stepped downscaling —
halving repeatedly rather than one `drawImage` from 6000px to 400px, which is
where canvas resampling visibly falls apart. Every one of those improves
`FileUpload` at the same time, because it is the same function.

Saving hands the resulting `Blob` to `StoreUpload`, unchanged. Hashing,
duplicate refusal, metadata read from the disk and the thumbnail are all already
written and already tested; the editor adds no save path of its own.

### 2. The source is read through the module's own route, always

A canvas that has drawn a cross-origin image refuses `toBlob()`. On a public S3
bucket or a CDN, that is exactly what would happen — after the person had
finished composing their crop.

So the editor does not read the image from `url()`. It reads it through the
module's streaming route, which is same-origin by construction. The route exists,
it is already policy-checked, and it currently answers only for unpublished
disks; this widens it to "the editor's source, on any disk".

The cost is one file's bytes through PHP per editing session, and it buys an
editor with no configuration-dependent failure mode. Where the route is switched
off, the editor is offered only for same-origin disks and says so rather than
failing at the end.

### 3. Two outcomes, two buttons, never a checkbox

**Uložit jako nový soubor** — a new row, `derived_from_id` pointing at the
original, filed in the same folder. The original is untouched.

**Nahradit originál** — the same row, the same path, new bytes.

Both are always present. A checkbox that changes what a Save button does is how
a person replaces a published photograph while believing they exported a crop.

### 4. The warning names the uses; it does not ask "are you sure?"

Replacement is confirmed by a dialog that says what will change, from ADR 0034's
data: the count of known uses and the first few of them by record and key, with
the sentence that the count is a floor.

"Opravdu?" is clicked through. "Tenhle soubor je ve 3 známých použitích:
Post #12, Post #40, Product #7" is read.

Where the count is zero, the dialog still appears and says so — including the
floor caveat, because zero known uses is the one case where the caveat carries
all the weight.

### 5. Replacement keeps the path, and the URL gains a version

The bytes are written **to the same path**. That follows the decision the folder
tree is already built on: moving bytes changes the URL of a file a published page
links to, and a broken image is not worth a tidier bucket. Replacement that
changed the path would break every hand-written `<img src>` at once — the very
uses ADR 0034 admits it cannot see.

Same path with different content is what caches get wrong, so `url()` gains a
version query derived from `updated_at`. A CDN and a browser both see a new
address; the file has not moved.

### 6. A replaced row is re-read, not assumed

After a replace: `size`, `width`, `height` and `checksum` are re-read from the
disk, and the thumbnail is remade with `force: true` — the flag the backfill
command already passes. Anything less leaves the row describing a file that is no
longer there, and leaves the grid showing the old picture.

### 7. Replacement asks the policy a question of its own

`MediaAccess::allows('replace', $media)`, falling back to `update` when the
registered policy has no `replace` method. Overwriting the bytes behind a
published URL is materially larger than renaming a row, and an application should
be able to allow one and refuse the other.

The fallback is what keeps the module's existing promise: a policy written before
this feature existed keeps working, and a library with no policy still refuses
nothing.

## Consequences

- **A migration**: `derived_from_id`, nullable, `nullOnDelete`. Deleting an
  original must not delete a crop that is itself in use — the derivative is a
  file, not a view of one.
- **The media module gets its first JS bundle.** It has none today; the editor
  brings `wire-media-editor.js`, which imports from `wire-forms`' image module.
  ADR 0024 governs how it registers, and `architecture/assets.md` lists the traps.
- **Browser memory bounds the editor.** A 40MP source is a ~160MB canvas, and a
  phone will not do it. The working canvas is capped, and above the cap the
  editor says what it will not attempt instead of crashing the tab.
- **SVG is not editable** and the button is not offered for it — there are no
  pixels to resample, and `processImage` already returns the file untouched.
- The browser drivers are the only gate over any of this
  (`npm run verify:drivers`): a crop that produces a derivative, and a replace
  that leaves the row's dimensions matching the new bytes.

## Residual Risk, Stated Plainly

**There is no undo.** A replaced original is gone, and every use of it — known,
unknown, published, cached — shows the new picture. The warning makes that
visible; it does not make it reversible.

Keeping the previous bytes for a retention window would make it reversible, and
it is a storage policy, a cleanup job and a restore UI. Recorded here as the
known answer, deliberately not taken now, so that the next person to feel this
gap finds it already thought through rather than open.

## Alternatives Rejected

**Non-destructive edits stored as a recipe and rendered on demand.** The right
answer with a CDN in front of the disk, and without one it re-renders on every
request — while the reason this whole plan exists is page load time.

**Server-side rendering through an extended `MakesThumbnails`.** Better
resampling with Imagick and no cross-origin concerns, at the cost of an editor
that does not work on the disks §1 quotes — and a new contract, route and job to
maintain beside the browser code that would still be needed to draw the frame.

## As Built

| Piece | Where |
| --- | --- |
| Crop, rotate, flip, stepped downscale, format | `forms/resources/js/image-processor.js` — `processImage()`, extended |
| The frame, the fetch, the upload | `module-media/resources/js/editor.js` → `dist/wire-media-editor.js` |
| The modal | `module-media/resources/views/partials/editor.blade.php` |
| New bytes under an existing row | `module-media/src/Actions/ReplaceOriginal.php` |
| Facts read from the disk, once | `module-media/src/Support/StoredFileFacts.php` |
| The two outcomes | `MediaManager::updatedEditorUpload()` |
| The ability | `MediaAccess::allows('replace', …)`, falling back to `update` |
| The column | `update_wire_media_table_add_derived_from.php` |

Four things landed differently from the decisions above, and each is worth
knowing:

**§2 is now split in two, and it is better for it.** Reading a picture into an
`<img>` cross-origin is free; it is reading the bytes back that a browser
refuses. So the frame is drawn over the ordinary `url()` and only the *fetch*
goes through `streamUrl()`. The first build read both through the route and the
editor was blank wherever the route's `auth` middleware did not answer — which
the browser driver caught and nothing else could have. The fetch falls back to
the plain URL when the route refuses, because where the route is unavailable the
plain URL is the only source there ever was.

**A cached image never fires `load`.** `measure()` runs from `x-on:load` *and*
from `init()`'s `$nextTick`. Without the second, the editor had no dimensions,
every ratio was a no-op, and Save wrote the original back — which the library
correctly recognised as a duplicate, so the bug's only symptom was a crop that
did nothing.

**Deleting an original nulls its derivatives' pointer in the model as well as in
the schema.** `nullOnDelete` is only enforced where foreign keys are, and SQLite
enforces them only when the pragma is on.

**`StoreUpload`'s two private helpers moved out** to `StoredFileFacts`, because
replacing an original has to establish exactly the same facts about exactly the
same kind of file. One owner, so an upload and a replacement cannot describe a
file differently.

### The gate that mattered

`workbench/scripts/verify-media-editor.mjs` — twelve checks, and it is the only
thing that could have found either of the first two items above. Pest sees the
markup and the database; everything between them happens in a canvas.
