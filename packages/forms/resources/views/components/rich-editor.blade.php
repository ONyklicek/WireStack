@php use NyonCode\WireForms\Components\RichEditor;
    assert($field instanceof RichEditor);

    $wireModifier = $field->getWireModelModifier();
    $wireAttr = 'wire:model' . ($wireModifier ? ".{$wireModifier}" : '');
    $toolbarButtons = $field->getToolbarButtons();
    $fieldId = $field->getId();
@endphp

@include('wire-forms::partials.field-wrapper-start')

<div
        x-data="{
        content: '',
        toolbar: @js($toolbarButtons),
        activeFormats: {},

        init() {
            const initial = $wire.get('{{ $field->getWireModelAttribute() }}');
            if (initial) {
                this.content = initial;
                this.$nextTick(() => { this.$refs.editor.innerHTML = this.content; });
            }

            $wire.$watch('{{ $field->getWireModelAttribute() }}', (val) => {
                if (document.activeElement !== this.$refs.editor) {
                    this.content = val || '';
                    this.$refs.editor.innerHTML = this.content;
                }
            });
        },

        onInput() {
            this.content = this.$refs.editor.innerHTML;
            this.$refs.textarea.value = this.content;
            this.$refs.textarea.dispatchEvent(new Event('input'));
            this.updateActiveFormats();
        },

        exec(command, value = null) {
            this.$refs.editor.focus();
            document.execCommand(command, false, value);
            this.onInput();
        },

        updateActiveFormats() {
            this.activeFormats = {
                bold: document.queryCommandState('bold'),
                italic: document.queryCommandState('italic'),
                underline: document.queryCommandState('underline'),
                strikeThrough: document.queryCommandState('strikeThrough'),
                insertOrderedList: document.queryCommandState('insertOrderedList'),
                insertUnorderedList: document.queryCommandState('insertUnorderedList'),
            };
        },

        insertLink() {
            const url = prompt('{{ __('Enter URL') }}');
            if (url) {
                this.exec('createLink', url);
            }
        },

        hasButton(name) {
            return this.toolbar.includes(name);
        }
    }"
        @class([
            'rounded-md border overflow-hidden',
            'border-gray-300 dark:border-gray-600',
            'focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500',
            'hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150',
            'border-red-500 focus-within:border-red-500 focus-within:ring-red-500' => $errors->has($field->getStatePath()),
        ])
>
    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-0.5 px-2 py-1.5 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-600">
        @if(in_array('bold', $toolbarButtons))
            <button
                    type="button"
                    @click="exec('bold')"
                    :class="{ 'bg-gray-200 dark:bg-gray-600': activeFormats.bold }"
                    class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-150"
                    title="{{ __('Bold') }}"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M8 11h4.5a2.5 2.5 0 000-5H8v5zm10 4.5a4.5 4.5 0 01-4.5 4.5H6V4h6.5a4.5 4.5 0 013.256 7.606A4.5 4.5 0 0118 15.5zM8 13v5h5.5a2.5 2.5 0 000-5H8z"/>
                </svg>
            </button>
        @endif

        @if(in_array('italic', $toolbarButtons))
            <button
                    type="button"
                    @click="exec('italic')"
                    :class="{ 'bg-gray-200 dark:bg-gray-600': activeFormats.italic }"
                    class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-150"
                    title="{{ __('Italic') }}"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M15 20H7v-2h2.927l2.116-12H9V4h8v2h-2.927l-2.116 12H15z"/>
                </svg>
            </button>
        @endif

        @if(in_array('underline', $toolbarButtons))
            <button
                    type="button"
                    @click="exec('underline')"
                    :class="{ 'bg-gray-200 dark:bg-gray-600': activeFormats.underline }"
                    class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-150"
                    title="{{ __('Underline') }}"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M8 3v9a4 4 0 108 0V3h2v9a6 6 0 01-12 0V3h2zM4 20h16v2H4v-2z"/>
                </svg>
            </button>
        @endif

        @if(in_array('strike', $toolbarButtons))
            <button
                    type="button"
                    @click="exec('strikeThrough')"
                    :class="{ 'bg-gray-200 dark:bg-gray-600': activeFormats.strikeThrough }"
                    class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-150"
                    title="{{ __('Strikethrough') }}"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.154 14c.23.516.346 1.09.346 1.72 0 1.342-.524 2.392-1.571 3.147C14.88 19.622 13.433 20 11.586 20c-1.64 0-3.263-.381-4.87-1.144V16.6c1.52.877 3.075 1.316 4.666 1.316 2.551 0 3.83-.732 3.839-2.197a2.21 2.21 0 00-.648-1.603l-.12-.116H3v-2h18v2h-3.846zM7.556 11H4V9h2.592c-.272-.516-.408-1.09-.408-1.72 0-1.342.524-2.392 1.571-3.147C8.81 3.378 10.26 3 12.107 3c1.434 0 2.852.315 4.254.946v2.196c-1.34-.715-2.742-1.072-4.206-1.072-2.551 0-3.83.732-3.839 2.197 0 .564.184 1.03.553 1.399.165.165.345.31.541.439l.146.095z"/>
                </svg>
            </button>
        @endif

        @if(in_array('bold', $toolbarButtons) || in_array('italic', $toolbarButtons) || in_array('underline', $toolbarButtons) || in_array('strike', $toolbarButtons))
            @if(in_array('h2', $toolbarButtons) || in_array('h3', $toolbarButtons) || in_array('link', $toolbarButtons) || in_array('bulletList', $toolbarButtons) || in_array('orderedList', $toolbarButtons) || in_array('blockquote', $toolbarButtons) || in_array('codeBlock', $toolbarButtons))
                <div class="w-px h-5 bg-gray-300 dark:bg-gray-500 mx-1"></div>
            @endif
        @endif

        @if(in_array('h2', $toolbarButtons))
            <button
                    type="button"
                    @click="exec('formatBlock', 'h2')"
                    class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-150"
                    title="{{ __('Heading 2') }}"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M13 20h-2v-7H4v7H2V4h2v7h7V4h2v16zm8-12v12h-2v-9.796l-2 .536V8.67L19.5 8H21z"/>
                </svg>
            </button>
        @endif

        @if(in_array('h3', $toolbarButtons))
            <button
                    type="button"
                    @click="exec('formatBlock', 'h3')"
                    class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-150"
                    title="{{ __('Heading 3') }}"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M13 20h-2v-7H4v7H2V4h2v7h7V4h2v16zm5.46-7.24c.61.17 1.07.51 1.39.99.32.49.49 1.06.49 1.71 0 1-.36 1.79-1.07 2.36-.72.58-1.71.87-2.99.87-1.2 0-2.17-.23-2.92-.68l.44-1.64c.68.41 1.42.62 2.22.62.63 0 1.12-.14 1.46-.43.34-.29.51-.69.51-1.21 0-.54-.18-.96-.53-1.26-.36-.3-.87-.45-1.54-.45h-.74v-1.5h.74c.6 0 1.07-.14 1.41-.42.34-.28.51-.67.51-1.16 0-.45-.15-.8-.46-1.06-.31-.25-.72-.38-1.24-.38-.72 0-1.38.21-2 .64l-.44-1.6c.74-.5 1.68-.75 2.82-.75 1.06 0 1.89.27 2.48.81.6.55.9 1.25.9 2.12 0 .93-.38 1.64-1.13 2.15z"/>
                </svg>
            </button>
        @endif

        @if(in_array('bulletList', $toolbarButtons))
            <button
                    type="button"
                    @click="exec('insertUnorderedList')"
                    :class="{ 'bg-gray-200 dark:bg-gray-600': activeFormats.insertUnorderedList }"
                    class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-150"
                    title="{{ __('Bullet list') }}"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M8 4h13v2H8V4zM4.5 6.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 7a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 6.9a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM8 11h13v2H8v-2zm0 7h13v2H8v-2z"/>
                </svg>
            </button>
        @endif

        @if(in_array('orderedList', $toolbarButtons))
            <button
                    type="button"
                    @click="exec('insertOrderedList')"
                    :class="{ 'bg-gray-200 dark:bg-gray-600': activeFormats.insertOrderedList }"
                    class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-150"
                    title="{{ __('Numbered list') }}"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M8 4h13v2H8V4zM5 3v3H4V4H3V3h2zm-1 8h2v1H3v-1h1v-1H3V9h3v3.5H4V12zm1 5v1H3v-1h1v-1H3v-1h3v4H4v-1h1zM8 11h13v2H8v-2zm0 7h13v2H8v-2z"/>
                </svg>
            </button>
        @endif

        @if(in_array('blockquote', $toolbarButtons))
            <button
                    type="button"
                    @click="exec('formatBlock', 'blockquote')"
                    class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-150"
                    title="{{ __('Blockquote') }}"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M4.583 17.321C3.553 16.227 3 15 3 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5 3.871 3.871 0 01-2.748-1.179zm10 0C13.553 16.227 13 15 13 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5 3.871 3.871 0 01-2.748-1.179z"/>
                </svg>
            </button>
        @endif

        @if(in_array('codeBlock', $toolbarButtons))
            <button
                    type="button"
                    @click="exec('formatBlock', 'pre')"
                    class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-150"
                    title="{{ __('Code block') }}"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M24 12l-5.657 5.657-1.414-1.414L21.172 12l-4.243-4.243 1.414-1.414L24 12zM2.828 12l4.243 4.243-1.414 1.414L0 12l5.657-5.657L7.07 7.757 2.828 12zm6.96 9H7.66l6.552-18h2.128L9.788 21z"/>
                </svg>
            </button>
        @endif

        @if(in_array('link', $toolbarButtons))
            @if(in_array('h2', $toolbarButtons) || in_array('h3', $toolbarButtons) || in_array('bulletList', $toolbarButtons) || in_array('orderedList', $toolbarButtons) || in_array('blockquote', $toolbarButtons) || in_array('codeBlock', $toolbarButtons))
                <div class="w-px h-5 bg-gray-300 dark:bg-gray-500 mx-1"></div>
            @endif

            <button
                    type="button"
                    @click="insertLink()"
                    class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-150"
                    title="{{ __('Insert link') }}"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M18.364 15.536L16.95 14.12l1.414-1.414a5 5 0 00-7.071-7.071L9.879 7.05 8.464 5.636 9.88 4.222a7 7 0 019.9 9.9l-1.415 1.414zm-2.828 2.828l-1.415 1.414a7 7 0 01-9.9-9.9l1.415-1.414L7.05 9.88l-1.414 1.414a5 5 0 007.071 7.071l1.414-1.414 1.415 1.414zm-.708-10.607l1.414 1.415-7.07 7.07-1.415-1.414 7.071-7.07z"/>
                </svg>
            </button>
        @endif

        @if(in_array('undo', $toolbarButtons) || in_array('redo', $toolbarButtons))
            <div class="w-px h-5 bg-gray-300 dark:bg-gray-500 mx-1"></div>
        @endif

        @if(in_array('undo', $toolbarButtons))
            <button
                    type="button"
                    @click="exec('undo')"
                    class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-150"
                    title="{{ __('Undo') }}"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M5.828 7l2.536 2.536L6.95 10.95 2 6l4.95-4.95 1.414 1.414L5.828 5H13a8 8 0 110 16H4v-2h9a6 6 0 000-12H5.828z"/>
                </svg>
            </button>
        @endif

        @if(in_array('redo', $toolbarButtons))
            <button
                    type="button"
                    @click="exec('redo')"
                    class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-150"
                    title="{{ __('Redo') }}"
            >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M18.172 7H11a6 6 0 000 12h9v2h-9a8 8 0 110-16h7.172l-2.536-2.536L17.05 1.05 22 6l-4.95 4.95-1.414-1.414L18.172 7z"/>
                </svg>
            </button>
        @endif
    </div>

    {{-- Editable area --}}
    <div
            wire:ignore
            x-ref="editor"
            contenteditable="{{ $field->isDisabled() || $field->isReadOnly() ? 'false' : 'true' }}"
            @input="onInput()"
            @click="updateActiveFormats()"
            @keyup="updateActiveFormats()"
            @if($field->getPlaceholder()) data-placeholder="{{ $field->getPlaceholder() }}" @endif
            @class([
                'min-h-[10rem] px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800',
                'focus:outline-none',
                'prose prose-sm dark:prose-invert max-w-none',
                '[&:empty]:before:content-[attr(data-placeholder)] [&:empty]:before:text-gray-400 [&:empty]:before:dark:text-gray-500',
                'disabled:opacity-50' => $field->isDisabled(),
            ])
    ></div>

    {{-- Hidden textarea: wire:model.live pushes value to server on each input event --}}
    <textarea
            id="{{ $fieldId }}"
            x-ref="textarea"
            wire:model.live="{{ $field->getWireModelAttribute() }}"
            class="sr-only"
            tabindex="-1"
            aria-hidden="true"
    ></textarea>
</div>

@include('wire-forms::partials.field-wrapper-end')
