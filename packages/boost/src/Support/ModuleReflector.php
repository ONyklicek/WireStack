<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Support;

use NyonCode\WireCore\Core\Modules\DomainModule;
use NyonCode\WireCore\Core\Plugin\Contracts\HasDependencies;
use NyonCode\WireCore\Core\Plugin\PluginManager;

/**
 * What an application's domain modules declare.
 *
 * The domain axis is the one thing about an application that no other tool can
 * show: `describe-resource` lists resources and knows nothing about which
 * business area they belong to, and a module is exactly that grouping. Reading
 * it is cheap — a module is a declaration, so this asks it what it names and
 * never builds anything.
 *
 * Modules come from {@see PluginManager} because a module *is* a plugin; there
 * is no module registry to read, deliberately (V2.6 step 5).
 */
class ModuleReflector
{
    public function __construct(private PluginManager $plugins) {}

    /**
     * Every registered module, in registration order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $described = [];

        foreach ($this->plugins->all() as $plugin) {
            if ($plugin instanceof DomainModule) {
                $described[] = $this->describeModule($plugin);
            }
        }

        return $described;
    }

    /**
     * One module by id, or null when nothing is registered under it.
     *
     * @return array<string, mixed>|null
     */
    public function describe(string $id): ?array
    {
        $plugin = $this->plugins->get($id);

        return $plugin instanceof DomainModule ? $this->describeModule($plugin) : null;
    }

    /** @return array<int, string> */
    public function ids(): array
    {
        return array_column($this->all(), 'id');
    }

    /**
     * @return array<string, mixed>
     */
    private function describeModule(DomainModule $module): array
    {
        $group = $module->navigation();

        return [
            'id' => $module->getId(),
            'class' => $module::class,
            'dependencies' => $module instanceof HasDependencies ? $module->dependencies() : [],
            'resources' => $module->resources(),
            'dashboards' => $module->dashboards(),
            'navigationGroup' => $group === null ? null : [
                'key' => $group->getKey(),
                'label' => $group->getLabel(),
                'icon' => $group->getIcon(),
                'sort' => $group->getSort(),
            ],
        ];
    }
}
