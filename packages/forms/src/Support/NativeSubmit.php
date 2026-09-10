<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Support;

use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Contracts\CanBeDehydrated;
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
 *
 * Reaching it is only half of catching it. A repeater is a `LayoutComponent`,
 * so a walk that recurses into every layout walks straight past the repeater
 * into its template children, switches those, and lets the repeater render into
 * a native form — where it fails as `Using $this when not in object context`,
 * from a view, naming nothing. Hence the state question below.
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
            // A layout that only *groups* fields is recursed into and its leaves
            // answer for themselves. One that carries state of its own is not:
            // `Repeater` and `Builder` are `LayoutComponent`s too, and their
            // children live at per-item paths a browser form cannot express. So
            // the question is not "is this a layout" but "does it hold state" —
            // asked as `CanBeDehydrated`, which is what having state to hand
            // back means here, rather than as a list of two class names that the
            // third repeating layout would not be on.
            if ($component instanceof LayoutComponent && ! $component instanceof CanBeDehydrated) {
                self::prepare($component->getSchema());

                continue;
            }

            // A stray value in a schema — it is `array<int, mixed>` and
            // `FormRuntime` skips what is not a component; matching that keeps a
            // loose value from becoming a "cannot submit natively" error about
            // something that is not a field.
            //
            // `LayoutComponent` is named beside `Component` because it is not
            // one: the two are separate bases, so a stateful layout that reached
            // here would be skipped by a check that asked only about `Component`
            // — and skipping is exactly the silence this class exists to avoid.
            if (! $component instanceof Component && ! $component instanceof LayoutComponent) {
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
