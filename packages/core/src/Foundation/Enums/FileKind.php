<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Enums;

use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasColor;

/**
 * What a file *is*, as a family rather than as a format.
 *
 * One vocabulary for the question every surface that shows a file has to answer
 * and, before this, answered on its own: an image, or one grey document icon.
 * Six places did that — two views in `wire-forms`, three in `wire-module-media`
 * and one table column — so a catalogue PDF, a price list, a contract and a
 * print archive all rendered as four identical grey rectangles.
 *
 * It lives in `core` rather than beside the media library because two packages
 * have the problem and only one of them is the media module. See ADR 0033.
 *
 * **Closed, with an `Other` case.** A vocabulary an application can rewrite is
 * one no package can rely on: `Spreadsheet` has to mean a spreadsheet. Anything
 * unmatched is `Other`, which renders as the file's own extension on a neutral
 * ground and is a perfectly good answer.
 *
 * The family is not the wordmark. `XLSX` and `ODS` are both {@see self::Spreadsheet}
 * and must not both read "XLSX", so the letters on a tile come from the file's
 * own name through {@see self::extensionOf()}.
 */
enum FileKind: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Spreadsheet = 'spreadsheet';
    case Presentation = 'presentation';
    case Archive = 'archive';
    case Code = 'code';
    case Other = 'other';

    /**
     * Mime types that are true of almost anything, and so decide nothing.
     *
     * `application/octet-stream` is what most archives and half of all uploads
     * from a strict browser arrive as, and `text/plain` is what a `.csv` — a
     * spreadsheet to every person who opens it — is usually served with. For
     * these the file's name is the better witness, and it is asked first.
     */
    private const GENERIC = [
        'application/octet-stream',
        'binary/octet-stream',
        'application/unknown',
        'text/plain',
    ];

    /**
     * The family of a file, from what the disk said it is and what it is called.
     *
     * The mime type goes first because it is read from the stored file rather
     * than from what a browser claimed about it. The name is consulted in
     * exactly two cases, both of them real: a null mime type — rows written
     * before it was recorded, and disks that could not answer — and a mime type
     * from {@see self::GENERIC} that means nothing.
     */
    public static function for(?string $mime, ?string $name = null): self
    {
        $mime = strtolower(trim((string) $mime));
        $fromName = self::fromExtension(self::extensionOf($name));

        if ($mime === '' || in_array($mime, self::GENERIC, true)) {
            // `text/plain` with no usable name is still text, which is a
            // document. Everything else generic is genuinely unknown.
            return $fromName ?? ($mime === 'text/plain' ? self::Document : self::Other);
        }

        return self::fromMime($mime) ?? $fromName ?? self::Other;
    }

    /**
     * The letters shown on a type card: the file's own extension, upper-cased.
     *
     * Null rather than a guess for anything that is not one — a name with no
     * dot, a trailing dot, "report.final version" — so the caller falls back to
     * the family's label instead of printing a fragment of a sentence.
     */
    public static function extensionOf(?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '' || ! str_contains($name, '.')) {
            return null;
        }

        $extension = substr($name, (int) strrpos($name, '.') + 1);

        if ($extension === '' || strlen($extension) > 5 || preg_match('/^[A-Za-z0-9]+$/', $extension) !== 1) {
            return null;
        }

        return strtoupper($extension);
    }

    /**
     * The hue this family is drawn in, from the shared palette.
     *
     * A `Color` case rather than a class string, so no Tailwind vocabulary is
     * invented here and the support policy in ADR 0005 is untouched: every
     * surface resolves it through {@see HasColor}
     * exactly as it resolves an owner-supplied colour.
     */
    public function color(): Color
    {
        return match ($this) {
            self::Image => Color::Violet,
            self::Video => Color::Pink,
            self::Audio => Color::Teal,
            self::Document => Color::Blue,
            self::Spreadsheet => Color::Green,
            self::Presentation => Color::Orange,
            self::Archive => Color::Yellow,
            self::Code => Color::Slate,
            self::Other => Color::Gray,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Image => 'outline:photo',
            self::Video => 'outline:film',
            self::Audio => 'outline:musical-note',
            self::Document => 'outline:document-text',
            self::Spreadsheet => 'outline:table-cells',
            self::Presentation => 'outline:presentation-chart-bar',
            self::Archive => 'outline:archive-box',
            self::Code => 'outline:code-bracket',
            self::Other => 'outline:document',
        };
    }

    public function label(): string
    {
        return (string) __('wire-core::messages.file_kinds.'.$this->value);
    }

    /** Whether there are pixels worth previewing. */
    public function isImage(): bool
    {
        return $this === self::Image;
    }

    private static function fromMime(string $mime): ?self
    {
        $prefix = strtok($mime, '/');

        $byPrefix = match ($prefix) {
            'image' => self::Image,
            'video' => self::Video,
            'audio' => self::Audio,
            default => null,
        };

        if ($byPrefix !== null) {
            return $byPrefix;
        }

        // Matched on a fragment rather than on the full type: the Office types
        // are sixty characters of vendor namespace whose only distinguishing
        // part is the last word, and listing them whole is how a `.pptm` gets
        // missed.
        return match (true) {
            str_contains($mime, 'spreadsheet'), str_contains($mime, 'ms-excel'), $mime === 'text/csv' => self::Spreadsheet,
            str_contains($mime, 'presentation'), str_contains($mime, 'ms-powerpoint') => self::Presentation,
            str_contains($mime, 'zip'), str_contains($mime, 'compressed'), str_contains($mime, 'x-tar'), $mime === 'application/gzip' => self::Archive,
            $mime === 'application/pdf',
            str_contains($mime, 'wordprocessing'), str_contains($mime, 'msword'),
            str_contains($mime, 'opendocument.text'), str_contains($mime, 'rtf'),
            $mime === 'text/markdown' => self::Document,
            str_contains($mime, 'json'), str_contains($mime, 'xml'), str_contains($mime, 'javascript'),
            $mime === 'text/html', $mime === 'text/css', $mime === 'application/sql' => self::Code,
            default => null,
        };
    }

    private static function fromExtension(?string $extension): ?self
    {
        if ($extension === null) {
            return null;
        }

        return match (strtolower($extension)) {
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp', 'ico', 'tif', 'tiff', 'heic' => self::Image,
            'mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v', 'mpg', 'mpeg' => self::Video,
            'mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'wma' => self::Audio,
            'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'md', 'pages' => self::Document,
            'xls', 'xlsx', 'ods', 'csv', 'tsv', 'numbers' => self::Spreadsheet,
            'ppt', 'pptx', 'pptm', 'odp', 'key' => self::Presentation,
            'zip', 'tar', 'gz', 'tgz', '7z', 'rar', 'bz2', 'xz' => self::Archive,
            'json', 'xml', 'html', 'htm', 'css', 'js', 'ts', 'php', 'yml', 'yaml', 'sql', 'sh' => self::Code,
            default => null,
        };
    }
}
