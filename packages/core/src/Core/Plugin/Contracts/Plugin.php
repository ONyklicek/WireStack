<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Contracts;

use NyonCode\WireCore\Core\Plugin\PluginManager;

/**
 * Contract for Wire ecosystem plugins.
 *
 * Plugins can extend tables, forms, queries, and other components
 * through a unified registration and boot lifecycle.
 *
 * Example:
 *   class ExportPlugin implements Plugin {
 *       public function getId(): string { return 'export'; }
 *       public function register(PluginManager $manager): void { ... }
 *       public function boot(PluginManager $manager): void { ... }
 *   }
 */
interface Plugin
{
    /**
     * Unique plugin identifier.
     */
    public function getId(): string;

    /**
     * Register plugin bindings, pipes, strategies, etc.
     *
     * Called during service provider register phase.
     * Do NOT resolve services here — they may not be available yet.
     */
    public function register(PluginManager $manager): void;

    /**
     * Boot the plugin after all plugins are registered.
     *
     * Called during service provider boot phase.
     * Safe to resolve services, register views, etc.
     */
    public function boot(PluginManager $manager): void;
}
