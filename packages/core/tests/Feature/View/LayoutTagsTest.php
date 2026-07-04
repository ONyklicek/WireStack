<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;

it('renders <x-wire::grid> with a per-breakpoint columns map', function () {
    $html = Blade::render(<<<'BLADE'
        <x-wire::grid :columns="['md' => 2, 'lg' => 3]">cells</x-wire::grid>
    BLADE);

    expect($html)->toContain('grid')
        ->toContain('gap-4')
        ->toContain('md:grid-cols-2')
        ->toContain('lg:grid-cols-3')
        ->toContain('cells');
});

it('renders <x-wire::grid> with an integer column reflow', function () {
    $html = Blade::render('<x-wire::grid columns="3">cells</x-wire::grid>');

    expect($html)->toContain('grid-cols-1')->toContain('md:grid-cols-3');
});

it('renders <x-wire::split> with justify/align/gap/grow', function () {
    $html = Blade::render(<<<'BLADE'
        <x-wire::split from="lg" justify="between" align="center" :gap="6">
            <div>a</div><div>b</div>
        </x-wire::split>
    BLADE);

    expect($html)->toContain('flex flex-col')
        ->toContain('lg:flex-row')
        ->toContain('gap-6')
        ->toContain('justify-between')
        ->toContain('items-center')
        // The child-grow variant is emitted; Blade HTML-encodes the & and > in
        // the attribute, so assert on the unescaped tail (browsers decode it).
        ->toContain('*]:flex-1');
});

it('renders <x-wire::callout> through the shared callout partial', function () {
    $html = Blade::render(<<<'BLADE'
        <x-wire::callout color="warning" heading="Careful" icon="exclamation-triangle" dismissible>
            Body text
        </x-wire::callout>
    BLADE);

    expect($html)->toContain('rounded-md border')
        ->toContain('bg-amber-50')
        ->toContain('Careful')
        ->toContain('Body text')
        ->toContain('role="alert"')
        ->toContain('show = false');
});

it('renders <x-wire::empty-state> with the slot as its action row', function () {
    $html = Blade::render(<<<'BLADE'
        <x-wire::empty-state icon="outline:inbox" heading="No records" description="Add one to begin.">
            <button data-test="cta">New</button>
        </x-wire::empty-state>
    BLADE);

    expect($html)->toContain('rounded-full')
        ->toContain('No records')
        ->toContain('Add one to begin.')
        ->toContain('data-test="cta"');
});

it('renders <x-wire::tabs> with self-registering panels', function () {
    $html = Blade::render(<<<'BLADE'
        <x-wire::tabs>
            <x-wire::tab label="Profile">Profile body</x-wire::tab>
            <x-wire::tab label="Security">Security body</x-wire::tab>
        </x-wire::tabs>
    BLADE);

    expect($html)->toContain('wireTabs(0)')
        ->toContain('role="tablist"')
        ->toContain('registerTab(')
        ->toContain('Profile')
        ->toContain('Profile body')
        ->toContain('Security body');
});

it('renders <x-wire::wizard> with steps and Back/Next controls', function () {
    $html = Blade::render(<<<'BLADE'
        <x-wire::wizard>
            <x-wire::step label="Account">Account body</x-wire::step>
            <x-wire::step label="Confirm">Confirm body</x-wire::step>
        </x-wire::wizard>
    BLADE);

    expect($html)->toContain('wireWizard(0)')
        ->toContain('registerStep(')
        ->toContain('Account body')
        ->toContain('Confirm body')
        ->toContain('prev()')
        ->toContain('next()');
});

it('renders <x-wire::widget-grid> with a column count and canonical span classes', function () {
    $widgets = [
        StatsOverviewWidget::make()->columnSpan(2)->stats([Stat::make('Revenue', '$45,231')]),
    ];

    $html = Blade::render('<x-wire::widget-grid :widgets="$widgets" :columns="3" />', ['widgets' => $widgets]);

    expect($html)->toContain('wire-widget-grid')
        // Column count drives the responsive grid.
        ->toContain('xl:grid-cols-3')
        // Span delegates to HasColumnSpan::getColumnSpanClass() — the safelisted
        // `sm:col-span-2`, never the un-emitted `col-span-2`.
        ->toContain('sm:col-span-2')
        ->not->toContain('"col-span-2"')
        // Each Htmlable widget is rendered into the grid.
        ->toContain('Revenue');
});

it('renders <x-wire::section> and <x-wire::fieldset> shells', function () {
    $section = Blade::render('<x-wire::section heading="Profile" description="Basic info">fields</x-wire::section>');
    expect($section)->toContain('Profile')->toContain('Basic info')->toContain('fields');

    $fieldset = Blade::render('<x-wire::fieldset legend="Address">fields</x-wire::fieldset>');
    expect($fieldset)->toContain('<fieldset')->toContain('<legend')->toContain('Address')->toContain('fields');
});
