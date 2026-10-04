<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * Accepts a file whose contents are one of the listed types.
 *
 * The same list a file input's `accept` attribute takes: an entry with a slash
 * is a MIME type (`image/*` works — the wildcard is Laravel's `mimetypes`
 * rule's own), anything else an extension, with or without its dot. A file
 * passes when either kind of entry accepts it; asking both rules at once would
 * demand a file match both, which a PDF listed by extension never does against
 * an `image/*` type.
 *
 * Only a file just uploaded is checked. A form editing a record holds the
 * stored path as its value, and a path is not a file to measure — refusing it
 * would make every edit form with an accepted type unsavable.
 */
final class AcceptedFileTypes implements ValidationRule
{
    /** @var list<string> */
    private readonly array $types;

    /** @var list<string> */
    private readonly array $extensions;

    /**
     * @param  array<int, string>  $accepted
     */
    public function __construct(array $accepted)
    {
        $entries = array_values(array_filter(
            array_map(static fn (mixed $entry): string => trim((string) $entry), $accepted),
            static fn (string $entry): bool => $entry !== '',
        ));

        $this->types = array_values(array_filter($entries, static fn (string $entry): bool => str_contains($entry, '/')));
        $this->extensions = array_values(array_map(
            static fn (string $entry): string => ltrim($entry, '.'),
            array_filter($entries, static fn (string $entry): bool => ! str_contains($entry, '/')),
        ));
    }

    public function isEmpty(): bool
    {
        return $this->types === [] && $this->extensions === [];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || $this->isEmpty()) {
            return;
        }

        if ($this->passes($value, 'mimetypes', $this->types) || $this->passes($value, 'mimes', $this->extensions)) {
            return;
        }

        $fail('validation.mimes')->translate(['values' => implode(', ', [...$this->types, ...$this->extensions])]);
    }

    /**
     * @param  list<string>  $entries
     */
    private function passes(UploadedFile $file, string $rule, array $entries): bool
    {
        return $entries !== []
            && Validator::make(['file' => $file], ['file' => $rule.':'.implode(',', $entries)])->passes();
    }
}
