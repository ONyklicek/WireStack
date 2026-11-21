<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Enums;

use NyonCode\WireCore\Foundation\Support\NameLabel;

/**
 * How a component's name becomes its label when no `->label()` was given
 * (`wire-core.labels.from_name`, read by {@see NameLabel}).
 *
 * - `Headline` — every word capitalised: `first_name` → "First Name" (the default).
 * - `Sentence` — only the first word: `first_name` → "First name".
 * - `Name` — the name as written: `first_name` → "first_name".
 *
 * Whatever the style, a name that is already text — it contains a space — is
 * kept as written, only its first letter raised: `Section::make('Pro montážníky')`
 * reads "Pro montážníky" under every style, never "Pro Montážníky".
 */
enum LabelStyle: string
{
    case Headline = 'headline';
    case Sentence = 'sentence';
    case Name = 'name';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
