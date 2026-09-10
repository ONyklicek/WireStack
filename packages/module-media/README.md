<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/ONyklicek/WireStack/HEAD/docs-site/assets/brand/github/readme-banner-dark.png">
  <img src="https://raw.githubusercontent.com/ONyklicek/WireStack/HEAD/docs-site/assets/brand/github/readme-banner-light.png" alt="WireStack" width="1200">
</picture>

# wire-module-media

A media library for the wire framework: uploads become rows, folders are rows
too, and deleting a row deletes the file.

```bash
composer require nyoncode/wire-module-media
php artisan wire-module-media:install
php artisan migrate
php artisan storage:link
```

## The trade it makes

**The row and the file are one thing.** Deleting the record deletes the file,
because a library that leaves orphans behind fills a disk nobody is looking at.

**The disk is stored on every row.** It is part of a file's address — the same
path means different files on `public` and `s3` — so changing the configured
disk moves new uploads and leaves the old rows readable.

**Metadata is read from the disk, not from the browser.** The mime type and the
size are what the stored file actually is; what an upload claimed about itself
is a claim.

**A folder is a row, not a directory.** Nothing on the disk moves when a file is
filed somewhere else: moving the bytes would change the URL a published page
already links to, and a tidier bucket is not worth a broken image on a live
page.

```php
use NyonCode\WireModuleMedia\Models\MediaFolder;

$brand = MediaFolder::createIn(null, 'Brand');
$logos = MediaFolder::createIn($brand, 'Logos');

$logos->moveTo(null);   // back out to the root
```

**Editing an image produces a new file by default.** Replacing the bytes under a
path other records already point at is how a library quietly changes what a
published page shows, so the ordinary outcome of the editor is a new row — and
the other one is behind a warning that names what it will break.

## What you get

| Screen | Notes |
| --- | --- |
| Library | A folder tree, a grid or list, a breadcrumb that walks back out |
| One file | Preview, metadata read off the disk, rename, move, replace, delete |
| The picker | The same library inside a form field, for choosing an existing file |

Every refusal comes from the model as a `MediaException` carrying a sentence
that says what it will not do and why — two folders with one name beside each
other, a folder moved into its own subfolder, a folder deleted while it still
holds something. The screen catches those and raises a notification; it does not
re-check any of them, which is what keeps a console command getting the same
answer the screen does.

## Documentation

Full docs: [`docs/modules/media.md`](../../docs/modules/media.md)
([česky](../../docs/cs/modules/media.md)).
