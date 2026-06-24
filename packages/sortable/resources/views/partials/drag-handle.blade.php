{{-- Canonical drag-handle markup. Owned in Blade (rendered by the
     Table::getDragHandleHtml() macro) and injected into the sortable Alpine
     component as config, so the handle SVG is never hand-built as a JS string. --}}
<div class="wire-sortable-handle">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
        <circle cx="5.5" cy="3.5" r="1.5"/>
        <circle cx="10.5" cy="3.5" r="1.5"/>
        <circle cx="5.5" cy="8" r="1.5"/>
        <circle cx="10.5" cy="8" r="1.5"/>
        <circle cx="5.5" cy="12.5" r="1.5"/>
        <circle cx="10.5" cy="12.5" r="1.5"/>
    </svg>
</div>
