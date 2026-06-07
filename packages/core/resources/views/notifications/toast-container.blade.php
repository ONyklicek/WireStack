{{-- Toast Notification Container --}}
{{-- Listens for Livewire events and renders toast notifications with auto-dismiss. --}}
<div
    x-data="{
        toasts: [],
        add(toast) {
            const id = Date.now() + Math.random();
            this.toasts.push({ ...toast, id });
            setTimeout(() => this.remove(id), toast.duration || {{ $duration }});
        },
        remove(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        },
        init() {
            const eventName = @js($eventName);
            const dispatch = (payload) => window.dispatchEvent(
                new CustomEvent(eventName, { detail: payload })
            );
            const normalize = (type, message, options) => {
                const data = (message !== null && typeof message === 'object')
                    ? { ...message }
                    : { message, ...(options || {}) };
                if (type && !data.type) data.type = type;
                if (!data.type) data.type = 'info';
                return data;
            };
            if (!window.wireToast) {
                const toast = (message, options) => dispatch(normalize(null, message, options));
                ['success', 'error', 'warning', 'info'].forEach((type) => {
                    toast[type] = (message, options) => dispatch(normalize(type, message, options));
                });
                window.wireToast = toast;
            }
            if (window.Alpine && !window.Alpine.__wireToastMagic) {
                window.Alpine.__wireToastMagic = true;
                window.Alpine.magic('toast', () => window.wireToast);
            }
        }
    }"
    x-on:{{ $eventName }}.window="add($event.detail)"
    class="fixed z-[99] {{ $positionClasses() }} flex flex-col gap-2 pointer-events-none"
    style="max-width: 24rem;"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transform ease-out duration-300 transition"
            x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="pointer-events-auto w-full overflow-hidden rounded-xl bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black/5 dark:ring-white/10"
        >
            <div class="p-4">
                <div class="flex items-start gap-3">
                    {{-- Icon --}}
                    <div class="flex-shrink-0">
                        <template x-if="toast.type === 'success'">
                            <x-wire::icon name="outline:check-circle" size="h-5 w-5" class="text-emerald-500" />
                        </template>
                        <template x-if="toast.type === 'error'">
                            <x-wire::icon name="outline:exclamation-circle" size="h-5 w-5" class="text-red-500" />
                        </template>
                        <template x-if="toast.type === 'warning'">
                            <x-wire::icon name="outline:exclamation-triangle" size="h-5 w-5" class="text-amber-500" />
                        </template>
                        <template x-if="toast.type === 'info'">
                            <x-wire::icon name="outline:information-circle" size="h-5 w-5" class="text-blue-500" />
                        </template>
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <p x-show="toast.title" x-text="toast.title" class="text-sm font-semibold text-gray-900 dark:text-white"></p>
                        <p x-text="toast.message" class="text-sm text-gray-600 dark:text-gray-300" :class="{ 'mt-1': toast.title }"></p>
                    </div>

                    {{-- Close button --}}
                    <button
                        @click="remove(toast.id)"
                        class="flex-shrink-0 rounded-lg p-1 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none"
                    >
                        <x-wire::icon name="outline:x-mark" size="h-4 w-4" />
                        <span class="sr-only">{{ __('Close') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
