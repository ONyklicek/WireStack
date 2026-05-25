<?php

declare(strict_types=1);

namespace NyonCode\WireSortable;

use NyonCode\WireCore\Core\Plugin\Contracts\HasDependencies;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireTable\Table;

/**
 * Plugin for reorderable tables.
 *
 * Registers a table.querying hook that forces ordering by the sort column
 * when the table is in reorder mode (isReordering on the component).
 */
class SortablePlugin implements HasDependencies, Plugin
{
    public function getId(): string
    {
        return 'sortable';
    }

    /**
     * @return array<int, string>
     */
    public function dependencies(): array
    {
        return [];
    }

    public function register(PluginManager $manager): void
    {
        $manager->hook('table.querying', function (array $payload) {
            $table = $payload['table'] ?? null;

            if (! $table instanceof Table) {
                return $payload;
            }

            $hasMethod = $table instanceof SortableTable || Table::hasMacro('isReorderable');

            if (! $hasMethod || ! $table->isReorderable()) {
                return $payload;
            }

            // Check if the component is in reorder mode
            $component = $table->getLivewireComponent();

            if ($component !== null && method_exists($component, 'isTableReordering') && $component->isTableReordering()) {
                $payload['force_sort_column'] = $table->getOrderColumn();
                $payload['force_sort_direction'] = 'asc';
            }

            return $payload;
        });
    }

    public function boot(PluginManager $manager): void
    {
        //
    }
}
