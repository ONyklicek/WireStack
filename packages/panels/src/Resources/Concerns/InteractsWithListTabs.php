<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\View\Palette;
use NyonCode\WirePanels\Resources\ListTab;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use NyonCode\WireTable\Table;

/**
 * Tabs above a list, each a narrower list of the same records.
 *
 *   protected function tabs(): array
 *   {
 *       return [
 *           ListTab::make('all'),
 *           ListTab::make('open')->query(fn (Builder $q) => $q->whereNull('closed_at'))->showCount(),
 *       ];
 *   }
 *
 * The active tab is the public `$activeTab`, kept in the URL as `?tab=` so a
 * link, a reload and the browser's back button all land on the same tab. An
 * empty or unknown name is the first tab. The tab narrows the table's base
 * query by wrapping whatever `modifyQueryUsing()` the table already had, so a
 * resource's own scope stays in force inside every tab.
 *
 * @phpstan-require-extends ListPage
 */
trait InteractsWithListTabs
{
    /** The active tab's name; empty means the first. */
    public string $activeTab = '';

    /**
     * The table's own base modification, as it was before the tab wrapped it —
     * what a tab's count is taken over. Per request, never in the snapshot.
     */
    private ?Closure $listTabsBaseScope = null;

    /**
     * Declare the tabs. None, and the list draws no strip.
     *
     * @return array<int, ListTab>
     */
    protected function tabs(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    protected function queryStringInteractsWithListTabs(): array
    {
        return ['activeTab' => ['as' => 'tab', 'except' => '']];
    }

    /**
     * The declared tabs, keyed by name.
     *
     * @return array<string, ListTab>
     */
    public function getListTabs(): array
    {
        $tabs = [];

        foreach ($this->tabs() as $tab) {
            if ($tab instanceof ListTab) {
                $tabs[$tab->getName()] = $tab;
            }
        }

        return $tabs;
    }

    /** The tab in force: the named one, or the first when the name is empty or unknown. */
    public function getActiveListTab(): ?ListTab
    {
        $tabs = $this->getListTabs();

        return $tabs[$this->activeTab] ?? ($tabs === [] ? null : reset($tabs));
    }

    /** Switching tab starts the list again from its first page. */
    public function updatedActiveTab(): void
    {
        $this->resetPage();
        $this->invalidateTable();
    }

    /**
     * The table, narrowed by the active tab on top of its own base query.
     */
    protected function applyActiveListTab(Table $table): Table
    {
        $base = $this->listTabsBaseScope = $table->getModifyQueryCallback();
        $tab = $this->getActiveListTab();

        if ($tab === null || ! $tab->hasQuery()) {
            return $table;
        }

        return $table->modifyQueryUsing(fn (Builder $query) => $tab->apply($this->applyBaseQuery($base, $query)));
    }

    /**
     * What the strip draws — resolved here, so the view echoes and decides nothing.
     *
     * The counts are asked of the table's base query (the resource's scope, not
     * the search or the filters), once per tab that shows one, per render.
     *
     * @return array<int, array{name: string, label: string, icon: string|null, count: int|null, badgeClasses: string, active: bool}>
     */
    protected function listTabsForView(): array
    {
        $tabs = $this->getListTabs();

        if ($tabs === []) {
            return [];
        }

        $active = $this->getActiveListTab()?->getName();
        $base = null;

        $view = [];

        foreach ($tabs as $name => $tab) {
            $count = $tab->getBadgeCount();

            if ($count === null && $tab->showsCount()) {
                $base ??= $this->listTabsBaseQuery();
                $count = $base === null ? null : $tab->apply(clone $base)->count();
            }

            $view[] = [
                'name' => (string) $name,
                'label' => (string) $tab->getLabel(),
                'icon' => $tab->getIcon(),
                'count' => $count,
                'badgeClasses' => Palette::getBadgeColorClasses($tab->getBadgeColor()),
                'active' => $name === $active,
            ];
        }

        return $view;
    }

    /**
     * The query a tab count starts from: the table's model with the table's own
     * modification applied, but not the active tab's. Null for a table built
     * from something other than a model, which leaves nothing to count on.
     *
     * @return Builder<Model>|null
     */
    private function listTabsBaseQuery(): ?Builder
    {
        $model = $this->getTable()->getModelClass();

        return $model === null ? null : $this->applyBaseQuery($this->listTabsBaseScope, $model::query());
    }

    private function applyBaseQuery(?Closure $callback, Builder $query): Builder
    {
        return $callback === null ? $query : ($callback($query) ?? $query);
    }
}
