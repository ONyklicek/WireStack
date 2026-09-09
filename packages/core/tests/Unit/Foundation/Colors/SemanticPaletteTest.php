<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Colors\SemanticPalette;
use NyonCode\WireCore\Foundation\Concerns\HasColor;

/** A bare consumer of the trait, so the static resolvers can be called directly. */
final class PaletteProbe
{
    use HasColor;
}

it('renders each role as its shipped hue when nothing says otherwise', function () {
    expect(SemanticPalette::hue('success'))->toBe('emerald')
        ->and(SemanticPalette::hue('danger'))->toBe('red')
        ->and(SemanticPalette::hue('warning'))->toBe('amber')
        ->and(SemanticPalette::hue('info'))->toBe('cyan');
});

it('leaves anything that is not a role alone', function () {
    // The reason this is safe to call on every colour a resolver receives, which
    // is what keeps it one line at the top rather than a branch inside each arm.
    expect(SemanticPalette::hue('teal'))->toBe('teal')
        ->and(SemanticPalette::hue('primary'))->toBe('primary')
        ->and(SemanticPalette::hue('not-a-colour'))->toBe('not-a-colour');
});

it('re-points a role, and the classes follow on every surface', function () {
    config()->set('wire-core.colors.success', 'teal');

    expect(SemanticPalette::hue('success'))->toBe('teal')
        ->and(PaletteProbe::getBadgeColorClasses('success'))->toContain('bg-teal-100')
        ->and(PaletteProbe::getTextColorClasses('success'))->toContain('text-teal-600')
        ->and(PaletteProbe::getSolidBgClass('success'))->toBe('bg-teal-600');
});

it('keeps the shipped hue when the configured one names nothing', function () {
    // A typo must not render a colourless button: falling through every arm to
    // the grey default is the failure nobody can explain from the screen.
    config()->set('wire-core.colors.danger', 'crimson');

    expect(SemanticPalette::hue('danger'))->toBe('red')
        ->and(PaletteProbe::getSolidBgClass('danger'))->toBe('bg-red-600');
});

it('refuses to let one role name another', function () {
    // Either a cycle or a second name for the same thing, and neither is worth
    // supporting.
    config()->set('wire-core.colors.warning', 'danger');

    expect(SemanticPalette::hue('warning'))->toBe('amber');
});

it('ignores a configured value that is not a string', function () {
    config()->set('wire-core.colors.info', ['cyan']);

    expect(SemanticPalette::hue('info'))->toBe('cyan');
});

it('moves every surface when info is re-pointed, the modal icon included', function () {
    // This surface used to render `info` as the alert banner's blue while
    // sixteen others gave it cyan — one role wearing two colours, which read as
    // two meanings rather than one in two tones. There is no exception now.
    config()->set('wire-core.colors.info', 'teal');

    expect(SemanticPalette::hue('info'))->toBe('teal')
        ->and(PaletteProbe::getModalIconBgClass('info'))->toContain('bg-teal-100')
        ->and(PaletteProbe::getModalIconTextClass('info'))->toContain('text-teal-600')
        ->and(PaletteProbe::getAlertColorClasses('info'))->toContain('bg-teal-50');
});

it('still falls to the neutral blue for a colour the alert does not own', function () {
    // The pinned contract that survives: an alert is semantic-only, so anything
    // that is not a role it owns is drawn informational rather than decorated.
    expect(PaletteProbe::getAlertColorClasses('purple'))->toContain('bg-blue-50')
        ->and(PaletteProbe::getAlertColorClasses('not-a-colour'))->toContain('bg-blue-50');
});

it('still normalizes the other roles on the modal icon surface', function () {
    // The regression this pairing caused once: excluding the surface wholesale
    // left success/danger/warning with no arm to land in, so every one of them
    // fell through to the neutral default.
    config()->set('wire-core.colors.success', 'teal');

    expect(PaletteProbe::getModalIconBgClass('success'))->toContain('bg-teal-100')
        ->and(PaletteProbe::getModalIconTextClass('danger'))->toContain('text-red-600');
});
