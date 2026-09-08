<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use NyonCode\WireCore\Core\Plugin\Contracts\HasHookTarget;
use NyonCode\WireCore\Core\Plugin\HookTarget;

/**
 * Typed payload for the 'infolist.configuring' hook.
 *
 * The read-only half of {@see FormConfiguringPayload}, and it shipped for the
 * same reason: four module packages send five resources whose detail pages are
 * built inside code the application does not own, so "add a field to the users
 * form" was writable and "add a row to its detail" was not.
 *
 * The schema is the array the infolist was built with — entries and layout
 * components, nested as declared. Whatever the callbacks leave is what renders,
 * and it is read once because the configured schema is memoized:
 *
 * ```php
 * $payload->schema = [...$payload->schema, TextEntry::make('internal_note')];
 * ```
 *
 * Typed only. There is no array counterpart and there will not be, for the
 * reason {@see FormConfiguringPayload} gives.
 */
final class InfolistConfiguringPayload implements HasHookTarget
{
    /**
     * @param  object  $infolist  The infolist being configured
     * @param  array<int, mixed>  $schema  The entries and layout components (modifiable)
     * @param  HookTarget|null  $target  Which component this came from, for scoped callbacks
     */
    public function __construct(
        public readonly object $infolist,
        public array $schema,
        public readonly ?HookTarget $target = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'infolist' => $this->infolist,
            'schema' => $this->schema,
        ];
    }

    public function hookTarget(): ?HookTarget
    {
        return $this->target;
    }
}
