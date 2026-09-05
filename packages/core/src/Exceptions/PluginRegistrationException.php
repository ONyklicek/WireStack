<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * Thrown when a plugin cannot be admitted to the registry.
 *
 * Every case is about the state of the registry, or about what a declaration
 * names, rather than about a bad argument at the call site: the plugin itself is
 * usually fine, it is the order, the company it keeps, or the class name in a
 * config file that is wrong.
 */
final class PluginRegistrationException extends RuntimeException implements WireException
{
    public static function alreadyRegistered(string $id): self
    {
        return new self("Plugin '{$id}' is already registered.");
    }

    public static function missingDependency(string $id, string $dependency): self
    {
        return new self("Plugin '{$id}' requires '{$dependency}' which is not registered.");
    }

    /**
     * A plugin arriving after the manager has booted, which is too late to
     * install and too quiet to notice.
     *
     * The registration itself would succeed — `has()` answers true afterwards,
     * which is what makes this worth an exception. What silently does not happen
     * is everything that reads the list once, during boot: `Plugin::boot()` is
     * never called, and a `DomainModule`'s resources, dashboards and navigation
     * group are spread by the provider in a pass that has already run. A module can be "installed" and have no menu
     * entry, no route and no dashboard.
     *
     * It cannot be fixed by booting the late plugin here: page routes are
     * registered inside the provider's `boot()` because Laravel installs a
     * cached route collection after it, so a module arriving later cannot be
     * routed at all — whoever registers has to move, and the message says where.
     */
    public static function registeredAfterBoot(string $id): self
    {
        return new self(
            "Plugin '{$id}' was registered after the plugin manager booted, which is too ".
            'late for it to be installed: boot() is never called on it, and a domain '.
            "module's resources, dashboards and navigation never reach the registries. ".
            'Register it during the register phase of a service provider instead: '.
            '`$this->app->resolving(PluginManager::class, ...)` from a package provider, '.
            'or the wire-core.plugins config array from an application.'
        );
    }

    /**
     * `wire-core.plugins` holding something that is not a list.
     *
     * Reached by a published config file someone edited into a string or a
     * single class name. Left alone it is a PHP error inside a foreach in a
     * provider — a stack trace pointing at framework code for a mistake that is
     * one line of the application's own config.
     */
    public static function invalidPluginList(mixed $value): self
    {
        return new self(
            'wire-core.plugins must be an array of plugin class names, but it holds '.
            get_debug_type($value).'. A single plugin is a one-element array.'
        );
    }

    /**
     * Something in `wire-core.plugins` that cannot be a plugin.
     *
     * Ignored until now, and that is the failure this replaces: a typo in a
     * class name, or a class that forgot the contract, meant the plugin simply
     * did not exist — no menu, no hooks, no error. Config is read at boot, so
     * this is early enough that it cannot reach a request.
     */
    public static function notAPlugin(string $class, string $contract): self
    {
        $reason = class_exists($class) || interface_exists($class)
            ? "it does not implement [{$contract}]"
            : 'the class does not exist';

        return new self(
            "[{$class}] is listed in wire-core.plugins but cannot be registered: {$reason}. ".
            'A plugin declares its id and its register/boot lifecycle through that '.
            'contract, which is what the manager installs it by.'
        );
    }
}
