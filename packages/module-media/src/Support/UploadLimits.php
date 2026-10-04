<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use NyonCode\WireForms\Validation\Rules\AcceptedFileTypes;
use NyonCode\WireModuleMedia\Actions\StoreUpload;

/**
 * What the library takes: `wire-module-media.accepts` and `max_size`, as rules.
 *
 * One owner, because a file reaches the library four ways — the drop zone, the
 * picker, the image editor and the resource's create page — and the two keys
 * used to reach exactly one of them. The create page's `FileUpload` was told the
 * limits; the manager stored whatever arrived, so a library configured for
 * two-megabyte images still took a ten-megabyte PDF from its own drop zone.
 * {@see StoreUpload} asks here before it writes a byte, which covers every path
 * that goes through it.
 *
 * What an entry of `accepts` means is {@see AcceptedFileTypes}'s to say — the
 * same rule a form's `FileUpload` checks its accepted types with.
 */
final class UploadLimits
{
    /** Kilobytes, the unit Laravel's `max` rule uses for a file. */
    public static function maxSize(): int
    {
        return max(1, (int) config('wire-module-media.max_size', 10240));
    }

    /**
     * @return list<string>
     */
    public static function accepts(): array
    {
        return array_values(array_filter(
            array_map(static fn (mixed $entry): string => trim((string) $entry), (array) config('wire-module-media.accepts', [])),
            static fn (string $entry): bool => $entry !== '',
        ));
    }

    /**
     * The `accept` attribute for a file input, or null when anything goes.
     *
     * The browser's picker is a convenience and nothing more — a person can
     * still drop any file, so the rules below are what decides.
     */
    public static function acceptAttribute(): ?string
    {
        $accept = array_map(
            static fn (string $entry): string => str_contains($entry, '/') || str_starts_with($entry, '.') ? $entry : '.'.$entry,
            self::accepts(),
        );

        return $accept === [] ? null : implode(',', $accept);
    }

    /**
     * @return list<mixed>
     */
    public static function rules(): array
    {
        $rules = ['file', 'max:'.self::maxSize()];
        $accepted = new AcceptedFileTypes(self::accepts());

        if (! $accepted->isEmpty()) {
            $rules[] = $accepted;
        }

        return $rules;
    }

    /**
     * Refuse a file the library does not take.
     *
     * @throws ValidationException naming what was wrong with it
     */
    public static function check(UploadedFile $upload): void
    {
        Validator::make(['file' => $upload], ['file' => self::rules()], [], ['file' => $upload->getClientOriginalName()])->validate();
    }
}
