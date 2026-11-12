{{-- "Only this value" / "Exclude this value" on a right-clicked cell
     (Table::cellFilterMenu()). One panel per table, not per row: it learns the
     record from the row's data-row-key and the column from the header cell
     above the clicked one, which carries data-cell-filter when its column has a
     header filter ("exclude" when that filter can also drop a value).

     The value itself is never read from the page — the server looks it up on
     the record (WithTable::filterByCellValue()), because the cell shows it
     formatted and the filter compares what is stored.

     A row with its own context menu keeps it: the record menu is the richer
     one, and two menus on one click would be one too many.

     Expression-only Alpine with single quotes throughout — a double quote
     inside the attribute would end it. --}}
<div
    x-data="{
        open: false, x: 0, y: 0, key: null, column: null, canExclude: false,
        onContext(event) {
            const td = event.target.closest('td');
            const table = $el.parentElement.querySelector('table');
            if (! td || ! table || ! table.contains(td)) return;
            const row = td.closest('tr[data-row-key]');
            if (! row || document.querySelector('[data-record-menu=' + CSS.escape(row.dataset.rowKey) + ']')) return;
            const head = table.querySelector('thead tr');
            const th = head ? head.children[td.cellIndex] : null;
            if (! th || ! th.dataset.cellFilter) return;
            event.preventDefault();
            this.key = row.dataset.rowKey;
            this.column = th.dataset.column;
            this.canExclude = th.dataset.cellFilter === 'exclude';
            this.x = Math.min(event.clientX, window.innerWidth - 232);
            this.y = Math.min(event.clientY, window.innerHeight - 96);
            this.open = true;
            $nextTick(() => $refs.first?.focus());
        },
        pick(exclude) {
            this.open = false;
            $wire.filterByCellValue(this.key, this.column, exclude);
        },
    }"
    @contextmenu.window="onContext($event)"
    @keydown.escape.window="open && ($event.preventDefault(), open = false)"
    @scroll.window="open = false"
    data-testid="cell-filter-menu" @wireEl('cell-filter-menu')
>
    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            @click.outside="open = false"
            role="menu"
            :style="'left: ' + x + 'px; top: ' + y + 'px'"
            class="fixed z-50 w-56 rounded-lg bg-white py-1 shadow-lg ring-1 ring-black/5 focus:outline-none dark:bg-gray-800 dark:ring-white/10"
            style="display: none;"
            data-testid="cell-filter-panel"
        >
            <button
                type="button"
                role="menuitem"
                x-ref="first"
                @click="pick(false)"
                data-testid="cell-filter-only"
                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:bg-gray-700"
            >
                {!! icon('outline:funnel', 'h-4 w-4', 'text-gray-400') !!}
                <span>{{ __('wire-table::messages.filter_only_value') }}</span>
            </button>
            <button
                type="button"
                role="menuitem"
                x-show="canExclude"
                @click="pick(true)"
                data-testid="cell-filter-exclude"
                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:bg-gray-700"
            >
                {!! icon('outline:no-symbol', 'h-4 w-4', 'text-gray-400') !!}
                <span>{{ __('wire-table::messages.filter_exclude_value') }}</span>
            </button>
        </div>
    </template>
</div>
