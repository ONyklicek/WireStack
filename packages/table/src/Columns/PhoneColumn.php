<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\ValueObjects\DialingCodes;

/**
 * A phone number, read as a number and dialled with one click.
 *
 * A column holding E.164 shows `+420123456789`, which is the shape a database
 * wants and the shape a person does not: nobody reads a twelve-digit run. This
 * writes the stored number the way `PhoneInput` writes it — same prefix, same
 * grouping, from the same {@see DialingCodes} table — so the number reads
 * identically wherever it appears, and links it as `tel:` so a phone or a
 * softphone dials it.
 *
 * The link is the point of the column; the formatting alone is a
 * `TextColumn::make('phone')->formatStateUsing(…)` away. `notCallable()` keeps
 * the formatting and drops the link, for a number nobody is meant to ring.
 *
 * ```php
 * PhoneColumn::make('phone')->copyable()
 * ```
 */
class PhoneColumn extends TextColumn
{
    protected bool $callable = true;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->formatStateUsing(static fn (mixed $state): string => DialingCodes::written(
            is_scalar($state) ? (string) $state : '',
        ));

        // Registering the callback is also what tells the cell it has a link
        // shape at all — the render path only asks for a URL when one is set.
        $this->actionUrl($this->diallerUrl(...));
    }

    /**
     * Show the number without linking it — an archived contact, a number held
     * for reference, a report nobody dials from.
     */
    public function notCallable(bool $condition = true): static
    {
        $this->callable = ! $condition;

        return $this;
    }

    public function isCallable(): bool
    {
        return $this->callable;
    }

    /**
     * The `tel:` href for a record, or nothing when this column does not link.
     *
     * The href is the raw number: a dialler wants digits, not the spacing that
     * makes the cell readable.
     */
    private function diallerUrl(Model $record): ?string
    {
        $number = self::digitsOf($this->getState($record));

        return $this->isCallable() && $number !== '' ? 'tel:+'.$number : null;
    }

    /** The number as digits alone, which is all a `tel:` href carries. */
    private static function digitsOf(mixed $value): string
    {
        return is_scalar($value)
            ? (string) preg_replace('/\D/', '', (string) $value)
            : '';
    }
}
