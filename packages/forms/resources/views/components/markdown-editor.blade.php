@php
    use NyonCode\WireForms\Components\MarkdownEditor;
    assert($field instanceof MarkdownEditor);
    // @entangle takes no wire:model modifiers — see CanBeLive::getEntangleModifier().
    $entangleModifier = $field->getEntangleModifier();
    $fieldId = $field->getId();

    // Shared editor vocabulary (resources/lang/*/fields.php) — the same keys
    // TiptapEditor and RichEditor title their buttons from, so the three
    // editors read alike and cs comes from the package, not the app.
    $t = static fn (string $key, array $replace = []): string
        => (string) trans("wire-forms::fields.editor.{$key}", $replace);
@endphp

@include('wire-forms::partials.field-assets')

@include('wire-forms::partials.field-wrapper-start')

<div
    {{-- Body registered as `wireMarkdownEditor`; only per-instance config here.
         The markdown renderer moved into the bundle with it, which is what
         retired the entity-encoding this attribute used to demand. --}}
    x-data="wireMarkdownEditor({
        state: @entangle($field->getWireModelAttribute()){{ $entangleModifier ? '.' . $entangleModifier : '' }},
        livePreview: @js($field->isLivePreview()),
    })"
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
            <button type="button" @click="insertAround('**', '**')" data-testid="form-editor-{{ $field->getStatePath() }}-bold" title="{{ $t('bold') }}"
                class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors font-bold text-sm w-7 h-7 flex items-center justify-center">B</button>
            <button type="button" @click="insertAround('*', '*')" data-testid="form-editor-{{ $field->getStatePath() }}-italic" title="{{ $t('italic') }}"
                class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors italic text-sm w-7 h-7 flex items-center justify-center">I</button>
            <button type="button" @click="insertAround('~~', '~~')" title="{{ $t('strike') }}"
                class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors line-through text-sm w-7 h-7 flex items-center justify-center">S</button>
            <button type="button" @click="insertAround('\`', '\`')" title="{{ $t('code') }}"
                class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors font-mono text-sm w-7 h-7 flex items-center justify-center">&lt;/&gt;</button>
            <div class="w-px h-5 bg-gray-300 dark:bg-gray-500 mx-1"></div>
            <button type="button" @click="insertLine('## ')" data-testid="form-editor-{{ $field->getStatePath() }}-heading" title="{{ $t('heading', ['level' => 2]) }}"
                class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors text-xs font-bold w-7 h-7 flex items-center justify-center">H</button>
            <button type="button" @click="insertLine('- ')" title="{{ $t('bullet_list') }}"
                class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors text-sm w-7 h-7 flex items-center justify-center">
                {!! icon('list-bullet', 'w-4 h-4', 'w-4 h-4') !!}
            </button>
            <button type="button" @click="insertLine('> ')" title="{{ $t('blockquote') }}"
                class="p-1.5 rounded text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors text-sm w-7 h-7 flex items-center justify-center">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M4.583 17.321C3.553 16.227 3 15 3 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5 3.871 3.871 0 01-2.748-1.179zm10 0C13.553 16.227 13 15 13 13.011c0-3.5 2.457-6.637 6.03-8.188l.893 1.378c-3.335 1.804-3.987 4.145-4.247 5.621.537-.278 1.24-.375 1.929-.311 1.804.167 3.226 1.648 3.226 3.489a3.5 3.5 0 01-3.5 3.5 3.871 3.871 0 01-2.748-1.179z"/></svg>
            </button>
        @endunless

        @if($field->hasPreview() && !$field->isLivePreview())
            <div class="ml-auto flex rounded-md border border-gray-200 dark:border-gray-600 overflow-hidden text-xs">
                <button type="button" @click="tab = 'write'" data-testid="form-editor-{{ $field->getStatePath() }}-write"
                    :class="tab === 'write' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-medium' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700'"
                    class="px-2.5 py-1 transition-colors">{{ $t('write') }}</button>
                <button type="button" @click="tab = 'preview'" data-testid="form-editor-{{ $field->getStatePath() }}-preview"
                    :class="tab === 'preview' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-medium' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700'"
                    class="px-2.5 py-1 border-l border-gray-200 dark:border-gray-600 transition-colors">{{ $t('preview') }}</button>
            </div>
        @endif
    </div>

    @if($field->isLivePreview())
        {{-- Side-by-side --}}
        <div class="grid grid-cols-2 divide-x divide-gray-200 dark:divide-gray-700">
            <textarea
                data-testid="form-editor-{{ $field->getStatePath() }}"
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
                data-testid="form-editor-{{ $field->getStatePath() }}"
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
