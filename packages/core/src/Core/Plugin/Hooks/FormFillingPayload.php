<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use NyonCode\WireCore\Core\Plugin\Contracts\HasHookTarget;
use NyonCode\WireCore\Core\Plugin\HookTarget;

/**
 * Typed payload for the 'form.filling' hook.
 *
 * Forms could be intercepted on the way out and not on the way in. `form.saving`
 * shapes what reaches the record; nothing shaped what reaches the fields, so an
 * application could **add** a field to a module's form ({@see FormConfiguringPayload})
 * and could not change what an existing one arrives holding.
 *
 * The data is what `Form::fill()` was handed — a record's attributes on an edit
 * page, whatever a caller passes anywhere else. Keys are state paths, so what is
 * here is what the fields bind to:
 *
 * ```php
 * $payload->data['currency'] ??= auth()->user()->currency;
 * ```
 *
 * Deliberately **not** dispatched from `getInitialState()`. That answers a
 * different question — the blanks a control needs before anything is bound — and
 * a hook on both would fire twice per edit page, which is how a callback that
 * appends ends up appending twice.
 *
 * Typed only.
 */
final class FormFillingPayload implements HasHookTarget
{
    /**
     * @param  object  $form  The form being filled
     * @param  array<string, mixed>  $data  What the fields are about to hold (modifiable)
     * @param  HookTarget|null  $target  Which component this came from, for scoped callbacks
     */
    public function __construct(
        public readonly object $form,
        public array $data,
        public readonly ?HookTarget $target = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'form' => $this->form,
            'data' => $this->data,
        ];
    }

    public function hookTarget(): ?HookTarget
    {
        return $this->target;
    }
}
