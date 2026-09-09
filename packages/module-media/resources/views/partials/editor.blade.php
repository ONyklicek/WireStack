@php
    use NyonCode\WireCore\Foundation\View\Palette;
@endphp
{{-- The image editor.

     The pixels are recomputed in the browser, by `wire-forms`' image processor
     extended — not a second copy of it. The source is read through this module's
     own route so the fetch is same-origin whatever disk the file is on: a canvas
     that has drawn a cross-origin image refuses to hand back its bytes, and it
     refuses *after* the person has finished composing their crop.

     Two outcomes and two buttons, never a checkbox that changes what one button
     does — that is how somebody replaces a published photograph believing they
     exported a crop. See ADR 0035. --}}
<div
    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4"
    data-testid="media-editor" @wireEl('media-editor')
    x-data="wireMediaEditor({
        src: @js($editing->url() ?? $editing->streamUrl()),
        source: @js($editing->streamUrl() ?? $editing->url()),
        name: @js($editing->name),
        messages: {
            tooLarge: @js(__('wire-module-media::messages.editor_too_large')),
            unreadable: @js(__('wire-module-media::messages.editor_unreadable')),
            failed: @js(__('wire-module-media::messages.editor_failed')),
        },
    })"
    x-on:keydown.escape.window="$wire.closeEditor()"
    x-on:pointermove.window="onDrag($event)"
    x-on:pointerup.window="endDrag()"
>
    <div class="flex max-h-full w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
            <p class="truncate text-sm font-medium">{{ __('wire-module-media::messages.editor_title', ['name' => $editing->name]) }}</p>

            <button
                type="button"
                wire:click="closeEditor"
                data-testid="media-editor-close" @wireEl('media-editor-close')
                class="rounded-sm p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
            >
                <span class="sr-only">{{ __('wire-module-media::messages.close') }}</span>
                {!! icon('outline:x-mark', 'h-4 w-4') !!}
            </button>
        </div>

        <div class="flex min-h-0 flex-1 flex-col md:flex-row">
            {{-- The tools ─────────────────────────────────────────────── --}}
            <div class="flex w-full shrink-0 flex-col gap-4 border-b border-gray-200 p-4 md:w-56 md:border-r md:border-b-0 dark:border-gray-800">
                <div>
                    <p class="text-[11px] font-semibold tracking-wider text-gray-400 uppercase">{{ __('wire-module-media::messages.crop') }}</p>

                    <div class="mt-1.5 flex flex-wrap gap-1">
                        <button
                            type="button"
                            x-on:click="ratio = null"
                            data-testid="media-editor-ratio" @wireEl('media-editor-ratio')
                            :class="ratio === null ? 'border-primary-400 bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-200' : 'border-gray-200 text-gray-500 dark:border-gray-700'"
                            class="rounded-full border px-2.5 py-1 text-xs"
                        >{{ __('wire-module-media::messages.ratio_free') }}</button>

                        @foreach (['1:1', '4:3', '16:9', '3:2'] as $option)
                            <button
                                type="button"
                                x-on:click="ratio = @js($option)"
                                data-testid="media-editor-ratio" @wireEl('media-editor-ratio')
                                :class="ratio === @js($option) ? 'border-primary-400 bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-200' : 'border-gray-200 text-gray-500 dark:border-gray-700'"
                                class="rounded-full border px-2.5 py-1 text-xs"
                            >{{ $option }}</button>
                        @endforeach
                    </div>
                </div>

                <div>
                    <p class="text-[11px] font-semibold tracking-wider text-gray-400 uppercase">{{ __('wire-module-media::messages.orientation') }}</p>

                    <div class="mt-1.5 flex gap-1">
                        <button type="button" x-on:click="turn(-90)" data-testid="media-editor-rotate-left" @wireEl('media-editor-rotate-left') title="{{ __('wire-module-media::messages.rotate_left') }}" class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:text-gray-800 dark:border-gray-700">
                            <span class="sr-only">{{ __('wire-module-media::messages.rotate_left') }}</span>
                            {!! icon('outline:arrow-uturn-left', 'h-4 w-4') !!}
                        </button>

                        <button type="button" x-on:click="turn(90)" data-testid="media-editor-rotate-right" @wireEl('media-editor-rotate-right') title="{{ __('wire-module-media::messages.rotate_right') }}" class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:text-gray-800 dark:border-gray-700">
                            <span class="sr-only">{{ __('wire-module-media::messages.rotate_right') }}</span>
                            {!! icon('outline:arrow-uturn-right', 'h-4 w-4') !!}
                        </button>

                        <button type="button" x-on:click="flip = ! flip" data-testid="media-editor-flip" @wireEl('media-editor-flip') title="{{ __('wire-module-media::messages.flip') }}" :class="flip ? 'border-primary-400 text-primary-700' : 'border-gray-200 text-gray-500'" class="rounded-lg border p-1.5 hover:text-gray-800 dark:border-gray-700">
                            <span class="sr-only">{{ __('wire-module-media::messages.flip') }}</span>
                            {!! icon('outline:arrows-right-left', 'h-4 w-4') !!}
                        </button>
                    </div>
                </div>

                <div>
                    <p class="text-[11px] font-semibold tracking-wider text-gray-400 uppercase">{{ __('wire-module-media::messages.output') }}</p>

                    <div class="mt-1.5 flex items-center gap-2">
                        <input
                            type="number"
                            min="16"
                            x-model="targetWidth"
                            data-testid="media-editor-width" @wireEl('media-editor-width')
                            class="w-20 rounded-lg border border-gray-200 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
                        >
                        <span class="text-xs text-gray-400">{{ __('wire-module-media::messages.output_width') }}</span>
                    </div>

                    <select
                        x-model="format"
                        data-testid="media-editor-format" @wireEl('media-editor-format')
                        class="mt-1.5 w-full rounded-lg border border-gray-200 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <option value="">{{ __('wire-module-media::messages.keep_format') }}</option>
                        <option value="image/webp">{{ __('wire-module-media::messages.to_webp') }}</option>
                    </select>
                </div>

                <div class="mt-auto">
                    <p class="text-[11px] font-semibold tracking-wider text-gray-400 uppercase">{{ __('wire-module-media::messages.result') }}</p>
                    <p class="mt-1 font-mono text-xs text-gray-600 dark:text-gray-300" data-testid="media-editor-result" @wireEl('media-editor-result') x-text="outputSize[0] + ' × ' + outputSize[1]"></p>

                    <button type="button" x-on:click="reset()" class="mt-2 text-xs text-gray-400 underline hover:text-gray-600">{{ __('wire-module-media::messages.reset') }}</button>
                </div>
            </div>

            {{-- The stage ─────────────────────────────────────────────── --}}
            <div class="flex min-h-0 min-w-0 flex-1 flex-col">
                <div class="flex flex-1 items-center justify-center overflow-hidden bg-gray-100 p-4 dark:bg-gray-950">
                    <div class="relative max-h-full" x-ref="stage">
                        <img
                            x-ref="image"
                            src="{{ $editing->url() ?? $editing->streamUrl() }}"
                            alt="{{ $editing->altText() }}"
                            data-testid="media-editor-image" @wireEl('media-editor-image')
                            class="max-h-[52vh] max-w-full select-none"
                            x-on:load="measure()"
                            :style="`transform: rotate(${rotate}deg) scaleX(${flip ? -1 : 1})`"
                            draggable="false"
                        >

                        {{-- The frame is positioned in percentages of the picture,
                             which is exactly what `processImage` takes as its crop
                             — so what is drawn and what is cut cannot disagree. --}}
                        <div
                            class="absolute cursor-move outline-2 outline-white/90"
                            data-testid="media-editor-frame" @wireEl('media-editor-frame')
                            :style="`left:${frame.x * 100}%; top:${frame.y * 100}%; width:${frame.width * 100}%; height:${frame.height * 100}%; box-shadow: 0 0 0 9999px rgba(17,24,39,.55)`"
                            x-on:pointerdown="startDrag($event)"
                        >
                            @foreach (['tl' => 'start-0 top-0', 'tr' => 'end-0 top-0', 'bl' => 'start-0 bottom-0', 'br' => 'end-0 bottom-0'] as $handle => $position)
                                <span
                                    class="absolute h-3 w-3 -translate-x-1/2 -translate-y-1/2 border-2 border-white bg-gray-900/30 {{ $position }}"
                                    data-testid="media-editor-handle" @wireEl('media-editor-handle')
                                    x-on:pointerdown.stop="startDrag($event, @js($handle))"
                                ></span>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Replacing changes every use at once, including the ones the
                     library admits it cannot see. The count comes from ADR 0034
                     and the sentence says it is a floor. --}}
                <div class="flex items-start gap-2 border-t px-4 py-2.5 {{ Palette::getAlertColorClasses('warning') }}">
                    {!! icon('outline:exclamation-triangle', 'h-4 w-4 shrink-0 '.Palette::getTextColorClasses('warning')) !!}
                    <p class="text-xs" data-testid="media-editor-warning" @wireEl('media-editor-warning')>
                        {{ trans_choice('wire-module-media::messages.replace_warning', $editingUses, ['count' => $editingUses]) }}
                    </p>
                </div>

                <p x-show="error" x-cloak class="border-t border-red-200 bg-red-50 px-4 py-2 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300" x-text="error" data-testid="media-editor-error" @wireEl('media-editor-error')></p>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-200 px-4 py-3 dark:border-gray-800">
                    <button
                        type="button"
                        x-on:click="save('replace')"
                        :disabled="busy || tooLarge"
                        data-testid="media-editor-replace" @wireEl('media-editor-replace')
                        class="rounded-lg px-3 py-1.5 text-sm disabled:opacity-50 {{ Palette::getOutlinedClasses('warning') }}"
                    >{{ __('wire-module-media::messages.replace_original') }}</button>

                    <button
                        type="button"
                        x-on:click="save('new')"
                        :disabled="busy || tooLarge"
                        data-testid="media-editor-save-new" @wireEl('media-editor-save-new')
                        class="bg-primary-600 hover:bg-primary-700 rounded-lg px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50"
                    >{{ __('wire-module-media::messages.save_as_new') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
