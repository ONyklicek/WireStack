<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Support;

use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireForms\Contracts\SupportsNativeSubmit;
use NyonCode\WireForms\Exceptions\FormConfigurationException;

/**
 * Puts a schema into native-submit mode, and refuses the fields that cannot be.
 *
 * The refusal is the point (ADR 0036 §2). A field bound only with `wire:model`
 * carries no `name`, so a browser posts nothing for it: rendered inside a native
 * `<form>` it looks correct, validates on the client, and submits an empty
 * value. On a sign-in screen that is a password field that silently sends
 * nothing. An exception at render is the cheap version of that discovery.
 *
 * It walks the declared schema rather than the form's flat component list,
 * which is not a preference either: `FormRuntime::flattenComponents()` skips a
 * `Repeater` deliberately — its children live at per-item wildcard paths — so a
 * repeater is exactly the kind of field that cannot submit natively *and* would
 * never reach a check written over the flat list.
 */
final class NativeSubmit
{
    /**
     * Switch every field in the schema to native rendering.
     *
     * @param  array<int, mixed>  $schema
     *
     * @throws FormConfigurationException when a field cannot render for a native submit
     */
    public static function prepare(array $schema): void
    {
        foreach ($schema as $component) {
            // A layout holds fields rather than being one; recurse and let the
            // leaves answer for themselves.
            if ($component instanceof LayoutComponent) {
                self::prepare($component->getSchema());

                continue;
            }

            if (! $component instanceof Component) {
                continue;
            }

            if (! $component instanceof SupportsNativeSubmit) {
                throw FormConfigurationException::cannotSubmitNatively(
                    $component->getName(),
                    $component::class,
                );
            }

            $component->nativeSubmit();
        }
    }
}
