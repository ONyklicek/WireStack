<div
    data-preview-root
    class="mx-auto flex h-[800px] w-full max-w-[1280px] items-stretch rounded-[36px] border border-slate-200 bg-[linear-gradient(180deg,_#f8fbff_0%,_#eef4fb_100%)] p-5 shadow-[0_30px_80px_rgba(148,163,184,0.24)]"
>
    <div data-preview-focus class="min-w-0 flex-1 rounded-[28px] border border-slate-200 bg-white p-6">
        <form class="flex h-full flex-col gap-5 overflow-hidden">
            <div class="flex-1 overflow-hidden">
                {{ $this->form }}
            </div>

            <div class="flex items-center justify-end border-t border-slate-200 pt-4">
                <button
                    type="button"
                    class="inline-flex items-center rounded-xl bg-primary-500 px-4 py-2 text-sm font-medium text-white shadow-lg shadow-primary-500/25"
                >
                    {{ $variant === 'repeater' ? 'Save contacts' : 'Save profile' }}
                </button>
            </div>
        </form>
    </div>
</div>
