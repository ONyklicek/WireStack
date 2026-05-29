<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Contracts;

/**
 * Opt-in interface for plugins that accept user configuration.
 *
 * When a plugin implements this interface, the PluginManager merges
 * the plugin's default config with user-provided values from
 * `config('wire-core.plugins.config.{pluginId}')`.
 *
 * The merged result is available via `PluginManager::getPluginConfig($id)`.
 *
 * Example:
 *   class ExportPlugin implements Plugin, HasConfiguration {
 *       public function defaultConfig(): array {
 *           return ['format' => 'csv', 'chunk_size' => 500];
 *       }
 *   }
 *
 *   // User overrides in config/wire-core.php:
 *   'plugins' => ['config' => ['export' => ['format' => 'xlsx']]]
 */
interface HasConfiguration
{
    /**
     * Default configuration values for this plugin.
     *
     * @return array<string, mixed>
     */
    public function defaultConfig(): array;
}
