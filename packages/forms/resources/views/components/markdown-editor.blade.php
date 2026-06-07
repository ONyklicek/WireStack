@php
    use NyonCode\WireForms\Components\MarkdownEditor;
    assert($field instanceof MarkdownEditor);
    $wireModifier = $field->getWireModelModifier();
    $fieldId = $field->getId();
@endphp

@include('wire-forms::partials.field-wrapper-start')

<div
    x-data="{
        content: @entangle($field->getWireModelAttribute()){{ $wireModifier ? '.' . $wireModifier : '' }},
        tab: 'write',
        livePreview: @js($field->isLivePreview()),

        renderMd(text) {
            if (!text) return '';
            let html = text
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/^### (.+)$/gm, '<h3 class=\"text-base font-semibold mt-3 mb-1\">$1</h3>')
                .replace(/^## (.+)$/gm, '<h2 class=\"text-lg font-bold mt-4 mb-1\">$1</h2>')
                .replace(/^# (.+)$/gm, '<h1 class=\"text-xl font-bold mt-4 mb-2\">$1</h1>')
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.+?)\*/g, '<em>$1</em>')
                .replace(/~~(.+?)~~/g, '<del>$1</del>')
                .replace(/`([^`\n]+)`/g, '<code class=\"bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded text-xs font-mono\">$1</code>')
                .replace(/\[(.+?)\]\((.+?)\)/g, '<a href=\"$2\" class=\"text-primary-600 hover:underline\" target=\"_blank\">$1</a>')
                .replace(/^> (.+)$/gm, '<blockquote class=\"border-l-4 border-gray-300 dark:border-gray-600 pl-3 text-gray-600 dark:text-gray-400 italic\">$1</blockquote>')
                .replace(/^- (.+)$/gm, '<li class=\"ml-4 list-disc\">$1</li>')
                .replace(/^\d+\. (.+)$/gm, '<li class=\"ml-4 list-decimal\">$1</li>')
                .replace(/\n\n/g, '</p><p class=\"mb-2\">');
            return '<p class=\"mb-2\">' + html + '</p>';
        },

        insertAround(before, after) {
            const el = this.$refs.editor;
            const start = el.selectionStart, end = el.selectionEnd;
            const selected = this.content.substring(start, end) || 'text';
            this.content = this.content.substring(0, start) + before + selected + after + this.content.substring(end);
            this.$nextTick(() => {
                el.focus();
                el.setSelectionRange(start + before.length, start + before.length + selected.length);
            });
        },

        insertLine(prefix) {
            const el = this.$refs.editor;
            const lineStart = this.content.lastIndexOf('\n', el.selectionStart - 1) + 1;
            this.content = this.content.substring(0, lineStart) + prefix + this.content.substring(lineStart);
            this.$nextTick(() => el.focus());
        }
    }"
    @class([
        'rounded-md border overflow-hidden',
        'border-gray-300 dark:border-gray-600',
        'focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500',
        'border-red-500 focus-within:border-red-500 focus-within:ring-red-500' => $errors->has($field->getStatePath()),
    ])
>
    {{-- Toolbar --}}
    <div class="flex items-center gap-0.5 px-2 py-1.5 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-600">
        @unless($field->isDisabled() || $field->isReadOnly())
            <button type="button" @click="insertAround('**', '**')" title="{{ __('Bold') }}"
                class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors font-bold text-sm w-7 h-7 flex items-center justify-center">B</button>
            <button type="button" @click="insertAround('*', '*')" title="{{ __('Italic') }}"
                class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors italic text-sm w-7 h-7 flex items-center justify-center">I</button>
            <button type="button" @click="insertAround('~~', '~~')" title="{{ __('Strikethrough') }}"
                class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors line-through text-sm w-7 h-7 flex items-center justify-center">S</button>
            <button type="button" @click="insertAround('\`', '\`')" title="{{ __('Inline code') }}"
                class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors font-mono text-sm w-7 h-7 flex items-center justify-center">&lt;/&gt;</button>
            <div class="w-px h-5 bg-gray-300 dark:bg-gray-500 mx-1"></div>
            <button type="button" @click="insertLine('## ')" title="{{ __('Heading') }}"
                class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors text-xs font-bold w-7 h-7 flex items-center justify-center">H</button>
            <button type="button" @click="insertLine('- ')" title="{{ __('List') }}"
                class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors text-sm w-7 h-7 flex items-center justify-center">
                <x-wire::icon name="list-bullet" class="w-4 h-4" />
            </button>
            <button type="button" @click="insertLine('> ')" title="{{ __('Blockquote') }}"
                class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors text-sm w-7 h-7 flex items-center justify-center">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M4.583 17.321C3.553 16.227 3 15 3 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5 3.871 3.871 0 01-2.748-1.179zm10 0C13.553 16.227 13 15 13 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5 3.871 3.871 0 01-2.748-1.179z"/></svg>
            </button>
        @endunless

        @if($field->hasPreview() && !$field->isLivePreview())
            <div class="ml-auto flex rounded-md border border-gray-200 dark:border-gray-600 overflow-hidden text-xs">
                <button type="button" @click="tab = 'write'"
                    :class="tab === 'write' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-medium' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700'"
                    class="px-2.5 py-1 transition-colors">{{ __('Write') }}</button>
                <button type="button" @click="tab = 'preview'"
                    :class="tab === 'preview' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-medium' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700'"
                    class="px-2.5 py-1 border-l border-gray-200 dark:border-gray-600 transition-colors">{{ __('Preview') }}</button>
            </div>
        @endif
    </div>

    @if($field->isLivePreview())
        {{-- Side-by-side --}}
        <div class="grid grid-cols-2 divide-x divide-gray-200 dark:divide-gray-700">
            <textarea
                x-ref="editor"
                x-model="content"
                rows="10"
                style="min-height: {{ $field->getMinHeight() }}px"
                @if($field->getPlaceholder()) placeholder="{{ $field->getPlaceholder() }}" @endif
                @if($field->getMaxLength()) maxlength="{{ $field->getMaxLength() }}" @endif
                @if($field->isDisabled()) disabled @endif
                @if($field->isReadOnly()) readonly @endif
                class="block w-full border-0 ring-0 shadow-none focus:ring-0 focus:border-0 focus:outline-none px-3 py-2 text-sm font-mono bg-white dark:bg-gray-800 text-gray-900 dark:text-white resize-none placeholder-gray-400 disabled:opacity-50"
            ></textarea>
            <div
                class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200 prose prose-sm dark:prose-invert max-w-none overflow-auto bg-gray-50 dark:bg-gray-800/50"
                style="min-height: {{ $field->getMinHeight() }}px"
                x-html="renderMd(content)"
            ></div>
        </div>
    @else
        {{-- Tabbed --}}
        <div x-show="tab === 'write'">
            <textarea
                x-ref="editor"
                x-model="content"
                rows="10"
                style="min-height: {{ $field->getMinHeight() }}px"
                @if($field->getPlaceholder()) placeholder="{{ $field->getPlaceholder() }}" @endif
                @if($field->getMaxLength()) maxlength="{{ $field->getMaxLength() }}" @endif
                @if($field->isDisabled()) disabled @endif
                @if($field->isReadOnly()) readonly @endif
                class="block w-full border-0 ring-0 shadow-none focus:ring-0 focus:border-0 focus:outline-none px-3 py-2 text-sm font-mono bg-white dark:bg-gray-800 text-gray-900 dark:text-white resize-y placeholder-gray-400 disabled:opacity-50"
            ></textarea>
        </div>

        @if($field->hasPreview())
            <div
                x-show="tab === 'preview'"
                class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200 prose prose-sm dark:prose-invert max-w-none bg-white dark:bg-gray-800"
                style="min-height: {{ $field->getMinHeight() }}px"
                x-html="renderMd(content)"
            ></div>
        @endif
    @endif

    @if($field->getMaxLength())
        <div class="flex justify-end px-3 py-1 bg-gray-50 dark:bg-gray-700/30 border-t border-gray-200 dark:border-gray-700">
            <span class="text-xs text-gray-400 dark:text-gray-500" x-text="(content || '').length + ' / {{ $field->getMaxLength() }}'"></span>
        </div>
    @endif
</div>

@include('wire-forms::partials.field-wrapper-end')
