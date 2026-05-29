<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Workbench\App\Livewire\Previews\CorePreview;
use Workbench\App\Livewire\Previews\FieldPreview;
use Workbench\App\Livewire\Previews\FormPreview;
use Workbench\App\Livewire\Previews\SortablePreview;
use Workbench\App\Livewire\Previews\TablePreview;

Route::get('/', function (): RedirectResponse {
    return redirect('/previews');
});

Route::get('/previews', function () {
    $screens = [
        [
            'slug' => 'forms-overview',
            'title' => 'Wire Forms',
            'label' => 'Forms overview',
            'copy' => 'Full schema-driven form layout with sections, grid, toggle, and repeater.',
            'component' => FormPreview::class,
            'variant' => 'overview',
        ],
        [
            'slug' => 'forms-repeater',
            'title' => 'Wire Forms',
            'label' => 'Forms repeater',
            'copy' => 'Focused repeater surface with nested items, ordering controls, and add action.',
            'component' => FormPreview::class,
            'variant' => 'repeater',
        ],
        [
            'slug' => 'table-overview',
            'title' => 'Wire Table',
            'label' => 'Table overview',
            'copy' => 'Search, filters, row actions, pagination, and responsive table chrome.',
            'component' => TablePreview::class,
            'variant' => 'overview',
        ],
        [
            'slug' => 'table-selection',
            'title' => 'Wire Table',
            'label' => 'Table selection',
            'copy' => 'Bulk selection state with active filter and selected-record toolbar.',
            'component' => TablePreview::class,
            'variant' => 'selection',
        ],
        [
            'slug' => 'sortable-overview',
            'title' => 'Wire Sortable',
            'label' => 'Sortable overview',
            'copy' => 'Full reorderable task table rendered through the sortable runtime.',
            'component' => SortablePreview::class,
            'variant' => 'overview',
        ],
        [
            'slug' => 'sortable-detail',
            'title' => 'Wire Sortable',
            'label' => 'Sortable detail',
            'copy' => 'Closer reorder surface focused on rows, handles, and ordering affordances.',
            'component' => SortablePreview::class,
            'variant' => 'detail',
        ],
        [
            'slug' => 'core-overview',
            'title' => 'Wire Core',
            'label' => 'Core overview',
            'copy' => 'Stats, actions, and shared runtime building blocks without docs chrome.',
            'component' => CorePreview::class,
            'variant' => 'overview',
        ],
        [
            'slug' => 'core-modal',
            'title' => 'Wire Core',
            'label' => 'Core modal',
            'copy' => 'Real modal surface captured from the shared core component set.',
            'component' => CorePreview::class,
            'variant' => 'modal',
        ],
    ];

    return view('previews.index', ['screens' => $screens]);
});

foreach ([
    'forms-overview' => ['title' => 'Wire Forms', 'subtitle' => 'Schema-driven form layout preview.', 'component' => FormPreview::class, 'variant' => 'overview'],
    'forms-repeater' => ['title' => 'Wire Forms Repeater', 'subtitle' => 'Focused nested repeater preview.', 'component' => FormPreview::class, 'variant' => 'repeater'],
    'table-overview' => ['title' => 'Wire Table', 'subtitle' => 'Live table preview with search, filters, and actions.', 'component' => TablePreview::class, 'variant' => 'overview'],
    'table-selection' => ['title' => 'Wire Table Selection', 'subtitle' => 'Selected-record state with bulk toolbar and active filters.', 'component' => TablePreview::class, 'variant' => 'selection'],
    'sortable-overview' => ['title' => 'Wire Sortable', 'subtitle' => 'Full reorderable task table preview.', 'component' => SortablePreview::class, 'variant' => 'overview'],
    'sortable-detail' => ['title' => 'Wire Sortable Detail', 'subtitle' => 'Closer reorder-surface preview.', 'component' => SortablePreview::class, 'variant' => 'detail'],
    'core-overview' => ['title' => 'Wire Core', 'subtitle' => 'Stats, actions, and shared primitives.', 'component' => CorePreview::class, 'variant' => 'overview'],
    'core-modal' => ['title' => 'Wire Core Modal', 'subtitle' => 'Real modal surface from the core runtime.', 'component' => CorePreview::class, 'variant' => 'modal'],
] as $slug => $screen) {
    Route::get('/previews/'.$slug, fn () => view('previews.capture', $screen));
}

$fieldPreviews = [
    'text-input' => 'Text Input',
    'textarea' => 'Textarea',
    'select' => 'Select',
    'checkbox' => 'Checkbox',
    'checkbox-list' => 'Checkbox List',
    'radio' => 'Radio',
    'toggle' => 'Toggle',
    'color-picker' => 'Color Picker',
    'slider' => 'Slider',
    'tags' => 'Tags',
    'rating' => 'Rating',
    'otp-input' => 'OTP Input',
    'key-value' => 'Key-Value',
    'date-time-picker' => 'Date-Time Picker',
    'file-upload' => 'File Upload',
];

foreach ($fieldPreviews as $field => $label) {
    Route::get('/previews/field-'.$field, fn () => view('previews.capture', [
        'title' => $label.' field',
        'subtitle' => 'Single '.$label.' field rendered through the Wire Forms runtime.',
        'component' => FieldPreview::class,
        'variant' => $field,
    ]));
}

Route::redirect('/previews/forms', '/previews/forms-overview');
Route::redirect('/previews/table', '/previews/table-overview');
Route::redirect('/previews/sortable', '/previews/sortable-overview');
Route::redirect('/previews/core', '/previews/core-overview');
