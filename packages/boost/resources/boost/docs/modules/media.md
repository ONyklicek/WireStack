---
order: 80
summary: A media library — uploads become rows, deleting a row deletes the file, and previews come from the disk it was stored on.
---

# The Media Module

Upload, find, delete. The row and the file are one thing: deleting the record
deletes the file, because a library that leaves orphans behind fills a disk
nobody is looking at.

```bash
composer require nyoncode/wire-module-media
php artisan wire-module-media:install
php artisan migrate
php artisan storage:link
```

## How It Works

**The disk is stored on every row.** It is part of a file's address — the same
path means different files on `public` and `s3` — so changing the configured disk
moves new uploads and leaves the old rows readable.

**Metadata is read from the disk, not from the browser.** The mime type and the
size are what the stored file actually is; what an upload claimed about itself is
a claim.

**The row is written at the persistence step**, through the form's `using()`
seam. That is not an implementation detail you can ignore if you extend this: the
file fields dehydrate *after* `mutateDataBeforeSave()`, so anything reading
`path` earlier gets the temporary upload rather than the stored file.

**Editing an image produces a new file by default.** Replacing the bytes under a
path other records already point at is how a library quietly changes what a
published page shows — so the ordinary outcome of the editor is a new row, and
the other one is behind a warning that names what it will break. See
[Editing an image](#editing-an-image).

## Configuration

```php
// config/wire-module-media.php
'disk' => 'public',                 // stored on every row too: changing it moves new uploads only [tl! focus]
'directory' => 'media',
'table' => 'wire_media',
'folders_table' => 'wire_media_folders',

'accepts' => [],                    // MIME types or extensions; empty accepts whatever your own rules do [tl! focus:start]
'max_size' => 10240,                // kilobytes, the unit Laravel's rules use [tl! focus:end]

'route' => [
    'enabled' => true,              // the only way a private disk's files reach a browser
    'prefix' => 'wire-media',
    'middleware' => ['web', 'auth'],
],

'thumbnails' => [
    'enabled' => true,
    'width' => 400,                 // the tile: the longest edge, never enlarged
    'sizes' => ['row' => 96, 'preview' => 1200],
    'directory' => 'thumbnails',
    'queue' => false,               // true, or a queue name, where a worker is actually running
],

'navigation' => [
    'group' => 'content',
    'label' => null,                // null uses the module's own group heading
    'icon' => 'outline:photo',
    'sort' => 80,
],
```

Each block has a section of its own below: [Private Files and Who May See
Them](#private-files-and-who-may-see-them) for the route, and
[Thumbnails](#thumbnails) for the sizes and the queue. Three keys have no section
because there is nothing more to them than the line above:

- **`accepts` and `max_size`** are the library's own upload rules. `accepts`
  empty means the library refuses nothing that your validation lets through —
  useful for a private library, and the wrong default for one anybody uploads to.
- **`table` and `folders_table`** name where rows live, for an application that
  already had a `media` table when this one arrived.

The wording is a published translation file and the markup a published view —
`wire-module-media::translations` and `…::views`, with what each costs in
[Theming → Localization](../start/theming.md#localization) and
[Overriding Views](../start/theming.md#overriding-views).

## Folders

A folder is **a row, not a directory**. Nothing on the disk moves when a file is
filed somewhere else, and that is the trade this makes on purpose: moving the
bytes would change the URL of a file a published page already links to, and a
tidier bucket is not worth a broken image on a live page. The disk layout stays
whatever `directory` says it is; the tree is what the library shows you.

```php
use NyonCode\WireModuleMedia\Models\MediaFolder;

$brand = MediaFolder::createIn(null, 'Brand');       // [tl! focus:3]
$logos = MediaFolder::createIn($brand, 'Logos');

$logos->moveTo(null);                                 // back out to the root
```

Each folder stores its **full path** rather than walking its parents, because a
breadcrumb is drawn on every screen of the library and walking is one query per
level. Renaming or moving a folder rewrites the paths below it, which is the cost
of that choice and the reason those two operations are the only ones that touch
more than one row.

Every refusal comes from the model, as a `MediaException` carrying a sentence
that says what it will not do and why:

| It refuses | Because |
| --- | --- |
| Two folders with one name beside each other | They cannot be told apart in a breadcrumb |
| Moving a folder into itself or its own subfolder | Nothing in that branch could be reached again |
| Deleting a folder that still holds anything | Deleting a folder must not be a way to lose files without being asked |

The screen catches those and raises a notification. It does not re-check any of
them — which is what keeps a console command, or your own code, getting the same
answer the screen does.

## The Library Screen

The index page is a file manager rather than a table: a folder tree on the left,
a grid or list on the right, and a breadcrumb that walks back out.

- **Drop files from the desktop** anywhere on the right-hand pane and they upload
  into the folder you are looking at. **Upload a whole folder** with the button
  beside the Upload one — the files are filed into the folder you are looking at,
  because a folder here is a row and inventing four of them from a directory
  somebody happened to drag is not a decision this screen should make quietly.
- **The tray** reports every file of a batch: stored, already here — with a link
  to the row the library kept — or refused, with the reason. It survives opening
  another folder, which is exactly what somebody does while a long batch is still
  going. "3 failed" in a toast is a sentence that makes the person who dropped
  forty photographs go and find the three themselves.
- **Drag a tile onto a folder** to file it there; drag a folder onto another to
  move the whole branch. A tile that is **part of the selection drags all of
  it**. A collapsed branch you hover over during a drag opens itself after a
  moment, and every rung of the **breadcrumb is a drop target** — which is how a
  file goes back up one level without hunting for its folder in the tree.
- **`⌘X` and `⌘V`** do the same thing without a mouse: cut the selection, open a
  folder, paste. A drag across a tree thirty folders deep is a gesture not
  everybody can make, and on a touch screen it is not a gesture at all.
- **The tree folds**, remembers what you left open, and carries a file count per
  folder from one grouped query.
- **Select** with the checkboxes for a bulk move or delete. The bar that appears
  stays at the bottom of the pane while the grid scrolls under it: a selection is
  made by scrolling through files, and a bar at the top is one you have to scroll
  back to. Deleting goes one row at a time on purpose — a mass delete would not
  fire the model event that removes the file, and the bytes would stay behind
  with no row pointing at them.
- **The list is a list**: sortable headers over name, kind, size, dimensions,
  folder and upload date. The headers set the same `sort` the toolbar and the URL
  use, so a sorted view is still something you can send to somebody, and the four
  words it used to hold still work in a link somebody bookmarked.
- **Rename** changes what the file is *listed* under, never where it is stored.
- **Search looks through the whole library**, not the open folder. Searching only
  where you happen to be standing is how a file nobody remembers filing stays
  lost.

## Private Files and Who May See Them

A disk your application **publishes** — the one with a `url` in its
`filesystems.disks` entry — answers its own address and the browser fetches the
file directly. That is the fastest thing that can happen and nothing here touches
it.

A disk you do not publish has no such address, and the library used to answer
`null`: blank tiles, a missing download link, nothing saying why. Those files are
now streamed through the module's own route.

```php
'route' => [ // [tl! focus:4]
    'enabled' => true,
    'prefix' => 'wire-media',
    'middleware' => ['web', 'auth'],
],
```

**"Published" is the disk's `url` key, not what `Storage::url()` says.** Laravel
answers `/storage/{path}` for *any* local disk whether or not that address
resolves — a convenience for the `public` disk and a wrong guess for every other
one. Taking it at its word is how a private contract ends up linked from a page
as though it were public.

Switch the route off and an unpublished disk answers `null` again, deliberately:
a broken image is a better answer than a public URL for a file somebody meant to
keep private.

### A policy

Register one and it is obeyed everywhere at once — the screen, the picker, and
the route that streams the file:

```php
Gate::policy(NyonCode\WireModuleMedia\Models\Media::class, MediaPolicy::class);
```

The abilities are Laravel's own: `viewAny`, `view`, `create`, `update`, `delete`.
Every mutating method on the manager asks before it acts, rather than the view
hiding a button — a Livewire method is a public endpoint, and a hidden button is
not a check.

**With no policy registered, nothing is refused.** Laravel's gate denies an
ability nobody defined, so asking it unconditionally would have locked every
existing library out of its own files the moment it upgraded. Not writing a
policy is how an application says "this is not access-controlled", and that is
taken at face value.

## Thumbnails

The grid used to load originals: a hundred files meant a hundred full-size
photographs, scaled down by the browser after paying for every byte. Now an
upload gets a scaled copy — one WebP, longest edge 400 pixels by default — and
the grid, the picker and the media field all show that instead. The detail panel
and the download link keep the original, which is never touched.

```php
'thumbnails' => [
    'enabled' => true,
    'width' => 400,       // the tile: the longest edge, ratio kept, small images not enlarged
    'directory' => 'thumbnails',

    'sizes' => [          // [tl! focus:3]
        'row' => 96,
        'preview' => 1200,
    ],
],
```

**One copy used to serve three surfaces.** A 32-pixel list row, a grid tile and a
detail preview all loaded the same 400-pixel WebP, and two of those three paid
for pixels they threw away on every screen of the library. Each surface now asks
for the size it actually draws, and a retina screen gets the next size up through
`srcset` — resolution descriptors, not widths, because a `sizes` attribute is a
guess about a layout the model cannot see.

`width` still names the **tile**, and `thumb_path` still holds it. A library that
upgrades renders exactly as it did and gains the other sizes as files are
uploaded — or all at once:

```bash
php artisan wire-module-media:thumbnails --force              # remake everything
php artisan wire-module-media:thumbnails --size=preview       # [tl! focus]
```

`--size` fills in one name that was added to the config later, leaving the copies
that already exist alone. A size that was never made falls back to the tile, and
then to the original: a missing variant is never a broken image.

**A private disk gets them too.** It has no address of its own, so the streamed
route takes the size as a parameter — without that, a grid of a hundred private
photographs stayed a hundred full-size photographs, which is the thing thumbnails
exist to prevent happening to the people who need them most.

**Every tile is painted before its picture arrives.** Making the copies is a
resize, and a one-pixel resize on the way through is free — so the row stores the
image's average colour as `#rrggbb`, and the grid has its shape and its rough
colours immediately instead of reflowing forty times as the images land. The
stored `width` and `height` go onto every `<img>` for the same reason.

**A thumbnail is a convenience, so nothing about it may fail an upload.** A PDF
has no pixels, an SVG needs no raster copy, a GIF would lose its animation, a
server may be built without GD, and a remote disk has no local file to read.
Every one of those leaves `thumb_path` null, and `previewUrl()` falls back to the
original — so no view needs a second code path and no upload is ever refused for
it.

Between dropping a photograph and seeing it there used to be a spinner — a round
trip at best, and longer when the thumbnail is made on a queue. The browser
already holds the bytes, so the tile appears **immediately**, drawn from the file
itself through an object URL, with a bar that moves. The stored file replaces it
when it lands, and the object URL is released — each one pins the whole file in
memory until it is, and a library is exactly where somebody drops forty
photographs at once.

Where thumbnails are queued, the screen refreshes itself until the ones on it
have arrived, and then stops. It asks only when the answer can change: thumbnails
on, made on a queue, and the file uploaded in the last few minutes. A PNG that GD
refused two months ago is null for good, and a screen left open on that folder
would otherwise ask about it every three seconds until the tab was closed.

Files that arrived before thumbnails existed catch up on demand:

```bash
php artisan wire-module-media:thumbnails          # [tl! focus:2]
php artisan wire-module-media:thumbnails --force  # after changing the width
```

It chunks, and it reports two numbers: how many it made, and how many it looked
at. The difference is not a failure to investigate — it is the PDFs.

### Using something other than GD

GD is bound by default because most PHP builds have it. An application with
Imagick, Intervention, or a resizing CDN replaces one binding:

```php
$this->app->bind( // [tl! focus:4]
    NyonCode\WireModuleMedia\Contracts\MakesThumbnails::class,
    ImagickThumbnailer::class,
);
```

The contract is two methods — `supports()` and `make()` — and the same rule
applies to anything implementing it: answer false, never throw.

## Editing an image

Crop, straighten, resize. The picture is opened from its panel and everything
happens in a canvas in the browser, by the same code the file upload field uses
to shrink a phone photograph — extended with rotation, a free crop and stepped
downscaling rather than copied.

**In the browser, and that is a decision.** A server-side editor would inherit
the thumbnailer's one limitation: `MakeThumbnail` gives up on a disk it cannot
read a local file from, so an editor built the same way would silently not exist
on S3 — the configuration a large library is most likely to be on. The browser
reads a URL, and every disk has one.

Saving has **two outcomes and two buttons**, never a checkbox that changes what
one button does:

| Button | What it does |
| --- | --- |
| Save as a new file | A new row, `derived_from_id` pointing at the original, filed in the same folder, carrying the original's alt text. The original is untouched |
| Replace the original | The same row, the same path, new bytes |

The panel shows both directions of that link — what a file was cut from, and what
has been cut from it — so a crop can be traced back rather than being related to
its original only by a name somebody typed. **Deleting an original does not
delete its crops**; they lose the pointer and stay.

### Replacing, and what it costs

Replacement changes every use of the file at once, including the ones the library
[cannot see](#where-a-file-is-used). So the editor says what it is about to
break, from the known-use count, and says the count is a floor. **There is no
undo.**

The bytes are written **to the same path on purpose**. Moving them would change
the URL of a file a published page already links to — which is exactly the kind
of use that cannot be warned about — so keeping the path is what makes a
replacement safe for the uses nobody can enumerate. Same address with different
content is what a browser and a CDN get wrong, so `url()` carries the row's
`updated_at`:

```
/storage/media/2026/hero.jpg?v=1788766728
```

Everything the row says about the file is re-read from the disk afterwards —
size, dimensions, checksum — and the thumbnail is remade. A row whose checksum
still describes the old bytes lies to duplicate detection first.

Replacing asks the policy for its own ability:

```php
public function replace(User $user, Media $media): bool
{
    return $user->isAdmin();
}
```

A policy that does not define `replace` is asked for `update` instead, so a
library written before the editor existed keeps working exactly as it did.

## Using the Library From Everywhere Else

A media module that is only a screen is only a screen. What makes the files
worth keeping is that a post, a product and a rich text editor can all point at
the **same row** — one file, one URL, one description, changed once.

### Attaching files to any record

```php
use NyonCode\WireModuleMedia\Concerns\HasMedia;

class Post extends Model // [tl! focus:3]
{
    use HasMedia;
}

$post->attachMedia($cover, 'cover');   // a named collection
$post->media('gallery');               // what is in one
$post->syncMedia([$b, $a], 'gallery'); // exactly these, in this order
```

The link is a row in `wire_mediables`, never a column on your table, which is
what lets two records share a file instead of each storing a path of its own.
Collections (`cover`, `gallery`, `attachments`) are named sets, so one record can
carry a hero image and a list of downloads without either knowing about the
other.

Detaching removes the link and leaves the file. Deleting the *file* takes its
links with it — a link to a file that no longer exists is a broken image on a
page nobody remembers publishing.

### Where a file is used

`wire_mediables` is a complete record of one kind of use. Attaching through a
field writes a link row, and a link row is findable in both directions.

The rich text editor used to write no such row. It stored `<img src="…">` and
nothing else, so a photograph in twelve articles reported **zero** uses — and the
confirmation in front of every delete was built on that zero. A warning that says
"nothing uses this" about a file that is on the front page is not a weak warning;
it is a false all-clear.

The editor now keeps the id, and a model says which of its attributes hold
written content:

```php
use NyonCode\WireModuleMedia\Concerns\HasMedia;
use NyonCode\WireModuleMedia\Concerns\SyncsMediaUsage;

class Post extends Model
{
    use HasMedia;          // [tl! focus:4]
    use SyncsMediaUsage;

    protected array $mediaContent = ['body', 'perex'];
}
```

On save, the ids are read out of those attributes and **synced** — not appended —
into `wire_mediables` under the reserved collection `__content`. An image taken
out of an article takes its link with it, or a count that only ever grows stops
meaning anything the first time somebody edits.

It is the same table a field writes to, so "where is this file" is one query and
one mental model. The reserved name is underscored so it can never collide with a
collection of your own, and `media('gallery')` never returns content uses.

**The number is a floor, and every screen says so.** A URL pasted into a Blade
template, a seeder or an e-mail layout by hand is invisible to this and always
will be. An honest floor is usable — "definitely in use, possibly more" — where a
number presented as complete would be trusted and would eventually be wrong at
the moment it mattered.

The file's panel lists the uses it knows about, linking through to the record
where a resource exists for its model. Deleting **warns** with the count and does
not refuse: retiring a file whose old article is allowed to end up with a broken
image is a legitimate thing to want, and refusing would make the library
un-cleanable.

Articles already in the database carry no id, so they are matched by URL, once:

```bash
php artisan wire-module-media:usage --model="App\Models\Post"           # [tl! focus:2]
php artisan wire-module-media:usage --model="App\Models\Post" --dry-run
```

It reports how many links it wrote and across how many records, and how many
records pointed at nothing the library holds. That difference is not a failure to
investigate — it is the pictures that were never in this library.

### A field on any form

```php
use NyonCode\WireModuleMedia\Forms\MediaField;

MediaField::make('cover'), // [tl! focus:5]

MediaField::make('gallery')
    ->multiple()
    ->collection('gallery')
    ->accepts('image/'),
```

**It saves itself.** The field's name is a collection, never a column, so it
implements `SavesAfterRecord`: its value is taken out of the data before the
record is written and written to the pivot afterwards, once the record has a key.
The model needs `HasMedia` and nothing else — no column, no migration, no
`afterSave` closure to remember.

### The rich text editor

`TiptapEditor::make('body')->withImages()` used to ask for a URL. With the media
module installed, its image button opens the library instead, and the alt text
comes back with the file.

Nothing was configured to make that happen, and nothing could have been:
`wire-forms` sits below this package and must never require it. So the editor
*offers* the job as a cancelable DOM event and whoever handles it claims the
event:

```js
const request = new CustomEvent('wire-media-picker:open', { // [tl! focus:6]
    cancelable: true,
    detail: { token: 'anything-unique', multiple: false, accepts: 'image/' },
});

window.dispatchEvent(request);

if (! request.defaultPrevented) { /* nobody is listening — do what you did before */ }
```

The answer arrives on `wire-media-picker:picked`, carrying the token it was
opened with so two pickers on one page cannot cross. Your own code can open the
library the same way.

### Where the modal comes from

The picker has to be in every page, and the shell that renders every page
(`wire-admin`) sits *above* this module and has never heard of it. So this module
registers its view and the shell renders whatever is registered:

```php
app(NyonCode\WireCore\Foundation\View\PageChrome::class)
    ->add('wire-module-media::picker-modal');
```

An application rendering its own layout instead of the shell's adds the same
loop to it, and one that renders neither simply has no picker — a button that
says so, rather than a page that breaks.

### One file, once

The library refuses a second copy of a file it already holds. The sha-256 of the
stored bytes is matched against what is there, and an upload of a file already in
the library hands back the existing row and deletes the copy it just made. The
same photograph uploaded from three screens should be one row with one URL and
one alt text, or every use of it drifts apart.

Checksums are only taken on disks with real files behind them. On S3 the file is
not pulled back down to hash it — duplicate detection is a convenience, and one
that downloads every upload twice is not one.

## What You Get

| Screen | Notes |
| --- | --- |
| Media | The file manager: folder tree, grid or list, breadcrumb, search across the library |
| Upload | Drag files onto the pane, or pick them — the disk, directory, accepted types and size limit come from config |
| One file | Read-only, headed by the file's own name: a preview at a size you can see it at, then what the file is (type, size, dimensions, folder, upload date), what somebody wrote about it (alt text and title), and — folded away — the disk and path it lives on. Open and Download sit in the preview's header, as links: a private disk goes through the module's streamed route, and a file with no address at all offers neither |

## Related

- [File uploads](../forms/fields/file-upload.md) — the field this uses
- [Modules](../panels/modules.md) — how a package ships an area like this

