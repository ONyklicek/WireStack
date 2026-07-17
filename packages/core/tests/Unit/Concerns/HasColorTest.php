<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Colors\Color;

class TestColorClass
{
    use HasColor;

    // Expose the protected per-surface button resolvers so the full palette can
    // be exercised directly (they are only called during rendering otherwise).
    public function solid(string $color): string
    {
        return $this->getSolidColorClasses($color);
    }

    public function outlined(string $color): string
    {
        return $this->getOutlinedColorClasses($color);
    }

    public function ghost(string $color): string
    {
        return $this->getGhostColorClasses($color);
    }

    public function iconButton(string $color): string
    {
        return $this->getIconButtonColorClasses($color);
    }

    public function quietButton(string $color): string
    {
        return $this->getQuietButtonColorClasses($color);
    }
}

/**
 * Every surface HasColor owns, keyed by name, each reduced to
 * `fn (string $color): string` so one loop can hold them all to the same rule.
 *
 * Enumerated by hand rather than reflected: reflection would silently stop
 * checking a resolver whose name stopped matching the pattern, which is the one
 * failure this list exists to prevent.
 *
 * @return array<string, callable(string): string>
 */
function colorSurfaces(): array
{
    $obj = new TestColorClass;

    return [
        'solid' => fn (string $c): string => $obj->solid($c),
        'outlined' => fn (string $c): string => $obj->outlined($c),
        'ghost' => fn (string $c): string => $obj->ghost($c),
        'iconButton' => fn (string $c): string => $obj->iconButton($c),
        'quiet' => fn (string $c): string => $obj->quietButton($c),
        'badge' => fn (string $c): string => TestColorClass::getBadgeColorClasses($c),
        // The only resolver returning an array; flattened so it can be compared
        // like the rest.
        'choice' => fn (string $c): string => implode(' ', TestColorClass::getChoiceColorClasses($c)),
        'text' => fn (string $c): string => TestColorClass::getTextColorClasses($c),
        'link' => fn (string $c): string => TestColorClass::getLinkColorClasses($c),
        'solidBg' => fn (string $c): string => TestColorClass::getSolidBgClass($c),
        'softBg' => fn (string $c): string => TestColorClass::getSoftBgClass($c),
        'rowTint' => fn (string $c): string => TestColorClass::getRowTintClasses($c),
        'gradientFill' => fn (string $c): string => TestColorClass::getGradientFillClasses($c),
        'fillText' => fn (string $c): string => TestColorClass::getFillTextClasses($c),
        'modalSubmit' => fn (string $c): string => TestColorClass::getModalSubmitButtonClasses($c),
        'alert' => fn (string $c): string => TestColorClass::getAlertColorClasses($c),
        'modalIconBg' => fn (string $c): string => TestColorClass::getModalIconBgClass($c),
        'modalIconText' => fn (string $c): string => TestColorClass::getModalIconTextClass($c),
    ];
}

/**
 * The raw Tailwind hues, which must render as themselves.
 *
 * @return array<int, string>
 */
function rawHues(): array
{
    return [
        'blue', 'green', 'yellow', 'red', 'cyan',
        'orange', 'lime', 'teal', 'sky', 'indigo', 'violet', 'purple', 'fuchsia',
        'pink', 'rose', 'slate', 'zinc', 'neutral', 'stone',
    ];
}

/**
 * The extended (non-semantic) palette that every owner-facing surface now
 * accepts, so `->color('purple')` looks the same on a solid button, an outlined
 * button, a link, a modal submit button and a choice card — not just a badge.
 */
$extendedPalette = [
    'blue', 'green', 'yellow',
    'orange', 'lime', 'teal', 'sky', 'indigo', 'violet', 'purple', 'fuchsia',
    'pink', 'rose', 'slate', 'zinc', 'neutral', 'stone',
];

it('resolves the full extended palette for every button surface', function () use ($extendedPalette) {
    $obj = new TestColorClass;

    foreach ($extendedPalette as $color) {
        expect($obj->solid($color))->toContain("bg-$color-")
            ->and($obj->outlined($color))->toContain("border-$color-")
            ->and($obj->ghost($color))->toContain("text-$color-")
            ->and($obj->iconButton($color))->toContain("text-$color-")
            ->and(TestColorClass::getLinkColorClasses($color))->toContain("text-$color-")
            ->and(TestColorClass::getModalSubmitButtonClasses($color))->toContain("bg-$color-")
            ->and(TestColorClass::getModalIconBgClass($color))->toContain("bg-$color-")
            ->and(TestColorClass::getModalIconTextClass($color))->toContain("text-$color-")
            ->and(TestColorClass::getSolidBgClass($color))->toContain("bg-$color-")
            ->and(TestColorClass::getSoftBgClass($color))->toContain("bg-$color-")
            ->and(TestColorClass::getRowTintClasses($color))->toContain("bg-$color-50")
            ->and(TestColorClass::getRowTintClasses($color))->toContain("hover:bg-$color-100")
            ->and(TestColorClass::getChoiceColorClasses($color)['solid'])->toContain("bg-$color-");
    }
});

it('keeps semantic aliases mapping to the same hue across button surfaces', function () {
    $obj = new TestColorClass;

    expect($obj->solid('emerald'))->toBe($obj->solid('success'))
        ->and($obj->solid('amber'))->toBe($obj->solid('warning'))
        ->and($obj->outlined('emerald'))->toBe($obj->outlined('success'))
        ->and($obj->outlined('amber'))->toBe($obj->outlined('warning'))
        ->and($obj->ghost('amber'))->toBe($obj->ghost('warning'))
        ->and($obj->ghost('emerald'))->toBe($obj->ghost('success'))
        ->and($obj->iconButton('emerald'))->toBe($obj->iconButton('success'))
        ->and(TestColorClass::getModalIconBgClass('emerald'))->toBe(TestColorClass::getModalIconBgClass('success'))
        ->and(TestColorClass::getModalIconTextClass('amber'))->toBe(TestColorClass::getModalIconTextClass('warning'));
});

it('resolves info/cyan on the button surfaces that previously fell back to gray', function () {
    $obj = new TestColorClass;

    expect($obj->ghost('info'))->toContain('cyan')
        ->and($obj->iconButton('info'))->toContain('cyan')
        ->and(TestColorClass::getModalSubmitButtonClasses('info'))->toContain('cyan')
        ->and(TestColorClass::getModalIconBgClass('cyan'))->toContain('cyan');
});

it('renders the quiet surface neutral at rest, with color only on intent', function () {
    $obj = new TestColorClass;

    // A non-destructive hue rests neutral gray (no solid fill) and reveals its
    // color only on hover/focus.
    expect($obj->quietButton('primary'))
        ->toContain('text-gray-600')
        ->toContain('dark:text-gray-300')
        ->toContain('hover:text-primary-600')
        ->toContain('hover:bg-primary-50')
        ->not->toContain('bg-primary-600'); // never a solid fill at rest
});

it('keeps the destructive hue legible at rest on the quiet surface', function () {
    $obj = new TestColorClass;

    // Touch has no hover, so danger must read as danger without interaction.
    expect($obj->quietButton('danger'))
        ->toContain('text-red-600')
        ->and($obj->quietButton('danger'))->toBe($obj->quietButton('red'));
});

it('sets an explicit focus ring on every quiet arm (WCAG 2.4.7)', function () {
    $obj = new TestColorClass;

    // The shared button base always applies focus:ring-2; without a ring color
    // the keyboard focus indicator is invisible.
    foreach (Color::values() as $color) {
        $this->assertStringContainsString('focus:ring-', $obj->quietButton($color), "[quiet] [{$color}] has no focus ring color.");
    }
});

it('returns correct badge color classes for primary', function () {
    expect(TestColorClass::getBadgeColorClasses('primary'))->toContain('bg-primary-100');
});

it('returns correct badge color classes for success', function () {
    expect(TestColorClass::getBadgeColorClasses('success'))->toContain('bg-emerald-100');
});

it('returns correct badge color classes for danger', function () {
    expect(TestColorClass::getBadgeColorClasses('danger'))->toContain('bg-red-100');
});

it('returns correct badge color classes for warning', function () {
    expect(TestColorClass::getBadgeColorClasses('warning'))->toContain('bg-amber-100');
});

it('returns correct badge color classes for info', function () {
    expect(TestColorClass::getBadgeColorClasses('info'))->toContain('bg-cyan-100');
});

it('returns correct badge color classes for gray', function () {
    expect(TestColorClass::getBadgeColorClasses('gray'))->toContain('bg-gray-100');
});

it('returns gray badge classes for unknown color', function () {
    expect(TestColorClass::getBadgeColorClasses('nonexistent'))->toContain('bg-gray-100');
});

it('keeps true badge aliases mapping to the same hue', function () {
    expect(TestColorClass::getBadgeColorClasses('emerald'))->toBe(TestColorClass::getBadgeColorClasses('success'))
        ->and(TestColorClass::getBadgeColorClasses('amber'))->toBe(TestColorClass::getBadgeColorClasses('warning'))
        ->and(TestColorClass::getBadgeColorClasses('secondary'))->toBe(TestColorClass::getBadgeColorClasses('gray'));
});

it('renders literal badge hues distinct from the semantic role', function () {
    expect(TestColorClass::getBadgeColorClasses('blue'))->toContain('bg-blue-100')
        ->and(TestColorClass::getBadgeColorClasses('blue'))->not->toBe(TestColorClass::getBadgeColorClasses('primary'))
        ->and(TestColorClass::getBadgeColorClasses('green'))->toContain('bg-green-100')
        ->and(TestColorClass::getBadgeColorClasses('green'))->not->toBe(TestColorClass::getBadgeColorClasses('success'))
        ->and(TestColorClass::getBadgeColorClasses('yellow'))->toContain('bg-yellow-100')
        ->and(TestColorClass::getBadgeColorClasses('yellow'))->not->toBe(TestColorClass::getBadgeColorClasses('warning'));
});

it('returns correct soft background classes for the toggle off track', function () {
    expect(TestColorClass::getSoftBgClass('primary'))->toContain('bg-primary-200')
        ->and(TestColorClass::getSoftBgClass('success'))->toContain('bg-emerald-200')
        ->and(TestColorClass::getSoftBgClass('danger'))->toContain('bg-red-200')
        ->and(TestColorClass::getSoftBgClass('warning'))->toContain('bg-amber-200')
        ->and(TestColorClass::getSoftBgClass('info'))->toContain('bg-cyan-200')
        ->and(TestColorClass::getSoftBgClass('purple'))->toContain('bg-purple-200')
        ->and(TestColorClass::getSoftBgClass('pink'))->toContain('bg-pink-200')
        ->and(TestColorClass::getSoftBgClass('gray'))->toContain('bg-gray-200')
        ->and(TestColorClass::getSoftBgClass('nonexistent'))->toContain('bg-gray-200');
});

it('maps true soft background aliases to the same hue', function () {
    expect(TestColorClass::getSoftBgClass('emerald'))->toBe(TestColorClass::getSoftBgClass('success'))
        ->and(TestColorClass::getSoftBgClass('amber'))->toBe(TestColorClass::getSoftBgClass('warning'))
        ->and(TestColorClass::getSoftBgClass('red'))->toBe(TestColorClass::getSoftBgClass('danger'))
        ->and(TestColorClass::getSoftBgClass('cyan'))->toBe(TestColorClass::getSoftBgClass('info'))
        ->and(TestColorClass::getSoftBgClass('secondary'))->toBe(TestColorClass::getSoftBgClass('gray'));
});

it('renders literal soft background hues distinct from the semantic role', function () {
    expect(TestColorClass::getSoftBgClass('blue'))->toContain('bg-blue-200')
        ->and(TestColorClass::getSoftBgClass('blue'))->not->toBe(TestColorClass::getSoftBgClass('primary'))
        ->and(TestColorClass::getSoftBgClass('green'))->toContain('bg-green-200')
        ->and(TestColorClass::getSoftBgClass('yellow'))->toContain('bg-yellow-200');
});

it('returns correct modal icon bg classes', function () {
    expect(TestColorClass::getModalIconBgClass('danger'))->toContain('bg-red-100')
        ->and(TestColorClass::getModalIconBgClass('warning'))->toContain('bg-amber-100')
        ->and(TestColorClass::getModalIconBgClass('success'))->toContain('bg-emerald-100')
        ->and(TestColorClass::getModalIconBgClass('info'))->toContain('bg-blue-100');
});

it('returns correct modal icon text classes', function () {
    expect(TestColorClass::getModalIconTextClass('danger'))->toContain('text-red-600')
        ->and(TestColorClass::getModalIconTextClass('warning'))->toContain('text-amber-600')
        ->and(TestColorClass::getModalIconTextClass('success'))->toContain('text-emerald-600')
        ->and(TestColorClass::getModalIconTextClass('info'))->toContain('text-blue-600');
});

it('returns correct alert color classes per semantic hue', function () {
    expect(TestColorClass::getAlertColorClasses('success'))->toContain('bg-emerald-50')
        ->and(TestColorClass::getAlertColorClasses('warning'))->toContain('bg-amber-50')
        ->and(TestColorClass::getAlertColorClasses('danger'))->toContain('bg-red-50')
        ->and(TestColorClass::getAlertColorClasses('info'))->toContain('bg-blue-50')
        ->and(TestColorClass::getAlertColorClasses('nonexistent'))->toContain('bg-blue-50');
});

it('maps true alert aliases to the same hue', function () {
    expect(TestColorClass::getAlertColorClasses('emerald'))->toBe(TestColorClass::getAlertColorClasses('success'))
        ->and(TestColorClass::getAlertColorClasses('red'))->toBe(TestColorClass::getAlertColorClasses('danger'))
        ->and(TestColorClass::getAlertColorClasses('amber'))->toBe(TestColorClass::getAlertColorClasses('warning'));
});

it('renders literal alert hues distinct from the semantic role', function () {
    expect(TestColorClass::getAlertColorClasses('green'))->toContain('bg-green-50')
        ->and(TestColorClass::getAlertColorClasses('green'))->not->toBe(TestColorClass::getAlertColorClasses('success'))
        ->and(TestColorClass::getAlertColorClasses('yellow'))->toContain('bg-yellow-50')
        ->and(TestColorClass::getAlertColorClasses('yellow'))->not->toBe(TestColorClass::getAlertColorClasses('warning'));
});

it('returns correct modal submit button classes per semantic hue', function () {
    expect(TestColorClass::getModalSubmitButtonClasses('primary'))->toContain('bg-primary-600')
        ->and(TestColorClass::getModalSubmitButtonClasses('danger'))->toContain('bg-red-600')
        ->and(TestColorClass::getModalSubmitButtonClasses('success'))->toContain('bg-emerald-600')
        ->and(TestColorClass::getModalSubmitButtonClasses('warning'))->toContain('bg-amber-500')
        ->and(TestColorClass::getModalSubmitButtonClasses('nonexistent'))->toContain('bg-primary-600');
});

it('includes active and focus states on modal submit button classes', function () {
    expect(TestColorClass::getModalSubmitButtonClasses('danger'))
        ->toContain('active:bg-red-800')
        ->toContain('focus:ring-red-500');
});

it('resolves the adaptive black endpoint (dark in light, white in dark)', function () {
    $obj = new TestColorClass;

    expect($obj->solid('black'))->toContain('bg-gray-900')->toContain('dark:bg-white')
        ->and(TestColorClass::getBadgeColorClasses('black'))->toContain('bg-gray-900')->toContain('dark:bg-white')
        ->and(TestColorClass::getTextColorClasses('black'))->toBe('text-gray-900 dark:text-white')
        ->and(TestColorClass::getSolidBgClass('black'))->toBe('bg-gray-900 dark:bg-white')
        ->and(TestColorClass::getChoiceColorClasses('black')['solid'])->toContain('peer-checked:bg-gray-900')
        ->and(TestColorClass::getFillTextClasses('black'))->toBe('text-gray-900 dark:text-white');
});

it('resolves the adaptive white endpoint (light in light, dark in dark)', function () {
    $obj = new TestColorClass;

    expect($obj->solid('white'))->toContain('bg-white')->toContain('dark:bg-gray-900')
        ->and(TestColorClass::getBadgeColorClasses('white'))->toContain('bg-white')
        ->and(TestColorClass::getTextColorClasses('white'))->toBe('text-white dark:text-gray-900')
        ->and(TestColorClass::getSolidBgClass('white'))->toBe('bg-white dark:bg-gray-900')
        ->and(TestColorClass::getChoiceColorClasses('white')['solid'])->toContain('peer-checked:bg-white')
        ->and(TestColorClass::getModalSubmitButtonClasses('white'))->toContain('!text-gray-900');
});

// ─── Whole-palette sweeps ─────────────────────────────────────
//
// Every resolver is a `match` with a `default` arm, so a hue nobody wrote an arm
// for does not fail — it silently renders as something else (gray, blue, or the
// brand primary, depending on the surface). A per-colour spot check cannot see
// that; these sweeps hold every surface to the same rule at once, and are what
// catches an arm that was missed when a colour was added to the palette.
//
// They use PHPUnit assertions rather than expect(): across 17 surfaces × 19
// hues, naming which pair broke is most of the value, and expect() has nowhere
// to put that message.

it('renders every raw hue as itself, on every surface that carries the palette', function () {
    foreach (colorSurfaces() as $surface => $resolve) {
        // `alert` is deliberately semantic-only; see its own test below.
        if ($surface === 'alert') {
            continue;
        }

        foreach (rawHues() as $hue) {
            $this->assertStringContainsString(
                "-{$hue}-",
                $resolve($hue),
                "[{$surface}] has no arm for the [{$hue}] hue, so it fell through to its default."
            );
        }
    }
});

it('resolves the achromatic endpoints on every surface, never through the default', function () {
    // black and white have no numeric Tailwind scale, so each surface renders
    // them by hand. A missing arm shows up as "black looks like an unset color".
    foreach (colorSurfaces() as $surface => $resolve) {
        $default = $resolve('not-a-color');

        $this->assertNotSame($default, $resolve('black'), "[{$surface}] has no arm for black.");
        $this->assertNotSame($default, $resolve('white'), "[{$surface}] has no arm for white.");
        $this->assertNotSame($resolve('white'), $resolve('black'), "[{$surface}] renders black and white identically.");
    }
});

it('maps every semantic alias to its role, on every surface', function () {
    // emerald/amber/secondary are the only true aliases left: they must be
    // indistinguishable from the role they stand for, everywhere.
    $aliases = ['emerald' => 'success', 'amber' => 'warning', 'secondary' => 'gray'];

    foreach (colorSurfaces() as $surface => $resolve) {
        foreach ($aliases as $alias => $role) {
            $this->assertSame(
                $resolve($role),
                $resolve($alias),
                "[{$surface}] renders the alias [{$alias}] differently from its role [{$role}]."
            );
        }
    }
});

it('keeps the literal hues distinct from the semantic roles, on every surface', function () {
    // The 2026-07 split: `blue` is literal blue, not the re-themeable brand
    // primary; `green` is not `success`; `yellow` is not `warning`. Collapsing
    // any of these back would be invisible without this.
    $distinct = ['blue' => 'primary', 'green' => 'success', 'yellow' => 'warning'];

    foreach (colorSurfaces() as $surface => $resolve) {
        if ($surface === 'alert') {
            continue;
        }

        foreach ($distinct as $literal => $role) {
            $this->assertNotSame(
                $resolve($role),
                $resolve($literal),
                "[{$surface}] collapsed the literal hue [{$literal}] onto the role [{$role}]."
            );
        }
    }
});

it('resolves every color in the canonical vocabulary to real classes', function () {
    foreach (colorSurfaces() as $surface => $resolve) {
        foreach (Color::values() as $color) {
            $this->assertNotSame('', trim($resolve($color)), "[{$surface}] resolves [{$color}] to nothing.");
        }
    }
});

it('keeps the alert surface deliberately semantic-only', function () {
    // Alerts carry meaning, not decoration: only the semantic roles (and the
    // achromatic endpoints) get their own look, and everything else falls to the
    // informational blue. That is the documented contract, pinned here so it does
    // not read as a gap in the sweeps above.
    $blue = TestColorClass::getAlertColorClasses('not-a-color');

    expect($blue)->toContain('bg-blue-50')
        ->and(TestColorClass::getAlertColorClasses('purple'))->toBe($blue)
        ->and(TestColorClass::getAlertColorClasses('info'))->toBe($blue)
        ->and(TestColorClass::getAlertColorClasses('gray'))->toBe($blue)
        // …while the roles it does own stay distinct.
        ->and(TestColorClass::getAlertColorClasses('danger'))->not->toBe($blue)
        ->and(TestColorClass::getAlertColorClasses('success'))->not->toBe($blue)
        ->and(TestColorClass::getAlertColorClasses('warning'))->not->toBe($blue);
});
