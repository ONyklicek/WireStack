<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\Support\MenuDividers;

/**
 * Spell a menu as a string: a letter is an item, `|` a divider.
 *
 * @return array<int, Action>
 */
function menu(string $spec): array
{
    return array_map(
        fn (string $c): Action => $c === '|' ? Action::divider() : Action::make($c),
        str_split($spec),
    );
}

/** @param  array<int, Action>  $items */
function spell(array $items): string
{
    return implode('', array_map(fn (Action $a): string => $a->isDivider() ? '|' : $a->getName(), $items));
}

it('keeps a divider only with an item on each side', function (string $menu, string $expected) {
    expect(spell(MenuDividers::clean(menu($menu))))->toBe($expected);
})->with([
    'untouched' => ['a|b|c', 'a|b|c'],
    'leading' => ['|a|b', 'a|b'],
    'trailing' => ['a|b|', 'a|b'],
    'run of dividers' => ['a|||b', 'a|b'],
    'everything at once' => ['||a||b||', 'a|b'],
    'dividers only' => ['|||', ''],
    'empty' => ['', ''],
    'no dividers' => ['abc', 'abc'],
]);

it('keeps the declared divider instance rather than a new one', function () {
    $divider = Action::divider();
    $items = [Action::make('a'), $divider, Action::make('b')];

    expect(MenuDividers::clean($items)[1])->toBe($divider);
});
