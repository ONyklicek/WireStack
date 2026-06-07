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
                <x-wire::icon name="bold" class="w-4 h-4" />
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
                <x-wire::icon name="italic" class="w-4 h-4" />
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
                <x-wire::icon name="underline" class="w-4 h-4" />
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
                <x-wire::icon name="strikethrough" class="w-4 h-4" />
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
                <x-wire::icon name="list-bullet" class="w-4 h-4" />
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
                <x-wire::icon name="numbered-list" class="w-4 h-4" />
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
                <x-wire::icon name="code-bracket" class="w-4 h-4" />
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
                <x-wire::icon name="link" class="w-4 h-4" />
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
                <x-wire::icon name="arrow-uturn-left" class="w-4 h-4" />
            </button>
        @endif

        @if(in_array('redo', $toolbarButtons))
            <button
                    type="button"
                    @click="exec('redo')"
                    class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-150"
                    title="{{ __('Redo') }}"
            >
                <x-wire::icon name="arrow-uturn-right" class="w-4 h-4" />
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
