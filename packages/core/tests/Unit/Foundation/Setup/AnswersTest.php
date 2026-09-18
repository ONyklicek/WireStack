<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Setup\Answers;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;

/*
 * A question a setup step asks again while the answer is not one it can use.
 */

/**
 * @param  array<int, string>  $warned
 */
function answersConsole(array &$warned, bool $interactive = true): SetupConsole
{
    $console = Mockery::mock(SetupConsole::class);
    $console->shouldReceive('isInteractive')->andReturn($interactive);
    $console->shouldReceive('warn')->andReturnUsing(function (string $message) use (&$warned): void {
        $warned[] = $message;
    });

    return $console;
}

it('asks again while the answer has a problem, and says what the problem is', function () {
    $warned = [];
    $given = ['bad', ' good ', 'good'];

    $answer = (new Answers(answersConsole($warned)))->until(
        function () use (&$given): string {
            return (string) array_shift($given);
        },
        static fn (string $answer): ?string => $answer === 'good' ? null : "Not good: {$answer}",
    );

    expect($answer)->toBe('good')
        ->and($warned)->toBe(['Not good: bad.', 'Not good:  good .']);
});

it('gives up after three answers it cannot use', function () {
    $warned = [];
    $asked = 0;

    $answer = (new Answers(answersConsole($warned)))->until(
        function () use (&$asked): string {
            $asked++;

            return 'bad';
        },
        static fn (): string => 'No',
    );

    expect($answer)->toBeNull()
        ->and($asked)->toBe(3)
        ->and($warned)->toHaveCount(3);
});

it('asks once where nobody is there to give a different answer', function () {
    $warned = [];
    $asked = 0;

    $answer = (new Answers(answersConsole($warned, interactive: false)))->until(
        function () use (&$asked): string {
            $asked++;

            return 'default';
        },
        static fn (): string => 'No',
    );

    expect($answer)->toBeNull()
        ->and($asked)->toBe(1);
});
