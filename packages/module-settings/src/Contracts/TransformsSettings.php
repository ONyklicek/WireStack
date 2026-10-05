<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Contracts;

/**
 * A settings group whose form does not hold its values the way they are stored.
 *
 * A form field has a shape of its own — a time picker answers `08:30`, a repeater
 * answers a list of rows, a set of checkboxes answers whatever was ticked in the
 * order it was ticked — and the value something else reads is usually narrower:
 * `08:30:00`, the rows sorted and de-duplicated, only the cases an enum knows.
 * Without a seam between the two, a group that needs the narrower shape has to
 * give up the module's screen and write its own.
 *
 *   public static function fromStorage(array $values): array
 *   {
 *       return [...$values, 'shift_start' => substr((string) $values['shift_start'], 0, 5)];
 *   }
 *
 *   public static function toStorage(array $data): array
 *   {
 *       return [...$data, 'shift_start' => $data['shift_start'].':00'];
 *   }
 *
 * **`toStorage()` runs after the form has shaped its own fields** — validated,
 * and a `FileUpload`'s pending upload already moved to its disk — so what it
 * receives is what the form would otherwise have written, and what it returns
 * is what is written instead. Keys it drops are not written; keys it adds are.
 */
interface TransformsSettings
{
    /**
     * The form's state, from the stored values (declared defaults included).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function fromStorage(array $values): array;

    /**
     * The values to store, from the form's validated state.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toStorage(array $data): array;
}
