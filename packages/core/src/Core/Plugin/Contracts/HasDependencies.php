<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Contracts;

/**
 * Opt-in interface for plugins that require other plugins.
 *
 * When a plugin implements this interface, the PluginManager checks
 * that all listed dependencies are already registered before allowing
 * this plugin to register. If a dependency is missing, a RuntimeException
 * is thrown.
 *
 * Example:
 *   class MyPlugin implements Plugin, HasDependencies {
 *       public function dependencies(): array { return ['sortable']; }
 *   }
 */
interface HasDependencies
{
    /**
     * Plugin IDs that must be registered before this plugin.
     *
     * @return array<int, string>
     */
    public function dependencies(): array;
}
