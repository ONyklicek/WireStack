<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Contracts;

/**
 * A settings group with a rule no single field can state.
 *
 * A field's own rules see the field. "The format must contain `{number}`" is a
 * field rule; "a start number needs the year it starts in, and the year needs
 * the number" is not — it is about two fields, and the message belongs beside
 * one of them, not in a toast that leaves the reader to find which.
 *
 *   public static function validateSettings(array $data): array
 *   {
 *       return blank($data['start']) === blank($data['start_year'])
 *           ? []
 *           : ['start' => __('Fill in the number and its year, or neither.')];
 *   }
 *
 * Asked after every field rule has passed, with the validated state. An empty
 * answer saves; any message stops the save and is shown under the field it is
 * keyed by — nothing is written, so a group is never half-checked.
 */
interface ValidatesSettings
{
    /**
     * Messages for the fields that are wrong, keyed by field name; empty when the group is valid.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public static function validateSettings(array $data): array;
}
