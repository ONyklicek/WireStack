<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin;

use Closure;
use NyonCode\WireCore\Foundation\Enums\Hook;

/**
 * The guard every typed dispatch site needs, written once.
 *
 * A lifecycle point that offers a hook has three things to do before it can
 * offer one: check the container has a manager at all (a package used without
 * the service provider, and every unit test that builds a component by hand),
 * check something is actually listening, and only then pay for a payload. Two
 * sites did that by hand — `WithTable::composeTableThroughPlugins()` and
 * `Form::configuredSchema()` — and six more were about to, which is the point at
 * which the guard stops being three lines and starts being a rule nobody can see.
 *
 * The payload arrives as a **closure**, and that is the whole reason this is not
 * just a `hasHook()` helper: building one means reading a table's columns, a
 * menu's entries or a dashboard's widgets, and an application that installs no
 * plugin should pay for none of it. The closure runs only after something has
 * been found to hand it to.
 *
 * ```php
 * $payload = HookDispatch::typed(
 *     Hook::WidgetConfiguring,
 *     fn () => new WidgetConfiguringPayload(widgets: $this->getWidgets(), target: …),
 * );
 *
 * $widgets = $payload !== null ? $payload->widgets : $this->getWidgets();
 * ```
 *
 * **Null means nobody listened, not "nothing changed".** A caller that folded
 * the two together with `??` would restore its own value whenever a callback
 * emptied the array — a filter that removes every column is a legitimate answer,
 * and `?? $columns` would silently undo it. Compare against null explicitly.
 */
final class HookDispatch
{
    /**
     * The manager, when there is one and something is listening for this name.
     *
     * The legacy half of the same guard. Seven names predate `runTypedHook()` and
     * are dispatched **both** ways for backwards compatibility, and what each
     * site does with the two results genuinely differs — `table.querying` reads a
     * forced sort out of the array and its columns out of the DTO, `action.executed`
     * reads neither. So this hands back the manager rather than pretending the
     * four sites share a shape they do not:
     *
     * ```php
     * $manager = HookDispatch::manager(Hook::FormSaving);
     *
     * if ($manager !== null) {
     *     $data = $manager->runHook(...)['data'] ?? $data;
     *     $data = $manager->runTypedHook(...)->data;
     * }
     * ```
     *
     * The `hasHook()` short-circuit is the part the legacy sites never had: they
     * built both payloads whenever a manager was bound, which on
     * `table.configuring` is once per table per render in an application that
     * registered no callback at all.
     */
    public static function manager(Hook|string $hook): ?PluginManager
    {
        if (! app()->bound(PluginManager::class)) {
            return null;
        }

        $manager = app(PluginManager::class);

        return $manager->hasHook($hook) ? $manager : null;
    }

    /**
     * Run a typed hook, if anything is listening.
     *
     * @template TPayload of object
     *
     * @param  Closure(): TPayload  $payload  Built only when a callback exists to receive it.
     * @return TPayload|null Null when no callback is registered for this hook.
     */
    public static function typed(Hook|string $hook, Closure $payload): ?object
    {
        $manager = self::manager($hook);

        if ($manager === null) {
            return null;
        }

        /** @var TPayload $result */
        $result = $manager->runTypedHook($hook, $payload());

        return $result;
    }
}
