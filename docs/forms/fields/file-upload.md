# FileUpload

File upload with preview, validation, and image processing.

```php
use NyonCode\WireForms\Components\FileUpload;
```

## Basic Usage

```php
FileUpload::make('attachment')
    ->disk('public')
    ->directory('attachments')
```

## Validation

```php
FileUpload::make('document')
    ->acceptedFileTypes(['application/pdf', 'image/*'])
    ->maxSize(10240)       // KB
    ->minSize(100)         // KB
    ->multiple()
    ->maxFiles(5)
    ->minFiles(1)
```

## Image Mode

```php
FileUpload::make('photo')
    ->image()
    ->imageResizeTargetWidth(1920)
    ->imageResizeTargetHeight(1080)
    ->imageCropAspectRatio('16:9')
```

## Avatar

```php
FileUpload::make('avatar')
    ->avatar()             // circular preview, single file
```

## Storage

```php
FileUpload::make('file')
    ->disk('s3')
    ->directory('uploads/2024')
    ->visibility('public')
    ->preserveFilenames()
```

## Storage & merge (store-on-submit)

Selecting (or dropping) a file uploads it to Livewire's **temporary** storage
and lists it below the drop zone as a *pending* upload — it is **not** moved to
permanent storage until you **save** the form. This keeps the model
orphan-free: an abandoned form leaves nothing behind (the temporary upload
expires on its own). On save, each pending upload is stored to the configured
`disk()`/`directory()` (honouring `visibility()` and `preserveFilenames()`) and
the field dehydrates to the stored path(s).

- **single** fields keep the newest upload, and dehydrate to one path (or `null`);
- **multiple** fields **merge** — new uploads are appended to the already-stored
  paths, so uploading more never discards what was there, and dehydrate to an
  array of paths.

The host must compose the form runtime (`WithForms`, or a table/form action
modal); the upload plumbing (Livewire's file handling, the merge step, and the
save-time store) is wired in automatically.

## Files list & removal

The field lists everything currently in its state below the drop zone —
already-stored paths (from the bound record or a previous save) **and** pending
uploads. Image files show a thumbnail (stored via the disk URL, pending via a
temporary preview), others a document icon; a stored file links to itself, a
pending one is labelled *Pending upload*. Each has a **remove** button that
drops it from the form state by index. Removing a stored file leaves the
physical file on disk untouched (cleanup is the application's concern); removing
a pending upload simply discards it.

Stored paths resolve to URLs through the configured `disk()` (a value that is
already a full URL is used as-is). Pass `deletable(false)` to show files
read-only, without the remove control:

```php
FileUpload::make('gallery')
    ->image()
    ->multiple()
    ->disk('public')
    ->deletable(false)   // display files read-only
```

## Methods

| Method | Description |
|--------|-------------|
| `disk(string)` | Storage disk |
| `directory(string)` | Upload directory |
| `visibility(string)` | File visibility (`public`, `private`) |
| `preserveFilenames()` | Keep original filenames |
| `acceptedFileTypes(array)` | Allowed MIME types |
| `maxSize(int)` | Max file size in KB |
| `minSize(int)` | Min file size in KB |
| `multiple()` | Allow multiple files |
| `maxFiles(int)` | Max number of files |
| `minFiles(int)` | Min number of files |
| `image()` | Image-only mode |
| `avatar()` | Avatar mode (circular, single) |
| `imageResizeTargetWidth(int)` | Resize width in pixels |
| `imageResizeTargetHeight(int)` | Resize height in pixels |
| `imageCropAspectRatio(string)` | Crop aspect ratio (e.g. `16:9`) |
| `deletable(bool)` | Whether already-stored files can be removed (default `true`) |
| `disabled(bool\|Closure)` | Disable the uploader |
| `required()` | Mark as required |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.
