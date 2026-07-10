<div class="mx-auto w-full max-w-[760px] space-y-8 p-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6" data-demo="tabs">
        <x-wire::tabs>
            <x-wire::tab label="Profile"><div class="text-sm" data-panel="Profile">Profile panel content</div></x-wire::tab>
            <x-wire::tab label="Security"><div class="text-sm" data-panel="Security">Security panel content</div></x-wire::tab>
            <x-wire::tab label="Billing"><div class="text-sm" data-panel="Billing">Billing panel content</div></x-wire::tab>
        </x-wire::tabs>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6" data-demo="wizard">
        <x-wire::wizard>
            <x-wire::step label="Account"><div class="text-sm" data-step="Account">Account step content</div></x-wire::step>
            <x-wire::step label="Team"><div class="text-sm" data-step="Team">Team step content</div></x-wire::step>
            <x-wire::step label="Confirm"><div class="text-sm" data-step="Confirm">Confirm step content</div></x-wire::step>
        </x-wire::wizard>
    </div>
</div>
