<!doctype html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Layout tags</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-100 p-8">
<div class="mx-auto max-w-4xl space-y-6">

    <x-wire::callout color="warning" heading="Storage almost full" icon="exclamation-triangle" dismissible>
        You have used 95% of your quota. Upgrade to keep uploading.
    </x-wire::callout>

    <x-wire::section heading="Team" description="People with access to this workspace.">
        <x-wire::grid :columns="['default' => 1, 'md' => 2, 'lg' => 3]" gap="gap-3">
            <div class="rounded-lg border border-gray-200 bg-white p-3 text-sm">Amelia — Admin</div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 text-sm">Ben — Editor</div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 text-sm">Cara — Viewer</div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 text-sm">Dan — Viewer</div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 text-sm">Eve — Editor</div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 text-sm">Finn — Admin</div>
        </x-wire::grid>
    </x-wire::section>

    <x-wire::flex from="md" justify="between" align="center" :gap="4">
        <div class="rounded-lg bg-white p-4 text-sm shadow-sm">Left panel</div>
        <div class="rounded-lg bg-white p-4 text-sm shadow-sm">Middle panel</div>
        <div class="rounded-lg bg-white p-4 text-sm shadow-sm">Right panel</div>
    </x-wire::flex>

    <x-wire::fieldset legend="Billing address">
        <div class="text-sm text-gray-600">Fields go here…</div>
    </x-wire::fieldset>

    <x-wire::section>
        <x-wire::empty-state icon="outline:inbox" heading="No invoices yet" description="Invoices you create will show up here.">
            <button class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white">New invoice</button>
        </x-wire::empty-state>
    </x-wire::section>

</div>
</body>
</html>
