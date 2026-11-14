{{-- A draft kept in this browser (InteractsWithUnsavedChanges::remembersDraft()),
     offered back when the page arrives to find one. Inside the element that runs
     wireUnsavedChanges, whose draftAvailable / restoreDraft() / discardDraft()
     it reads. --}}
{{-- Variables: $unsavedChanges --}}
@if(isset($unsavedChanges['draftKey']))
    <div
        x-show="draftAvailable"
        x-cloak
        role="status"
        data-testid="form-draft-banner" @wireEl('form-draft-banner')
        class="flex flex-col gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200 sm:flex-row sm:items-center sm:justify-between dark:bg-amber-400/10 dark:text-amber-200 dark:ring-amber-400/30"
    >
        <span class="flex items-center gap-2">
            {!! icon('outline:clock', 'h-5 w-5 shrink-0') !!}
            {{ __('wire-panels::messages.draft_available') }}
        </span>
        <span class="flex gap-2">
            <button type="button" x-on:click="restoreDraft()" data-testid="form-draft-restore"
                class="rounded-lg bg-amber-400 px-3 py-1.5 text-sm font-semibold text-gray-950 hover:bg-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500">
                {{ __('wire-panels::messages.draft_restore') }}
            </button>
            <button type="button" x-on:click="discardDraft()" data-testid="form-draft-discard"
                class="rounded-lg px-3 py-1.5 text-sm font-medium text-amber-900 hover:bg-amber-100 dark:text-amber-200 dark:hover:bg-amber-400/15">
                {{ __('wire-panels::messages.draft_discard') }}
            </button>
        </span>
    </div>
@endif
