<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Enums\NativeControlMode;

/**
 * @param  array<string, mixed>  $data
 */
function renderSelectControl(array $data = []): string
{
    return view('wire-core::partials.select-control', $data + [
        'selectId' => 'status',
        'statePath' => 'data.status',
        'options' => ['draft' => 'Draft', 'published' => 'Published'],
        'placeholder' => 'Pick one',
        'searchPrompt' => 'Search',
        'noResultsMessage' => 'Nothing',
    ])->render();
}

it('renders only the combobox by default', function () {
    $html = renderSelectControl();

    expect($html)
        ->toContain('wireSearchableSelect(')
        ->not->toContain('<select');
});

it('renders only the native select when native everywhere', function () {
    $html = renderSelectControl(['nativeMode' => NativeControlMode::Always, 'required' => true]);

    expect($html)
        ->toContain('<select')
        ->toContain('id="status"')
        ->toContain('wire:model="data.status"')
        ->toContain('required')
        // Required: the empty row is only the starting point, not a choice.
        ->toMatch('/<option value="" disabled hidden\s*>Pick one<\/option>/')
        ->not->toContain('wireSearchableSelect(')
        ->not->toContain('aria-required');
});

it('renders both halves split at the breakpoint when native on mobile', function () {
    $html = renderSelectControl([
        'nativeMode' => NativeControlMode::Mobile,
        'mobileBreakpoint' => 'md',
        'required' => true,
        'ariaLabel' => 'Status',
        'sheetOnMobile' => true,
    ]);

    expect($html)
        ->toContain('class="relative max-md:hidden"')
        ->toContain('<div class="hidden max-md:block">')
        ->toContain('wireSearchableSelect(')
        // The <label for> keeps pointing at the combobox trigger…
        ->toContain('id="status"')
        // …so the native twin takes its own id and names itself.
        ->toContain('id="status-native"')
        ->toContain('aria-label="Status"')
        // A required hidden twin would block submitting the form on a desktop.
        ->toContain('aria-required="true"')
        ->not->toMatch('/<select[^>]*\srequired[\s>]/')
        // The sheet the combobox would become below md is never seen there.
        ->toContain('sheetOnMobile: false');
});

it('binds the native select live when the combobox is live', function () {
    expect(renderSelectControl(['nativeMode' => NativeControlMode::Always, 'live' => true]))
        ->toContain('wire:model.live="data.status"');
});

it('leaves the placeholder row out of a multiple native select', function () {
    expect(renderSelectControl(['nativeMode' => NativeControlMode::Always, 'multiple' => true]))
        ->toContain('multiple')
        ->not->toContain('<option value="">');
});

it('disables the disabled values in both controls, compared as strings', function () {
    $html = renderSelectControl([
        'nativeMode' => NativeControlMode::Mobile,
        'options' => [1 => 'One', 2 => 'Two'],
        'disabledValues' => ['2'],
    ]);

    expect($html)
        ->toMatch('/<option value="2" disabled\s*>Two/')
        ->not->toMatch('/<option value="1" disabled/')
        ->toContain("disabledValues: JSON.parse('[\\u00222\\u0022]')");
});

it('marks the first-paint selection on the native select', function () {
    $html = renderSelectControl([
        'nativeMode' => NativeControlMode::Always,
        'selectedValues' => ['published'],
    ]);

    expect($html)
        ->toMatch('/<option value="published"\s+selected\s*>/')
        ->not->toMatch('/<option value="draft"\s+selected/');
});

it('hands the combobox its options in the order they were given', function () {
    // A JS object lists integer-like keys ascending — a map would reorder these.
    $html = renderSelectControl(['options' => [10 => 'Apple', 2 => 'Banana']]);

    expect($html)->toContain("initialOptions: JSON.parse('[[\\u002210\\u0022,\\u0022Apple\\u0022],[\\u00222\\u0022,\\u0022Banana\\u0022]]')");
});

it('offers an optional select a way back to empty, named even without a placeholder', function () {
    expect(renderSelectControl(['nativeMode' => NativeControlMode::Always]))
        ->toMatch('/<option value=""\s*>Pick one<\/option>/')
        ->and(renderSelectControl(['nativeMode' => NativeControlMode::Always, 'placeholder' => null]))
        ->toMatch('/<option value=""\s*>—<\/option>/')
        ->not->toContain('hidden');
});

it('renders the touch sheet beside the floating panel when touch', function () {
    $html = renderSelectControl(['touch' => true, 'sheetTitle' => 'Status', 'mobileBreakpoint' => 'md', 'searchable' => false]);

    expect($html)
        ->toContain('data-testid="select-touch-sheet"')
        ->toContain('touch: true')
        ->toContain('x-trap.noscroll.noautofocus="open && touchNow"')
        // The floating panel hides where the touch sheet shows, and never becomes a sheet itself.
        ->toContain('max-md:hidden')
        ->toContain('hidden max-md:block')
        ->toContain('sheetOnMobile: false')
        ->toContain('max-md:min-h-11 max-md:text-base')
        ->toContain('>Status</p>')
        // Not searchable: the sheet rests at its content, not full height.
        ->toContain('max-h-[calc(100dvh-3rem)]');
});

it('renders no touch sheet by default', function () {
    expect(renderSelectControl())->not->toContain('select-touch-sheet');
});

it('gives a searchable touch sheet the full height and a 16px search', function () {
    $html = renderSelectControl(['touch' => true, 'searchable' => true]);

    expect($html)
        ->toContain('data-testid="select-touch-search"')
        ->toMatch('/select-touch-search"\s+class="h-11 w-full[^"]*text-base/')
        ->toMatch('/class="fixed inset-x-0 bottom-0 z-50[^"]*top-12/');
});
