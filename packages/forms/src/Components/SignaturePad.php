<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use NyonCode\WireCore\Foundation\Contracts\DehydratesState;
use NyonCode\WireCore\Foundation\Support\CssColor;
use NyonCode\WireCore\Foundation\Support\StoredFileUrlResolver;

/**
 * A signature drawn with a pointer — finger, stylus or mouse — on a canvas.
 *
 * State is the drawing as a `data:image/png;base64,…` URI: the canvas produces
 * one, and it round-trips through Livewire like any other string. That is what
 * the field stores by default, which suits a text/blob column and a
 * consent-style record that is read back rarely.
 *
 * {@see storeOn()} moves the bytes to a disk instead, and then state carries the
 * stored path — the same shape a FileUpload leaves behind, resolved for display
 * through the same `StoredFileUrlResolver` ladder, so a signature and an upload
 * are rendered from the record the same way.
 *
 * The conversion happens at the state boundary, not while drawing: the browser
 * only ever holds the data URI, so an interrupted form loses a signature the
 * same way it loses any other unsaved field, and no orphaned file is written
 * for a form that was never submitted.
 *
 * `SignaturePad::make('signature')->height(220)->storeOn('public', 'signatures')`
 */
class SignaturePad extends Field implements DehydratesState
{
    /** The prefix every canvas drawing arrives with. */
    private const DATA_URI = 'data:image/png;base64,';

    protected int $height = 180;

    protected string|Closure|null $penColor = null;

    protected int|float $penWidth = 2;

    protected string|Closure|null $backgroundColor = null;

    protected ?string $disk = null;

    protected ?string $directory = null;

    protected bool $storesFile = false;

    /**
     * Drawings already written to the disk during this request, keyed by the
     * data URI they came from.
     *
     * A host may dehydrate the same state twice — once to pre-validate outside
     * its transaction, once with the record locked — and a second write would
     * leave an orphaned copy of the same signature behind, plus a path the first
     * caller never saw. Answering from here keeps the transform a function of
     * its argument, which is what {@see DehydratesState} asks for.
     *
     * @var array<string, string>
     */
    private array $stored = [];

    /** How tall the drawing area is, in CSS pixels (default 180). */
    public function height(int $pixels): static
    {
        $this->height = $pixels;

        return $this;
    }

    /** The ink colour, as a CSS colour (default `#111827`, the ink of body text). */
    public function penColor(string|Closure|null $color): static
    {
        $this->penColor = $color;

        return $this;
    }

    /** How wide the stroke is, in CSS pixels (default 2). */
    public function penWidth(int|float $pixels): static
    {
        $this->penWidth = $pixels;

        return $this;
    }

    /**
     * Paint the canvas behind the signature, as a CSS colour.
     *
     * Left unset the PNG is transparent, which is what a signature printed onto
     * a document wants; a colour here is for one that will be shown on an
     * unknown background.
     */
    public function backgroundColor(string|Closure|null $color): static
    {
        $this->backgroundColor = $color;

        return $this;
    }

    /**
     * Store the signature as a PNG file on a disk and keep only its path in the
     * column, instead of the data URI itself.
     *
     * The disk and directory default to the ones uploads already use
     * (`wire-forms.file_upload.*`), so signatures land beside them.
     */
    public function storeOn(?string $disk = null, ?string $directory = null): static
    {
        $this->storesFile = true;
        $this->disk = $disk;
        $this->directory = $directory;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    public function getHeight(): int
    {
        return $this->height;
    }

    /** The ink colour, guarded through the canonical CSS-colour owner. */
    public function getPenColor(): string
    {
        return CssColor::sanitize($this->evaluate($this->penColor)) ?? '#111827';
    }

    public function getPenWidth(): int|float
    {
        return $this->penWidth;
    }

    /** The canvas colour, or null for a transparent drawing. */
    public function getBackgroundColor(): ?string
    {
        return CssColor::sanitize($this->evaluate($this->backgroundColor));
    }

    public function storesFile(): bool
    {
        return $this->storesFile;
    }

    public function getDisk(): string
    {
        return $this->disk ?? config('wire-forms.file_upload.disk', 'public');
    }

    public function getDirectory(): string
    {
        return $this->directory ?? config('wire-forms.file_upload.directory', 'uploads');
    }

    /**
     * The current signature as something an `<img>` can show — a data URI is
     * already one, a stored path resolves through the shared URL ladder.
     */
    public function getImageUrl(mixed $state): ?string
    {
        if (! is_string($state) || $state === '') {
            return null;
        }

        return StoredFileUrlResolver::resolve($state, $this->storesFile ? $this->getDisk() : null);
    }

    // ─── State ─────────────────────────────────────────────────────

    /**
     * A fresh drawing → what the column holds.
     *
     * Only a data URI is ever converted. State that already holds a stored path
     * is a signature nobody re-drew, and writing it out again would leave a
     * second copy of the same image on the disk with every save.
     */
    public function dehydrateState(mixed $state, ?Model $record = null): mixed
    {
        if (! is_string($state) || $state === '') {
            return null;
        }

        if (! $this->storesFile || ! str_starts_with($state, self::DATA_URI)) {
            return $state;
        }

        if (isset($this->stored[$state])) {
            return $this->stored[$state];
        }

        $bytes = base64_decode(substr($state, strlen(self::DATA_URI)), true);

        if ($bytes === false) {
            return null;
        }

        $path = trim($this->getDirectory(), '/').'/'.Str::random(40).'.png';

        Storage::disk($this->getDisk())->put($path, $bytes);

        return $this->stored[$state] = $path;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.signature-pad';
    }
}
