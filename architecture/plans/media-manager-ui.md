# The Media Manager, As A Tool You Work In

`packages/module-media/` already holds a folder tree, a grid, a list, drag and
drop in both senses, uploads by dropping onto the pane, thumbnails, a detail
panel and a picker that is the same component. This plan does not rebuild any of
that. It names the seven places where the screen stops behaving like a media
manager and starts behaving like a page with files on it, and what each one
should become.

Read `docs/modules/media.md` first — the decisions that hold (a folder is a
row, the disk is part of the address, every refusal comes from the model) are
load-bearing here and none of them change.

## What Is Missing, In One Table

| Ask | Today | Gap |
| --- | --- | --- |
| File tree | Recursive branch partial, drag targets, hover rename | Always fully expanded, no counts, no root node, rename through `window.prompt()` |
| Grid and list | Both, toggled | The list is a bare `<table>`: no sortable headers, no dates, no folder column |
| Preview, else the extension | `image/*` with a thumbnail; everything else one grey `outline:document` | A PDF, a spreadsheet and a zip are indistinguishable |
| Add, move | Both work | A batch reports "3 failed" without saying which three |
| Bulk add | Multiple upload, optimistic tiles | No tray, no per-file state, no retry |
| Edit images | Absent by design | The design reason is real; the absence is not the only answer to it |
| Where a file is used | Only field links | TipTap stores a bare URL, so a photo in twelve articles reports zero uses |
| Thumbnails | One WebP, longest edge 400 | One size for three surfaces, no `srcset`, no placeholder, no `width`/`height` |

## 1. The Frame

Three panes, and the chrome stops scrolling with the files.

- **Left rail** — the tree, with disclosure triangles and an expansion state kept
  per person, a **Library** root node that is the drop target for "move out to
  the root" (today that target is elsewhere on the screen and has to be
  explained), a file count per folder from one grouped query, and a width the
  person can drag. `New folder` sits at the bottom of the rail, not in the
  header, because it is the rail's only verb.
- **Centre** — a sticky toolbar (breadcrumb, search, type chips, sort, view
  toggle, Upload), then the canvas. Nothing else moves.
- **Right** — the inspector. A pane on desktop, a sheet on mobile. It already
  exists; it gains the preview at a size worth looking at, the derived-file list
  (§6) and an **Edit** button.

The selection toolbar becomes a bar that rises from the bottom of the canvas when
anything is selected — count, Move to…, Download, Delete — and replaces the
`<select>` that currently lives in the toolbar and carries three different
meanings in one string.

## 2. What A Tile Shows

One canonical answer to "what does this file look like", asked by the grid, the
list, the inspector, the picker and the media field — five surfaces that must not
each grow a `match` of their own.

**`FileKind`** — an enum in **`core/Foundation/Enums/`** mapping a mime type to a
family: `image`, `video`, `audio`, `document`, `spreadsheet`, `presentation`,
`archive`, `code`, `other`. Each case carries an icon and a `Color` from core's
canonical vocabularies, never a local colour name. It sits in core rather than in
this module because `wire-forms` has the same grey icon in two of its own views —
[ADR 0033](../decisions/0033-file-kind-vocabulary.md) records why, and why the
extension wordmark is the file's name rather than the enum's business.

**`MediaPreview`** — a value object answering one of three shapes:

| Shape | When |
| --- | --- |
| `image` | An image with a thumbnail, or an image without one that is small enough to serve directly (SVG, an animated GIF) |
| `poster` | A video or a PDF whose thumbnailer produced a frame or a first page |
| `card` | Everything else: the extension as a wordmark (`PDF`, `XLSX`, `ZIP`) over the family's colour, with the family's icon behind it |

The card is the answer to *"jinak označení příponou"*, and it is why the enum
exists: the wordmark is the extension, the colour is the family, and a person
scanning a folder of forty files sees the shape of it without reading a name.

`MakesThumbnails` grows `supports()` cases rather than a second contract — a
thumbnailer that can rasterise a PDF page or pull a video frame answers true and
`MakeThumbnail` writes `thumb_path` exactly as it does now. GD answers false to
both and the card is what shows. Nothing about that path may fail an upload;
that rule does not bend.

## 3. The List As A List

The same rows, rendered as a real list: preview at 32px, name, kind, size,
dimensions, folder, modified. Headers sort — and they sort by driving the
component's existing `$sort`, extended from four hard-coded orders to a column
plus a direction.

**Not `wire-table`.** The list is one of two renderings of a selection the grid
also owns, and the picker renders both inside a modal; mounting a Table there
would put two selection state machines and two paginators on one screen. What is
worth borrowing is the *vocabulary* — the gesture layer's keyboard grid, shift
ranges and row context menu (`architecture/table.md` § Gesture layer) — as the
same key bindings, not as a second implementation.

## 4. Adding Files: The Tray

A dockable panel at the bottom right, listing every file in the batch with its
own progress bar and its own terminal state: **stored**, **duplicate — opens the
row we already have**, or **refused, with the reason**. It survives navigating
between folders, and each failed row has a Retry.

The counts in a toast were the right first answer and are the wrong last one: a
person who drops forty photographs and reads "3 failed" has to find the three
themselves. `StoreUpload` already returns null on failure and sets `$duplicate`
by reference — the tray is the same information, kept instead of counted.

Also: **a folder upload**. `webkitdirectory` on the picker input, and the drop
handler reading `DataTransferItem.webkitGetAsEntry()`, so dropping a directory
creates the folders it implies and files each upload into the right one. That is
the difference between "bulk add" and "add the shoot".

## 5. Moving

Dragging a tile onto a folder works. What does not:

- Dragging **the selection** — today a drag carries the one tile it started on.
  The drag payload becomes the selection when the dragged tile is part of it.
- **Auto-expand** a collapsed folder hovered during a drag, and auto-scroll the
  rail near its edges.
- **Drop onto the breadcrumb**, which is how a person moves a file back up one
  level without hunting for the folder in the tree.
- **Cut / paste** (`⌘X`, `⌘V`) as the keyboard equivalent, because a drag across
  a long tree is not a gesture everyone can make.

## 6. Editing An Image

The current refusal is right about the danger and too broad about the answer:

> Replacing the bytes under a path other records already point at is how a
> library quietly changes what a published page shows.

The owner weighed that against the expectation everyone arriving from WordPress
carries, and chose to offer replacement on every file behind a warning. So Save
has two outcomes and both are always present, as two buttons rather than a
checkbox that changes what one button does:

| Button | What it does |
| --- | --- |
| **Uložit jako nový soubor** | A new row, `derived_from_id` pointing at the original, same folder. The original is untouched |
| **Nahradit originál** | The same row, the same path, new bytes — behind a dialog naming the records that will change |

That warning is worthless until the library knows where a file is used, and today
it does not: the rich text editor drops the media id and stores a bare URL. So
**[ADR 0034](../decisions/0034-where-a-file-is-used.md) is a prerequisite**, not a
neighbour — it is also the answer to "where was this file used", which the
inspector gains as a panel of its own.

The full decision, including why replacement keeps the path and gains a version
query instead of moving the bytes, is
[ADR 0035](../decisions/0035-image-editing-and-replacement.md).

**The pixels are recomputed in the browser, by `wire-forms`' image processor,
extended — not a second one.** Server-side rendering was the alternative and it
loses on one fact: `MakeThumbnail` already gives up at `is_file()` on a remote
disk, so that editor would not exist on S3 at all. `packages/forms/resources/js/image-processor.js` already crops to a ratio,
places the frame interactively and downscales, dependency-free, in a canvas. It
gains `rotate` (90° steps), `flip` and a free-ratio crop; the media editor is a
modal around the same functions. `wire-forms` sits below this package in the
graph, so importing it is allowed and duplicating it is not (CLAUDE.md § Adapter,
never a second wheel — and the memory of the same name).

Saving hands the resulting `Blob` to the existing upload path: `StoreUpload`
hashes it, refuses a copy that already exists, reads the metadata from the disk
and queues the thumbnail. The whole save side of the editor is already written.

Deliberately **not** proposed: storing an edit recipe and rendering on demand.
Without a CDN in front of the disk, every request re-renders, and the thing this
plan is trying to buy is page load time.

## 7. Thumbnails, And The Load Time

One 400px WebP serves a 32px list row, a ~256px tile and an 800px inspector
preview. Three surfaces, one file, and two of them pay for pixels they throw
away.

```php
'thumbnails' => [
    'sizes' => [        // named, not a single width
        'row' => 64,
        'tile' => 320,
        'preview' => 1200,
    ],
],
```

- `thumb_path` **stays** and stays the tile — an existing install must not break
  and must not need a backfill to render. A `thumb_variants` JSON column holds
  the rest, and a missing variant falls back to `thumb_path`, then to the
  original. Same rule as today, one level deeper.
- The grid emits `srcset` over `row`/`tile`, so a retina screen gets the 320 and
  a 1× screen does not.
- **`width` and `height` on every `<img>`.** Both are already stored. Without
  them a grid of forty tiles reflows forty times as the images land.
- **A placeholder colour**, taken as a 1×1 downscale during thumbnailing and
  stored as a hex string on the row. The tile paints it immediately, so the grid
  has its final shape and its rough colours before a single image has arrived.
  A 1×1 resize is free where the resize is already happening; a blurhash is a
  dependency and buys little more.
- `decoding="async"` beside the `loading="lazy"` that is already there.
- The polling loop (`awaitingThumbnails()`) is unchanged — it is already the
  right shape, and it already knows when to stop.

The backfill command grows `--size=` so a new variant can be filled in without
remaking the ones that exist.

## 8. Order Of Work

Each phase ships something usable on its own.

1. ~~**`FileKind` + the shared thumbnail component**, and every surface asking
   them.~~ **Done** — ADR 0033.
2. ~~**Thumbnail variants**, `srcset`, dimensions, placeholder colour.~~ **Done**
   — named sizes with `width` still naming the tile, the streamed route taking a
   size so private disks get them too, and the average colour taken as a
   one-pixel resize on the way through.
3. ~~**The frame**~~ **Done** (with two exceptions): sticky toolbar, collapsible
   tree with counts and a root node, the selection bar at the bottom of the
   pane, the inspector's preview at its own size. **Not done**: a rail whose
   width the person can drag, and the inspector as a sheet on mobile.
4. ~~**The tray**~~ **Done** (with one exception): every file with its own
   outcome and reason, surviving folder changes, and folder upload through
   `webkitdirectory`. **Not done**: per-file retry — a real one needs the browser
   to hold the `File` objects and re-upload one of them, and a button that only
   re-opens the picker would be a retry in name only.
5. ~~**Drag the selection**, auto-expand, breadcrumb drops, cut/paste.~~ **Done.**
6. ~~**The list as a list**: sortable headers, real columns.~~ **Done** — `sort`
   became `column:direction` with the four old words kept as aliases, so a
   bookmarked link still lands where it did.
7. ~~**Knowing where a file is used**~~ **Done** — ADR 0034: the id survives into
   the document, `SyncsMediaUsage`, the inspector's Usage panel, the delete
   warnings, the backfill command. Brought forward out of order, because
   nothing may replace bytes until a warning can be true.
8. ~~**The editor**~~ **Done** — ADR 0035: `processImage` gained rotate, flip, a
   free crop and stepped downscaling; the modal; `derived_from_id`; replacement
   behind the warning phase 7 made true.

Every phase has landed. What is deliberately left, and why, is on the three
lines above that say **Not done** — plus the gesture-layer vocabulary in § 3,
which stays a borrowing rather than a dependency.

## 9. Gates

- `composer test:module-media` for every phase; `MediaEcosystemTest` covers the
  picker, the field and the editor seam together.
- **`npm run verify:drivers`** is the only check over the tray, the drag payload
  and the editor — Pest sees markup, not what the browser does with it
  (memory: *CDP drivers*, *morph markers are load-bearing*). New drivers under
  `workbench/scripts/` for: a batch upload's tray states, dragging a selection
  onto a folder, and a crop that produces a derivative.
- `docs/modules/media.md` and its CS pair move together, per
  `AI_DOCS_STANDARD.md`, and the ADR for the derivative rule in §6 goes in
  `architecture/decisions/` — it is a data-model promise, not a screen detail.

## As Built

Everything above landed on 2026-09-07. Three of the eight phases have an ADR of
their own — [0033](../decisions/0033-file-kind-vocabulary.md),
[0034](../decisions/0034-where-a-file-is-used.md),
[0035](../decisions/0035-image-editing-and-replacement.md) — and each carries its
own *As Built*. What follows is the rest, plus the handful of places where the
finished thing differs from the plan.

### Where it lives

| Phase | Where |
| --- | --- |
| 1 · What a tile shows | `core/Foundation/Enums/FileKind.php`, `core/Foundation/View/FileThumb.php` + `foundation/file-thumb.blade.php` |
| 2 · Thumbnail sizes | `MakeThumbnail::sizes()`, `Media::{previewUrl,srcset,thumbPathFor}`, `MediaController::show(?variant)`, `update_wire_media_table_add_thumbnail_variants.php` |
| 3 · The frame | `manager.blade.php` (sticky toolbar, rail, selection bar), `partials/folder-branch.blade.php`, `MediaManager::folderCounts()` |
| 4 · The tray | `MediaManager::{$tray,trayTotals,forgetTrayEntry}`, `forms/…/fields/file-dropzone.js` (`retain`, `retry`) |
| 5 · Moving | `MediaManager::{moveIds,cutSelection,pasteHere}`, the drag payload in `manager.blade.php` |
| 6 · The list | `MediaManager::{sortColumn,sortDirection,sortBy}` + the list table |
| 7 · Where a file is used | ADR 0034 |
| 8 · The editor | ADR 0035 |

### What differs from the plan

**§2 — `width` still names the tile.** The plan had a `sizes` map with `tile` in
it. That would have made an existing `WIRE_MEDIA_THUMBNAIL_WIDTH` inert, so
`sizes()` reads `width` as the tile and `sizes` as the *other* sizes. An
installation that upgrades keeps the copies it has and the knob it set.

**§2 — the streamed route takes a size.** Not in the plan, and without it the
whole phase skipped the people it helps most: a private disk has no address of
its own, so the route could only ever stream the original and a grid of a
hundred private photographs stayed a hundred full-size photographs.

**§2 — `srcset` uses `1x`/`2x`, not widths.** Width descriptors need a `sizes`
attribute, and a `sizes` attribute is a guess about a layout the model cannot
see. Each surface asks for the size it draws; the only thing left to answer is a
retina screen.

**§3 — the tree's state is the browser's.** Which branches are open, and how wide
the rail is, are kept in `localStorage`. Folding a folder away should not be a
round trip, and neither is a fact the server has any use for.

**§4 — the retry is real, and it cost a change in `wire-forms`.** The plan asked
for one without saying where the file would come from. The server cannot re-send
a file it never received, so `wireFileDropzone` grew an opt-in `retain` that
keeps the batch's `File` objects and a `retry(name)` that sends one again.
Opt-in, because holding forty photographs alive is not something a field that
uploads once should do.

**§5 — the drag carries the word `selection`.** Not a list of ids: the server
already knows what is selected, and putting a list in a `DataTransfer` is a
second copy of it that can disagree with the first.

**§6 — `sort` became `column:direction`, with the four old words as aliases.** It
is one property because it is one thing in the URL, and the aliases are there so
a link somebody bookmarked before the list had headers still lands where it did.
The column goes through an allow-list, because `sort` is a public property and a
Livewire method is a public endpoint.

### Deliberately not done

- **Usage counts on the grid tiles.** One more grouped query per page, and not
  needed to make anything else honest (ADR 0034 § Deferred).
- **Folder upload that recreates the directory tree.** `webkitdirectory` files
  land in the folder being looked at. A folder here is a row, and inventing four
  of them from a directory somebody happened to drag is not a decision this
  screen should make quietly.
- **The gesture layer as a dependency.** § 3 borrows its key vocabulary; mounting
  `wire-table` inside the picker would put two selection state machines and two
  paginators on one screen.

### What the browser found that nothing else could

Two bugs and one bad test, all in things Pest cannot see:

- **The editor's frame had never laid out.** `max-h-[52vh]` appears only in the
  editor partial, so it was missing from the compiled CSS until `npm run build`
  ran — leaving a wrapper at 0×0. The check that should have caught it read
  `Math.abs(w - h) <= 2`, which *passes* at 0×0. Any check comparing two
  measurements now asserts one is non-zero first.
- **A cached image never fires `load`.** The editor measured only from
  `x-on:load`, so a picture the browser already had left it with no dimensions,
  every ratio a no-op, and Save writing the original back — which the library
  correctly called a duplicate, so the only symptom was a crop that did nothing.
- **The editor driver did not clean up**, leaving one derivative behind per run,
  which the next driver counted.

### Gates

`composer test:module-media` — 185. Five browser drivers over this screen:
`media-library` (23), `media-picker` (15), `media-detail` (17), `media-editor`
(14), `media-frame` (14). `docs/modules/media.md` and its CS pair move
together.

**A driver gets 180 seconds in the sweep**, and launching a browser costs more
than every check in `media-frame` put together. Its phone half resizes the page
it already has (`Emulation.setDeviceMetricsOverride`) rather than opening a
second one — which is what it did first, and what the sweep killed while it
passed in isolation.

**One trap worth knowing before adding a migration here.** `workbench:build`
publishes the package's migrations into the testbench app, where they run a
second time beside the workbench's own copies — so both new migrations guard on
`Schema::hasColumn()`. Without that, the build stops at `duplicate column`.
