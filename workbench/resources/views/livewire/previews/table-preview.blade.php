<div
    data-preview-root
    class="mx-auto flex w-[1280px] items-stretch rounded-[36px] border border-slate-200 bg-[linear-gradient(180deg,_#f8fbff_0%,_#eef4fb_100%)] p-5 shadow-[0_30px_80px_rgba(148,163,184,0.24)]"
>
    <div data-preview-focus class="w-full overflow-hidden rounded-[28px] border border-slate-200 bg-white p-4">
        <div class="{{ $variant === 'selection' ? 'origin-top-left scale-[1]' : '' }}">
            {{ $this->table }}
        </div>
    </div>
</div>
