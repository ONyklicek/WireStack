---
summary: A signature drawn with a pointer, stored as a data URI or as a PNG on a disk.
---

# SignaturePad

A signature drawn on a canvas with a finger, a stylus or a mouse. Reach for it
when a record needs a person's mark on it — a delivery note, a consent, an
approval — rather than a typed name.

```php
use NyonCode\WireForms\Components\SignaturePad;
```

## How It Works

**State is the drawing as a `data:image/png;base64,…` URI.** The canvas produces
one, and it round-trips through Livewire like any other string. That is what the
field stores by default, which suits a text or blob column.

**`storeOn()` moves the bytes to a disk instead**, and state then carries the
stored path — the same shape a `FileUpload` leaves behind, resolved for display
through the same URL ladder, so a signature and an upload are rendered from a
record the same way.

**The conversion happens at the state boundary, not while drawing.** The browser
only ever holds the data URI, so an interrupted form loses a signature the way
it loses any other unsaved field, and no orphaned file is written for a form
that was never submitted.

**Writing is idempotent.** A host may dehydrate the same state twice in one save
— once to pre-validate, once with the record locked — so a drawing already
written during this request answers with the path it got the first time instead
of leaving a second copy on the disk.

**State that already holds a stored path is left alone.** It is a signature
nobody re-drew, and writing it out again would add a copy of the same image on
every save.

**Drawing is pointer-based**, so one set of events covers stylus, finger and
mouse, and a stroke that leaves the canvas stays attached to it rather than
ending mid-letter. Two details make the result legible: the canvas is sized in
device pixels and scaled back down, so a stroke on a 2× screen is drawn at that
screen's resolution; and the backing store is only re-created on a real width
change, because assigning `canvas.width` clears the canvas — a naive resize
handler wipes a signature every time a mobile browser's toolbar slides away.

**State is written on pointer-up, not per point.** `toDataURL()` re-encodes the
whole image; doing that per movement would push a base64 PNG through Livewire
dozens of times per stroke.

**Both colours pass through the canonical CSS-colour guard**, so a value that is
not a colour renders no colour at all rather than reaching the canvas config.

## Basic Usage

```php
SignaturePad::make('signature')
```

A transparent PNG in the column, drawn in the ink colour of body text.

## Size And Ink

```php
SignaturePad::make('signature')
    ->height(240)              // CSS pixels — default 180
    ->penColor('#1d4ed8')
    ->penWidth(3)
```

## A Painted Canvas

```php
SignaturePad::make('signature')
    ->backgroundColor('#fffbeb')
```

Left unset the PNG is transparent, which is what a signature printed onto a
document wants. A colour is for one that will be shown on an unknown background.

## Storing It As A File

```php
SignaturePad::make('signature')
    ->storeOn('public', 'signatures')   // the column holds signatures/<random>.png
```

Called with nothing, the disk and directory are the ones uploads already use
(`wire-forms.file_upload.*`), so signatures land beside them.

## Showing A Signature

A stored signature needs no display component of its own — an infolist
`ImageEntry` renders both shapes this field stores:

```php
use NyonCode\WireCore\Infolists\Components\ImageEntry;

ImageEntry::make('signature')
    ->disk('private')        // the disk storeOn() wrote to; omit for a data URI
    ->imageSize(120)
```

A data URI is already an image source and passes through untouched; a stored
path resolves through the same URL ladder the field itself uses.

## Extended Example

```php
use Livewire\Component;
use NyonCode\WireForms\Components\Checkbox;
use NyonCode\WireForms\Components\SignaturePad;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class SignDelivery extends Component
{
    use WithForms;

    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->model(DeliveryNote::class)
            ->statePath('data')
            ->schema([
                TextInput::make('received_by')->required(),
                Checkbox::make('goods_undamaged'),
                SignaturePad::make('signature')       // [tl! focus:start]
                    ->label('Signature of the recipient')
                    ->height(220)
                    ->storeOn('private', 'deliveries')
                    ->required(),                     // [tl! focus:end]
            ]);
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

## SignaturePad API

The pad surface. Label, hint, placeholder (the prompt shown on an empty canvas),
`required()`, visibility and the rest are the shared field API, documented in
[Form Fields](index.md).

```php
->height(int $pixels)                          // drawing area height — default 180
->penColor(string|Closure|null $color)         // any CSS colour — default '#111827'
->penWidth(int|float $pixels)                  // stroke width — default 2
->backgroundColor(string|Closure|null $color)  // null (default) draws a transparent PNG
->storeOn(?string $disk = null, ?string $directory = null)  // store a PNG, keep the path
```

And what the pad answers about itself:

```php
->getHeight(): int
->getPenColor(): string
->getPenWidth(): int|float
->getBackgroundColor(): ?string
->storesFile(): bool
->getDisk(): string
->getDirectory(): string
->getImageUrl(mixed $state): ?string           // the current signature as an <img> source
```

## Related

- [Form Fields](index.md) — the shared field API
- [FileUpload](file-upload.md) — the same stored-path shape, for files a user picks
- [Infolists](../../core/infolists/index.md) — `ImageEntry` shows a stored signature
- [Save Lifecycle](../save-lifecycle.md) — where dehydration runs during a save
