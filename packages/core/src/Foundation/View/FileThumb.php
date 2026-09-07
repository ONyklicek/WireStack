<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Enums\FileKind;

/**
 * Blade component: <x-wire::file-thumb :name :mime :url />
 *
 * One answer to "what does this file look like", for every surface that shows a
 * file: the picture when there is one, and otherwise a card carrying the file's
 * own extension over its family's colour.
 *
 * Six places used to decide this themselves and all six decided the same
 * impoverished thing — an image, or one grey document icon — so a folder of a
 * catalogue, a price list, a contract and a print archive rendered as four
 * identical grey rectangles. See ADR 0033.
 *
 * **The box is the caller's.** This fills whatever it is put in, so the same
 * component is a 32-pixel row thumbnail and a full-bleed grid tile; `size` scales
 * what is drawn inside it, not the container.
 */
class FileThumb extends Component
{
    use HasColor;

    public FileKind $kind;

    /** The letters on the card: the file's own extension, or its family. */
    public string $wordmark;

    public string $colorClasses;

    public bool $showsImage;

    /** @var array{icon: string, text: string} */
    public array $scale;

    public function __construct(
        public ?string $name = null,
        public ?string $mime = null,
        /** The preview to show, when there is one. Null renders the card. */
        public ?string $url = null,
        public ?string $alt = null,
        public string $size = 'md',
        /** Resolution descriptors (`… 1x, … 2x`), for a retina screen. */
        public ?string $srcset = null,
        /** The picture's own dimensions, so the page does not reflow as it lands. */
        public ?int $width = null,
        public ?int $height = null,
        /** The average colour, painted before the image arrives. */
        public ?string $placeholder = null,
    ) {
        $this->kind = FileKind::for($mime, $name);

        // The family is not the wordmark: `XLSX` and `ODS` are both a
        // spreadsheet and must not both read "XLSX". Only a file whose name
        // carries no usable extension falls back to the family's label.
        $this->wordmark = FileKind::extensionOf($name) ?? $this->kind->label();

        $this->colorClasses = self::getBadgeColorClasses($this->kind->color()->value);
        $this->showsImage = $this->kind->isImage() && $this->url !== null;

        $this->scale = match ($size) {
            // A 32-pixel row has no room for both, and the letters are the part
            // that identifies the file.
            'sm' => ['icon' => '', 'text' => 'text-[9px]'],
            'lg' => ['icon' => 'h-7 w-7', 'text' => 'text-sm'],
            default => ['icon' => 'h-5 w-5', 'text' => 'text-[11px]'],
        };
    }

    /**
     * The colour this thumbnail is drawn in, for {@see HasColor}.
     *
     * The trait's instance resolvers ask their host for it, exactly as
     * {@see Badge} answers them. Here it is not owner-supplied at all: the
     * family decides, which is the whole point of the enum.
     */
    public function getColor(): string
    {
        return $this->kind->color()->value;
    }

    public function render(): View
    {
        return view('wire-core::foundation.file-thumb');
    }
}
