<?php

declare(strict_types=1);

use Illuminate\Support\ViewErrorBag;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasColor;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasIcon;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireForms\Components\Radio;

function renderRadio(Radio $field): string
{
    view()->share('errors', new ViewErrorBag);

    return view('wire-forms::components.radio', ['field' => $field])->render();
}

enum RadioTestPlan: string implements HasColor, HasIcon, HasLabel
{
    case Pro = 'pro';
    case Free = 'free';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pro => 'Pro',
            self::Free => 'Free',
        };
    }

    public function getIcon(): string|Icon|null
    {
        return match ($this) {
            self::Pro => Icon::star,
            self::Free => Icon::gift,
        };
    }

    public function getColor(): string|Color|null
    {
        return match ($this) {
            self::Pro => Color::Success,
            self::Free => Color::Gray,
        };
    }
}

test('default variant renders native radio inputs', function () {
    $html = renderRadio(
        Radio::make('role')->options(['admin' => 'Admin', 'user' => 'User'])
    );

    expect($html)->toContain('type="radio"')
        ->and($html)->toContain('wire:model="role"')
        ->and($html)->toContain('Admin')
        ->and($html)->toContain('User')
        ->and($html)->not->toContain('peer sr-only');
});

test('cards variant renders selectable cards with an indicator', function () {
    $html = renderRadio(
        Radio::make('plan')
            ->options(['pro' => 'Pro', 'free' => 'Free'])
            ->descriptions(['pro' => 'Everything included'])
            ->cards()
    );

    expect($html)->toContain('peer sr-only')
        ->and($html)->toContain('peer-checked:border-primary-500')
        ->and($html)->toContain('wf-card-indicator')
        ->and($html)->toContain('Everything included');
});

test('cards without indicator omit the radio dot', function () {
    $html = renderRadio(
        Radio::make('plan')->options(['pro' => 'Pro'])->cards()->hideIndicator()
    );

    expect($html)->toContain('peer sr-only')
        ->and($html)->not->toContain('wf-card-indicator');
});

test('cards with icons render an icon per option', function () {
    $html = renderRadio(
        Radio::make('plan')
            ->options(['pro' => 'Pro'])
            ->icons(['pro' => 'star'])
            ->cards()
    );

    expect($html)->toContain('wf-card-icon')
        ->and($html)->toContain('<svg');
});

test('inline cards flow horizontally', function () {
    $html = renderRadio(
        Radio::make('plan')->options(['a' => 'A', 'b' => 'B'])->cards()->inline()
    );

    expect($html)->toContain('grid-flow-col');
});

test('segmented variant renders a pill-track group', function () {
    $html = renderRadio(
        Radio::make('plan')
            ->options(['a' => 'A', 'b' => 'B'])
            ->icons(['a' => 'star'])
            ->segmented()
    );

    expect($html)->toContain('role="radiogroup"')
        ->and($html)->toContain('peer sr-only')
        ->and($html)->toContain('peer-checked:bg-white')
        ->and($html)->toContain('<svg');
});

test('buttons variant renders separate buttons filled on selection, stacked by default', function () {
    $html = renderRadio(
        Radio::make('plan')
            ->options(['a' => 'A', 'b' => 'B', 'c' => 'C'])
            ->icons(['a' => 'star'])
            ->buttons()
    );

    expect($html)->toContain('role="radiogroup"')
        ->and($html)->toContain('peer sr-only')
        ->and($html)->toContain('peer-checked:bg-primary-600')
        ->and($html)->toContain('flex-col')          // stacked (pod sebou)
        ->and($html)->toContain('rounded-lg')         // each button independently rounded
        ->and($html)->not->toContain('-ml-px')        // not a joined group
        ->and($html)->toContain('<svg');
});

test('inline buttons flow in a row', function () {
    $html = renderRadio(
        Radio::make('plan')->options(['a' => 'A', 'b' => 'B'])->buttons()->inline()
    );

    expect($html)->toContain('flex-row')
        ->and($html)->not->toContain('flex-col');
});

test('size defaults to md and is fluent via sm/md/lg', function () {
    expect(Radio::make('plan')->getSize())->toBe('md')
        ->and(Radio::make('plan')->sm()->getSize())->toBe('sm')
        ->and(Radio::make('plan')->lg()->getSize())->toBe('lg')
        ->and(Radio::make('plan')->size('xs')->getSize())->toBe('xs');
});

test('buttons variant scales padding and icon with size', function () {
    $html = renderRadio(
        Radio::make('plan')
            ->options(['a' => 'A'])
            ->icons(['a' => 'star'])
            ->buttons()
            ->lg()
    );

    expect($html)->toContain('px-4 py-2.5 text-base')   // HasSize::getButtonSizeClasses('lg')
        ->and($html)->toContain('w-5 h-5');              // HasSize::getButtonIconSizeClasses('lg')
});

test('segmented variant scales with size', function () {
    $html = renderRadio(
        Radio::make('plan')->options(['a' => 'A'])->segmented()->sm()
    );

    expect($html)->toContain('px-2.5 py-1.5');           // HasSize small padding
});

test('color defaults to primary and is fluent via string or Color enum', function () {
    expect(Radio::make('plan')->getColor())->toBe('primary')
        ->and(Radio::make('plan')->color('success')->getColor())->toBe('success')
        ->and(Radio::make('plan')->color(Color::Danger)->getColor())->toBe('danger');
});

test('buttons fill uses the configured color', function () {
    $html = renderRadio(
        Radio::make('plan')->options(['a' => 'A'])->buttons()->color('success')
    );

    expect($html)->toContain('peer-checked:bg-emerald-600')
        ->and($html)->not->toContain('peer-checked:bg-primary-600');
});

test('cards border uses the configured color', function () {
    $html = renderRadio(
        Radio::make('plan')->options(['a' => 'A'])->cards()->color('danger')
    );

    expect($html)->toContain('peer-checked:border-red-500')
        ->and($html)->toContain('peer-checked:[&_.wf-card-indicator]:bg-red-600');
});

test('segmented label uses the configured color', function () {
    $html = renderRadio(
        Radio::make('plan')->options(['a' => 'A'])->segmented()->color(Color::Info)
    );

    expect($html)->toContain('peer-checked:text-cyan-600');
});

test('default radio accent uses the configured color', function () {
    $html = renderRadio(
        Radio::make('plan')->options(['a' => 'A'])->color('success')
    );

    expect($html)->toContain('text-emerald-600')
        ->and($html)->toContain('focus:ring-emerald-500');
});

test('per-option colors derive automatically from a HasColor enum via options', function () {
    $field = Radio::make('plan')->options(RadioTestPlan::class)->cards();

    expect($field->getColors())->toBe([
        'pro' => 'success',
        'free' => 'gray',
    ]);

    $html = renderRadio($field);

    // Pro card tinted emerald, Free card tinted gray — each option keeps its own color.
    expect($html)->toContain('peer-checked:border-emerald-500')
        ->and($html)->toContain('peer-checked:border-gray-500');
});

test('explicit colors override enum-derived colors', function () {
    $field = Radio::make('plan')
        ->options(RadioTestPlan::class)
        ->colors(['pro' => 'danger']);

    expect($field->getColors())->toBe([
        'pro' => 'danger',
        'free' => 'gray',
    ]);
});

test('per-option color falls back to the group color when unset', function () {
    $field = Radio::make('plan')
        ->options(['a' => 'A', 'b' => 'B'])
        ->buttons()
        ->color('primary')
        ->colors(['a' => 'danger']);

    $html = renderRadio($field);

    // Option a filled red (its own color), option b filled primary (group fallback).
    expect($html)->toContain('peer-checked:bg-red-600')
        ->and($html)->toContain('peer-checked:bg-primary-600');
});

test('every variant renders per-option icons', function () {
    $variants = [
        fn (Radio $f) => $f,               // default list
        fn (Radio $f) => $f->cards(),
        fn (Radio $f) => $f->segmented(),
        fn (Radio $f) => $f->buttons(),
    ];

    foreach ($variants as $configure) {
        $field = $configure(
            Radio::make('plan')->options(['a' => 'A', 'b' => 'B'])->icons(['a' => 'star'])
        );

        expect(renderRadio($field))->toContain('<svg');
    }
});

test('cards derive icons automatically from a HasIcon enum', function () {
    $field = Radio::make('plan')->options(RadioTestPlan::class)->cards();

    expect($field->getIcons())->toBe([
        'pro' => Icon::star->value(),
        'free' => Icon::gift->value(),
    ]);

    $html = renderRadio($field);

    expect($html)->toContain('wf-card-icon')
        ->and($html)->toContain('<svg');
});

test('explicit icons override enum-derived icons', function () {
    $field = Radio::make('plan')
        ->options(RadioTestPlan::class)
        ->icons(['pro' => 'bolt']);

    expect($field->getIcons())->toBe([
        'pro' => 'bolt',
        'free' => Icon::gift->value(),
    ]);
});

test('disabled radio marks every input disabled', function () {
    $html = renderRadio(
        Radio::make('plan')->options(['a' => 'A'])->cards()->disabled()
    );

    expect($html)->toContain('disabled');
});
