<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use NyonCode\WireCore\Core\Plugin\Contracts\HasHookTarget;
use NyonCode\WireCore\Core\Plugin\HookTarget;

/**
 * Typed payload for the 'form.configuring' hook.
 *
 * Dispatched once, when a form's schema becomes its config — the moment
 * `table.configuring` already had and forms did not, which is why a plugin could
 * add a column to someone else's list and not a field to their form.
 *
 * The schema is the array a form was built with: `TextInput`, `Section`,
 * `Repeater` — layout components included, nested as declared. Replace it, append
 * to it, or filter it; whatever the callbacks leave is what the form is built
 * from, and it is read once because the config is memoized.
 *
 * Typed only. There is no array-payload counterpart and there will not be: the
 * two-dispatch arrangement is a 2.x compatibility debt carried by the seven hooks
 * that predate it, not a pattern to extend.
 */
final class FormConfiguringPayload implements HasHookTarget
{
    /**
     * @param  object  $form  The form being configured
     * @param  array<int, mixed>  $schema  The schema components (modifiable)
     * @param  HookTarget|null  $target  Which component this came from, for scoped callbacks
     */
    public function __construct(
        public readonly object $form,
        public array $schema,
        public readonly ?HookTarget $target = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'form' => $this->form,
            'schema' => $this->schema,
        ];
    }

    public function hookTarget(): ?HookTarget
    {
        return $this->target;
    }
}
