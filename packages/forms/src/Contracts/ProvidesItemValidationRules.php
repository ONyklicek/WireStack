<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Contracts;

use NyonCode\WireForms\Validation\FormValidationResolver;

/**
 * A field whose state is a list, and whose rules describe one *item* of it.
 *
 * Rules on the field's own key describe the list (`max:3` = at most three
 * items); these describe each entry, and the resolver mounts them at the
 * wildcard path (`data.photos.*`).
 *
 * Without this a multiple FileUpload can bound how many files it takes but not
 * how big each one is: the same `max:` cannot mean kilobytes and a count at
 * once. {@see FormValidationResolver::getRules()} mounts the result; the
 * Repeater has its own richer path, since its items are whole schemas.
 */
interface ProvidesItemValidationRules
{
    /**
     * Rules applied to every item of this field's list.
     *
     * An empty list adds no wildcard entry at all.
     *
     * @return array<int, mixed>
     */
    public function itemValidationRules(): array;
}
