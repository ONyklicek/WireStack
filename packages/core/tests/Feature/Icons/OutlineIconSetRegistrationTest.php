<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Icons\IconManager;

it('makes the bundled Heroicons outline set available under the outline prefix', function () {
    /** @var IconManager $manager */
    $manager = app(IconManager::class);

    // The outline variant is bundled with the framework, so `outline:name` must
    // resolve through the container-built manager even when the app ships a
    // previously published config that predates the outline set.
    expect($manager->has('outline:x-mark'))->toBeTrue();

    $svg = $manager->render('outline:x-mark', 'w-5 h-5');

    expect($svg)->toContain('viewBox="0 0 24 24"')
        ->toContain('stroke="currentColor"')
        ->toContain('stroke-width="1.5"');

    // Bare names still resolve to the solid base set.
    expect($manager->render('x-mark'))->toContain('viewBox="0 0 20 20"');
});
