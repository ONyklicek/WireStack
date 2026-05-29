@php
    use NyonCode\WireForms\Components\TiptapEditor;
    use NyonCode\WireForms\WireFormsServiceProvider;
    assert($field instanceof TiptapEditor);

    $toolbar   = $field->getToolbarButtons();
    $config    = $field->getAlpineConfig();
    $fieldId   = $field->getId();
    $statePath = $field->getStatePath();

    // Match the standard field binding: deferred by default (no server round-trip
    // per keystroke), live + debounce only when the field opts in via ->live().
    $wireModifier     = $field->getWireModelModifier();
    $debounceModifier = $field->getDebounceModifier();
    $wireAttr         = 'wire:model'.($wireModifier ? ".{$wireModifier}" : '').$debounceModifier;

    // Pre-bundled, self-registering editor JS served straight from the package.
    // No npm install, build step, or manual import required on the consumer side.
    $assetVersion = @filemtime(WireFormsServiceProvider::ASSETS_PATH.'/wire-forms-tiptap.js') ?: null;
    $assetUrl     = route('wire-forms.asset', ['asset' => 'tiptap']).($assetVersion ? '?id='.$assetVersion : '');

    // Button icon SVGs + Alpine expressions, keyed by button name.
    $btns = [
        'bold'         => ['action' => 'toggleBold()',              'active' => "isActive('bold')",           'title' => __('Bold'),         'svg' => '<path d="M8 11h4.5a2.5 2.5 0 000-5H8v5zm10 4.5a4.5 4.5 0 01-4.5 4.5H6V4h6.5a4.5 4.5 0 013.256 7.606A4.5 4.5 0 0118 15.5zM8 13v5h5.5a2.5 2.5 0 000-5H8z"/>'],
        'italic'       => ['action' => 'toggleItalic()',            'active' => "isActive('italic')",         'title' => __('Italic'),       'svg' => '<path d="M15 20H7v-2h2.927l2.116-12H9V4h8v2h-2.927l-2.116 12H15z"/>'],
        'underline'    => ['action' => 'toggleUnderline()',         'active' => "isActive('underline')",      'title' => __('Underline'),    'svg' => '<path d="M8 3v9a4 4 0 108 0V3h2v9a6 6 0 01-12 0V3h2zM4 20h16v2H4v-2z"/>'],
        'strike'       => ['action' => 'toggleStrike()',            'active' => "isActive('strike')",         'title' => __('Strikethrough'),'svg' => '<path d="M17.154 14c.23.516.346 1.09.346 1.72 0 1.342-.524 2.392-1.571 3.147C14.88 19.622 13.433 20 11.586 20c-1.64 0-3.263-.381-4.87-1.144V16.6c1.52.877 3.075 1.316 4.666 1.316 2.551 0 3.83-.732 3.839-2.197a2.21 2.21 0 00-.648-1.603l-.12-.116H3v-2h18v2h-3.846zM7.556 11H4V9h2.592c-.272-.516-.408-1.09-.408-1.72 0-1.342.524-2.392 1.571-3.147C8.81 3.378 10.26 3 12.107 3c1.434 0 2.852.315 4.254.946v2.196c-1.34-.715-2.742-1.072-4.206-1.072-2.551 0-3.83.732-3.839 2.197 0 .564.184 1.03.553 1.399.165.165.345.31.541.439l.146.095z"/>'],
        'code'         => ['action' => 'toggleCode()',              'active' => "isActive('code')",           'title' => __('Inline code'),  'svg' => '<path d="M24 12l-5.657 5.657-1.414-1.414L21.172 12l-4.243-4.243 1.414-1.414L24 12zM2.828 12l4.243 4.243-1.414 1.414L0 12l5.657-5.657L7.07 7.757 2.828 12zm6.96 9H7.66l6.552-18h2.128L9.788 21z"/>'],
        'highlight'    => ['action' => 'toggleHighlight()',         'active' => "isActive('highlight')",      'title' => __('Highlight'),    'svg' => '<path d="M15.243 4.515l-6.738 6.737-.707 2.121-1.04 1.041 2.828 2.829 1.04-1.041 2.122-.707 6.737-6.738-4.242-4.242zm6.364 3.535a1 1 0 010 1.414L13.414 17.65l-2.829 2.828-1.414 1.414-1.414-1.414.707-.707-2.828-2.829-.707.707-1.414-1.414 1.414-1.414 2.828-2.829 8.193-8.192a1 1 0 011.414 0l4.244 4.242zM4 20h2v2H2v-4h2v2z"/>'],
        'h1'           => ['action' => 'setHeading(1)',             'active' => "isActive('heading', { level: 1 })", 'title' => __('H1'),  'label' => 'H1'],
        'h2'           => ['action' => 'setHeading(2)',             'active' => "isActive('heading', { level: 2 })", 'title' => __('H2'),  'label' => 'H2'],
        'h3'           => ['action' => 'setHeading(3)',             'active' => "isActive('heading', { level: 3 })", 'title' => __('H3'),  'label' => 'H3'],
        'bulletList'   => ['action' => 'toggleBulletList()',        'active' => "isActive('bulletList')",     'title' => __('Bullet list'),  'svg' => '<path d="M8 4h13v2H8V4zM4.5 6.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 7a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 6.9a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM8 11h13v2H8v-2zm0 7h13v2H8v-2z"/>'],
        'orderedList'  => ['action' => 'toggleOrderedList()',       'active' => "isActive('orderedList')",    'title' => __('Numbered list'), 'svg' => '<path d="M8 4h13v2H8V4zM5 3v3H4V4H3V3h2zm-1 8h2v1H3v-1h1v-1H3V9h3v3.5H4V12zm1 5v1H3v-1h1v-1H3v-1h3v4H4v-1h1zM8 11h13v2H8v-2zm0 7h13v2H8v-2z"/>'],
        'blockquote'   => ['action' => 'toggleBlockquote()',        'active' => "isActive('blockquote')",     'title' => __('Blockquote'),   'svg' => '<path d="M4.583 17.321C3.553 16.227 3 15 3 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5 3.871 3.871 0 01-2.748-1.179zm10 0C13.553 16.227 13 15 13 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5 3.871 3.871 0 01-2.748-1.179z"/>'],
        'codeBlock'    => ['action' => 'toggleCodeBlock()',         'active' => "isActive('codeBlock')",      'title' => __('Code block'),   'svg' => '<path d="M24 12l-5.657 5.657-1.414-1.414L21.172 12l-4.243-4.243 1.414-1.414L24 12zM2.828 12l4.243 4.243-1.414 1.414L0 12l5.657-5.657L7.07 7.757 2.828 12zm6.96 9H7.66l6.552-18h2.128L9.788 21z"/>'],
        'link'         => ['action' => 'insertLink()',              'active' => "isActive('link')",           'title' => __('Link'),         'svg' => '<path d="M18.364 15.536L16.95 14.12l1.414-1.414a5 5 0 00-7.071-7.071L9.879 7.05 8.464 5.636 9.88 4.222a7 7 0 019.9 9.9l-1.415 1.414zm-2.828 2.828l-1.415 1.414a7 7 0 01-9.9-9.9l1.415-1.414L7.05 9.88l-1.414 1.414a5 5 0 007.071 7.071l1.414-1.414 1.415 1.414zm-.708-10.607l1.414 1.415-7.07 7.07-1.415-1.414 7.071-7.07z"/>'],
        'image'        => ['action' => 'insertImage()',             'active' => 'false',                      'title' => __('Image'),        'svg' => '<path d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>'],
        'table'        => ['action' => 'insertTable()',             'active' => "isActive('table')",          'title' => __('Table'),        'svg' => '<path d="M3 3h18v18H3V3zm16 10H5v2h14v-2zm0 4H5v2h14v-2zM5 9h14V7H5v2z"/>'],
        'alignLeft'    => ['action' => "setAlign('left')",          'active' => "isActive({ textAlign: 'left' })",   'title' => __('Align left'),   'svg' => '<path d="M3 4h18v2H3V4zm0 4h12v2H3V8zm0 4h18v2H3v-2zm0 4h12v2H3v-2z"/>'],
        'alignCenter'  => ['action' => "setAlign('center')",        'active' => "isActive({ textAlign: 'center' })", 'title' => __('Align center'), 'svg' => '<path d="M3 4h18v2H3V4zm3 4h12v2H6V8zm-3 4h18v2H3v-2zm3 4h12v2H6v-2z"/>'],
        'alignRight'   => ['action' => "setAlign('right')",         'active' => "isActive({ textAlign: 'right' })",  'title' => __('Align right'),  'svg' => '<path d="M3 4h18v2H3V4zm6 4h12v2H9V8zm-6 4h18v2H3v-2zm6 4h12v2H9v-2z"/>'],
        'undo'         => ['action' => 'undo()',                    'active' => 'false',                      'title' => __('Undo'),         'svg' => '<path d="M5.828 7l2.536 2.536L6.95 10.95 2 6l4.95-4.95 1.414 1.414L5.828 5H13a8 8 0 110 16H4v-2h9a6 6 0 000-12H5.828z"/>'],
        'redo'         => ['action' => 'redo()',                    'active' => 'false',                      'title' => __('Redo'),         'svg' => '<path d="M18.172 7H11a6 6 0 000 12h9v2h-9a8 8 0 110-16h7.172l-2.536-2.536L17.05 1.05 22 6l-4.95 4.95-1.414-1.414L18.172 7z"/>'],
    ];

    $hasToolbar = count($toolbar) > 0;
@endphp

@include('wire-forms::partials.field-wrapper-start')

@once
<style>
    /* Make the whole editor box (not just the first line) clickable and typeable:
       the contenteditable fills the configured min-height and carries the padding. */
    .tiptap-content { cursor: text; }
    .tiptap-content .ProseMirror {
        outline: none;
        min-height: var(--tt-min-height, 240px);
        padding: .75rem 1rem;
    }
    .tiptap-content .ProseMirror p.is-editor-empty:first-child::before {
        content: attr(data-placeholder); float: left;
        color: #9ca3af; pointer-events: none; height: 0;
    }
    .tiptap-content .ProseMirror h1 { font-size: 1.5rem; font-weight: 700; margin: 1rem 0 .5rem; }
    .tiptap-content .ProseMirror h2 { font-size: 1.25rem; font-weight: 700; margin: 1rem 0 .5rem; }
    .tiptap-content .ProseMirror h3 { font-size: 1.1rem; font-weight: 600; margin: .75rem 0 .375rem; }
    .tiptap-content .ProseMirror ul { list-style: disc; padding-left: 1.5rem; margin: .5rem 0; }
    .tiptap-content .ProseMirror ol { list-style: decimal; padding-left: 1.5rem; margin: .5rem 0; }
    .tiptap-content .ProseMirror blockquote { border-left: 3px solid #d1d5db; padding-left: .75rem; color: #6b7280; font-style: italic; margin: .5rem 0; }
    .tiptap-content .ProseMirror pre { background: #1e293b; color: #e2e8f0; padding: .75rem 1rem; border-radius: .375rem; font-family: monospace; font-size: .8125rem; overflow-x: auto; margin: .5rem 0; }
    .tiptap-content .ProseMirror code { background: #f1f5f9; color: #dc2626; padding: .125rem .25rem; border-radius: .25rem; font-family: monospace; font-size: .85em; }
    .tiptap-content .ProseMirror pre code { background: none; color: inherit; padding: 0; }
    .tiptap-content .ProseMirror a { color: #2563eb; text-decoration: underline; cursor: pointer; }
    .tiptap-content .ProseMirror mark { background: #fef08a; border-radius: .125rem; }
    .tiptap-content .ProseMirror img { max-width: 100%; height: auto; border-radius: .375rem; }
    .tiptap-content .ProseMirror table { border-collapse: collapse; width: 100%; margin: .5rem 0; }
    .tiptap-content .ProseMirror td, .tiptap-content .ProseMirror th { border: 1px solid #d1d5db; padding: .375rem .5rem; vertical-align: top; min-width: 1.5rem; }
    .tiptap-content .ProseMirror th { background: #f9fafb; font-weight: 600; }
    .tiptap-content .ProseMirror .selectedCell { background: #eff6ff; }
    .dark .tiptap-content .ProseMirror code { background: #374151; color: #f87171; }
    .dark .tiptap-content .ProseMirror td, .dark .tiptap-content .ProseMirror th { border-color: #4b5563; }
    .dark .tiptap-content .ProseMirror th { background: #374151; }
    .dark .tiptap-content .ProseMirror .selectedCell { background: #1e3a5f; }
</style>
@endonce

{{-- Load the pre-bundled editor JS via Livewire's @assets directive so it runs
     once and also when the field renders inside a Livewire-loaded modal (AJAX),
     where script tags injected through DOM morphing would never execute. --}}
@assets
<script src="{{ $assetUrl }}"></script>
@endassets

<div
    x-data="tiptapEditor(@js($config))"
    @class([
        'rounded-md border overflow-hidden',
        'border-gray-300 dark:border-gray-600',
        'border-red-500' => $errors->has($statePath),
    ])
>
    {{-- ─── Toolbar ─────────────────────────────────────────────── --}}
    @if($hasToolbar)
        <div @class([
            'flex flex-wrap items-center gap-0.5 px-2 py-1.5 border-b',
            'bg-gray-50 dark:bg-gray-700/50 border-gray-300 dark:border-gray-600',
            'opacity-50 pointer-events-none' => $field->isDisabled() || $field->isReadOnly(),
        ])>
            @foreach($toolbar as $btn)
                @if($btn === '|')
                    <div class="w-px h-5 bg-gray-300 dark:bg-gray-500 mx-1"></div>
                @elseif(isset($btns[$btn]))
                    @php $b = $btns[$btn]; @endphp
                    <button
                        type="button"
                        @mousedown.prevent
                        @click="{{ $b['action'] }}"
                        :class="{ 'bg-gray-200 dark:bg-gray-600 text-gray-900 dark:text-white': {{ $b['active'] }} }"
                        title="{{ $b['title'] }}"
                        class="inline-flex items-center justify-center w-7 h-7 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-100"
                    >
                        @if(isset($b['label']))
                            <span class="text-xs font-semibold leading-none">{{ $b['label'] }}</span>
                        @else
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">{!! $b['svg'] !!}</svg>
                        @endif
                    </button>
                @endif
            @endforeach
        </div>
    @endif

    {{-- ─── Editor mount point ─────────────────────────────────────── --}}
    <div
        x-ref="editorContent"
        wire:ignore
        style="--tt-min-height: {{ $field->getMinHeight() }}px"
        @class([
            'tiptap-content text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800',
            'focus-within:ring-2 focus-within:ring-inset focus-within:ring-primary-500',
            'opacity-60 cursor-not-allowed' => $field->isDisabled(),
        ])
    ></div>

    {{-- ─── Hidden input: carries wire:model to Livewire ──────────────── --}}
    <input
        type="hidden"
        id="{{ $fieldId }}"
        x-ref="hiddenInput"
        {{ $wireAttr }}="{{ $field->getWireModelAttribute() }}"
    />

    {{-- ─── Character count footer ──────────────────────────────────── --}}
    @if($field->getMaxLength())
        <div class="flex justify-end px-3 py-1 bg-gray-50 dark:bg-gray-700/30 border-t border-gray-300 dark:border-gray-600 text-xs text-gray-400 dark:text-gray-500">
            <span x-text="characterCount + ' / {{ $field->getMaxLength() }}'"></span>
        </div>
    @endif
</div>

@include('wire-forms::partials.field-wrapper-end')
