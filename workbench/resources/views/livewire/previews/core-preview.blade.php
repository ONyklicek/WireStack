<div
    data-preview-root
    class="mx-auto h-[800px] w-[1280px] overflow-hidden rounded-[36px] border border-slate-200 bg-[linear-gradient(180deg,_#f8fbff_0%,_#eef4fb_100%)] p-5 shadow-[0_30px_80px_rgba(148,163,184,0.24)]"
>
    <div data-preview-focus class="h-full rounded-[28px] border border-slate-200 bg-white p-6">
        <div class="space-y-6">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/70">
                {!! $stats !!}
            </div>

            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/70">
                <div class="flex flex-wrap items-center gap-3">
                    @foreach ($actions as $action)
                        {!! $action->render($record) !!}
                    @endforeach
                </div>

                <div class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-5">
                    <p class="text-sm font-medium text-slate-900">Action pipeline</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Before hooks, execution, notifications, redirects, and plugin listeners all run through the shared core runtime.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <x-wire::modal
        wire:model="showPreviewModal"
        heading="Edit automation policy"
        description="Core modal primitives power confirmations, slide-overs, and table action forms."
        width="2xl"
        icon="pencil"
        iconColor="primary"
        closeAction="closePreviewModal"
    >
        <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-900">Policy name</label>
                    <input type="text" value="Release approvals" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-900">Escalation level</label>
                    <select class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm">
                        <option>Team lead</option>
                        <option selected>Ops manager</option>
                        <option>Admin</option>
                    </select>
                </div>
            </div>

            <div class="rounded-2xl bg-slate-100 p-4 text-sm leading-6 text-slate-700">
                This modal is rendered by the real core Blade component. The preview only fixes it open so it can be captured as a static screenshot.
            </div>
        </div>

        <x-slot:footer>
            <div class="flex items-center justify-end gap-3">
                @foreach ($footerActions as $action)
                    {!! $action->render($record) !!}
                @endforeach
            </div>
        </x-slot:footer>
    </x-wire::modal>
</div>
