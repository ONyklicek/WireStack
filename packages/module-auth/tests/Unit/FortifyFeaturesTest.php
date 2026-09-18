<?php

declare(strict_types=1);

use NyonCode\WireModuleAuth\Install\FortifyFeatures;

/*
 * The four shapes a feature takes in a published `config/fortify.php`, and the
 * ones this refuses to guess at.
 */

it('reads a feature whose options array opens and closes on one line', function () {
    $features = new FortifyFeatures("    Features::twoFactorAuthentication(['confirm' => true]),\n");

    expect($features->states(['twoFactorAuthentication']))->toBe(['twoFactorAuthentication' => true]);

    $features->set('twoFactorAuthentication', false);

    expect($features->contents())->toBe("    // Features::twoFactorAuthentication(['confirm' => true]),\n");
});

it('leaves out a block it cannot find the end of', function () {
    // Closed at a different indent, which is a file somebody reformatted. Half
    // a block commented out is a syntax error in their config.
    $features = new FortifyFeatures("    Features::passkeys([\n        'confirmPassword' => true,\n        ]),\n");

    expect($features->states(['passkeys']))->toBe([]);

    $features->set('passkeys', false);

    expect($features->contents())->toBe("    Features::passkeys([\n        'confirmPassword' => true,\n        ]),\n");
});

it('does not touch a line inside a commented block that was not commented at its indent', function () {
    $contents = "    // Features::passkeys([\n        'confirmPassword' => true,\n\n    // ]),\n";
    $features = new FortifyFeatures($contents);

    $features->set('passkeys', true);

    // The opening and closing lines come back; the stray line and the blank
    // one are left exactly as they were.
    expect($features->contents())->toBe("    Features::passkeys([\n        'confirmPassword' => true,\n\n    ]),\n");
});

it('leaves a feature that is already in the state asked for', function () {
    $features = new FortifyFeatures("    Features::registration(),\n");

    $features->set('registration', true);
    $features->set('passkeys', true);

    expect($features->contents())->toBe("    Features::registration(),\n");
});
