# ADR 0034: Where A File Is Used

## Status

ACCEPTED — 2026-09-07, implemented the same day. Raised by the repo owner in one
question — *"Pokud použiji obrázek třeba přes tiptap nebo jinde pozná to? Mohu
zjistit kde byl použit?"* — asked while reviewing the decision to let the editor
replace an original ([ADR 0035](0035-image-editing-and-replacement.md)).

The answer before this was **no**, which made it a prerequisite of 0035 rather
than a feature beside it.

## Context

`wire_mediables` is a complete record of one kind of use and no record at all of
another.

**What it knows.** `attachMedia()`, `syncMedia()` and `MediaField` all write a
link row: a file attached to a record through a field is findable, in one query,
in both directions.

**What it did not know.** The rich text editor. `TiptapEditor::withImages()`
opens the picker, the picker answers with the file — id, url, alt, title, mime,
dimensions — and the editor used three of those:

```js
// packages/forms/resources/js/tiptap-editor.js:178
editor?.chain().focus().setImage({ src: file.url, alt: file.alt ?? '', title: file.title ?? null }).run()
```

The id is dropped on the floor. What lands in the database is an `<img>` carrying
a URL, and the library has no way back from a URL to a use. So a photograph in
twelve articles reports zero uses, and three separate screens are built on that
zero:

- the confirmation before a **delete**, which today does not ask about uses at all;
- the warning before a **replace**, which ADR 0035 makes the whole safety of that
  feature depend on;
- the plain question the owner asked — **where is this used** — which a media
  library is expected to answer.

A warning that reports zero uses for a file in twelve articles is not a weak
warning. It is a false all-clear, and it is worse than showing nothing.

## Decision

### 1. The picker's answer keeps the id all the way into the document

TipTap's image node gains a `data-media-id` attribute through `addAttributes()`,
filled from the `wire-media-picker:picked` payload that already carries `id`.

`wire-forms` learns nothing about what the id means — it stops discarding a field
it is already handed. That matters for the package graph: `wire-forms` sits below
`wire-module-media` and must never require it, and after this change it still
does not. An installation with no media module writes no attribute, because
nothing answers the picker event.

### 2. Content usage is synced on save, into the table that already exists

A concern in the media module, `SyncsMediaUsage`, applied by the application to
the models that hold rich text:

```php
class Post extends Model
{
    use HasMedia;
    use SyncsMediaUsage;

    protected array $mediaContent = ['body', 'perex'];
}
```

On `saved`, the ids are read out of those attributes and synced into
`wire_mediables` under the reserved collection **`__content`**.

- **The same table**, so "where is this file" stays one query and one mental
  model. A second table would mean every consumer asking twice and one of them
  eventually forgetting.
- **A reserved name**, underscored, so it can never collide with an owner's own
  collection and `media('gallery')` never returns content uses.
- **Synced, not appended** — an image removed from an article removes its link,
  or the count only ever grows and stops meaning anything.

The existing unique index means one article using one image three times is one
row. That is the right unit: the question is which records break, not how many
`<img>` tags do.

### 3. The inspector answers the question out loud

A **Použití / Usage** panel: the record's type, its key, and which collection the
link came from — `gallery`, `cover`, `__content` — linking through to the record
where a resource for that model exists. This is the feature the owner asked for;
everything above is what makes it true.

### 4. The number is a floor, and says so

A URL pasted into a Blade template, a seeder, a config file or an e-mail layout
by hand is invisible to all of this, and no amount of syncing will find it. So
every surface says **"známá použití: 3"** — *known* uses — and never "used 3
times".

An honest floor is usable: it turns "this might be in use" into "this is
definitely in use, and possibly more". A number presented as complete would be
trusted, and would eventually be wrong at the moment it mattered.

### 5. Existing content is backfilled once, by scanning

Articles already in the database carry no `data-media-id`. A command —
`wire-module-media:usage --model=App\Models\Post` — reads the configured
attributes, matches the stored paths of known media against the HTML, and writes
the links it finds. One pass, opt-in per model, reporting what it matched and
what it could not.

### 6. Deleting warns; it does not refuse

The delete confirmation gains the known-use count. It stays a confirmation.

Refusing to delete a file that is in use would be a new rule the model owns, and
it would make the library un-cleanable: retiring a file whose old article is
allowed to end up with a broken image is a legitimate thing to want. The folder
rule is different on purpose — deleting a folder must not be a way to lose files
without being asked, and nothing is lost by declining to delete an image.

## Consequences

- **No new table and no new migration.** `wire_mediables` carries one more kind
  of row, distinguished by a collection name.
- **Opt-in per model.** A model that does not use the concern reports nothing,
  exactly as today, and nothing breaks by upgrading.
- Extraction on save is a regex over HTML the application just wrote — cheap, and
  only where the concern is applied.
- `docs/modules/media.md` gains a section, with its CS pair; the "Attaching
  files to any record" section is where it belongs, because that is where a
  reader learns what a link row is.

## Deferred

**Scanning content on demand, from the inspector.** "Find every occurrence of
this file's path across the application's tables" would raise the floor to near
certainty, and it is a table scan across tables this package does not own. Worth
doing when somebody has the problem; the backfill command in §5 is the same
machinery, run once and deliberately.

**Usage counts in the grid.** A badge on each tile saying how many records use
it. One extra grouped query per page and genuinely useful — deferred only
because it is not needed to make ADR 0035 safe, which is what this ADR is for.

## As Built

| Piece | Where |
| --- | --- |
| The id survives into the document | `forms/resources/js/tiptap-editor-addons.js` (the extended image node), `tiptap-editor.js` (`mediaId` on insert) |
| Reading ids and URLs out of content | `module-media/src/Support/ContentMedia.php` |
| Reading and counting uses | `module-media/src/Support/MediaUsage.php` |
| The model hook | `module-media/src/Concerns/SyncsMediaUsage.php` |
| The panel and the two delete warnings | `MediaManager::deleteWarning()`, `manager.blade.php` |
| The backfill | `wire-module-media:usage --model= [--dry-run]` |

Two things landed differently from the sketch above and are worth knowing:

**The counts for a grid are one grouped query**, not one per tile
(`MediaUsage::countsFor()`), because this is asked wherever a page of files is
drawn and a count per file is how forty files become forty queries.

**The backfill holds the library in memory** as a `path => id` and
`basename => id` map (`ContentMedia::lookup()`). The alternative is a query per
`<img>` in every row being scanned. The basename key is the weaker of the two and
is documented as such: two files with the same name in different folders resolve
to whichever was loaded last, and the exact path is always tried first.
