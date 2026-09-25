{{-- A row of widgets above or below a page's content, drawn through the grid a
     dashboard uses. Drawn, not hosted — see InteractsWithPageWidgets — so the
     grid gets no layout, no tray and no filters, which is exactly the grid's
     own hostless path. Absent when the page declares none.

     Variables: $pageWidgets — ['widgets' => Widget[], 'columns' => int]. --}}
@if(($pageWidgets['widgets'] ?? []) !== [])
    <div data-testid="page-widgets" @wireEl('page-widgets')>
        @include('wire-core::widgets.widget-grid', [
            'widgets' => $pageWidgets['widgets'],
            'columns' => $pageWidgets['columns'],
        ])
    </div>
@endif
