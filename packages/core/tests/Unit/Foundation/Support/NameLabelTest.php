<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Exceptions\LabelStyleException;
use NyonCode\WireCore\Foundation\Enums\LabelStyle;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Foundation\Support\NameLabel;

/*
 * The one owner of "name → label": `wire-core.labels.from_name`.
 */

afterEach(fn () => NameLabel::using(null));

it('humanises an identifier by the configured style', function (string|LabelStyle $style, string $name, string $label) {
    config()->set('wire-core.labels.from_name', $style);

    expect(NameLabel::for($name))->toBe($label);
})->with([
    'headline' => ['headline', 'plan_period', 'Plan Period'],
    'headline from camelCase' => ['headline', 'withTransport', 'With Transport'],
    'sentence' => ['sentence', 'plan_period', 'Plan period'],
    'sentence from camelCase' => ['sentence', 'withTransport', 'With transport'],
    'name' => ['name', 'plan_period', 'plan_period'],
    'enum case' => [LabelStyle::Sentence, 'first_name', 'First name'],
]);

it('keeps a name that is already text, under every style', function (string $style) {
    config()->set('wire-core.labels.from_name', $style);

    expect(NameLabel::for('Pro montážníky'))->toBe('Pro montážníky')
        ->and(NameLabel::for('doprava s technikem'))->toBe('Doprava s technikem');
})->with(LabelStyle::values());

it('defaults to headline when the style is unset', function (mixed $style) {
    config()->set('wire-core.labels.from_name', $style);

    expect(NameLabel::for('plan_period'))->toBe('Plan Period');
})->with(['null' => null, 'empty' => '']);

it('refuses an unknown style instead of guessing', function () {
    config()->set('wire-core.labels.from_name', 'sentense');

    NameLabel::for('plan_period');
})->throws(LabelStyleException::class, 'Unknown label style [sentense]');

it('lets an invokable class decide every label', function () {
    config()->set('wire-core.labels.from_name', UpperCaseLabels::class);

    expect(NameLabel::for('plan_period'))->toBe('PLAN_PERIOD')
        ->and(NameLabel::for('Pro montážníky'))->toBe('PRO MONTÁŽNÍKY');
});

it('lets a closure set at runtime win over the config', function () {
    config()->set('wire-core.labels.from_name', 'headline');
    NameLabel::using(fn (string $name): string => "[{$name}]");

    expect(NameLabel::for('plan_period'))->toBe('[plan_period]');

    NameLabel::using(null);

    expect(NameLabel::for('plan_period'))->toBe('Plan Period');
});

it('is what a section and an action fall back to', function () {
    config()->set('wire-core.labels.from_name', 'sentence');

    expect(Section::make('Pro montážníky')->getLabel())->toBe('Pro montážníky')
        ->and(Section::make('plan_rates')->getLabel())->toBe('Plan rates')
        ->and(Action::make('sendReminder')->getLabel())->toBe('Send reminder')
        ->and(Section::make('plan_rates')->label('Taxy')->getLabel())->toBe('Taxy');
});

final class UpperCaseLabels
{
    public function __invoke(string $name): string
    {
        return mb_strtoupper($name);
    }
}
