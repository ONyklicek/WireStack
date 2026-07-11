<?php

declare(strict_types=1);

use NyonCode\WireCore\Modals\ModalStack;

it('derives the z-index for a stack depth from the base and step', function () {
    expect(ModalStack::zIndexForDepth(0))->toBe(ModalStack::BASE_Z_INDEX)
        ->and(ModalStack::zIndexForDepth(1))->toBe(ModalStack::BASE_Z_INDEX + ModalStack::Z_INDEX_STEP)
        ->and(ModalStack::zIndexForDepth(3))->toBe(ModalStack::BASE_Z_INDEX + 3 * ModalStack::Z_INDEX_STEP);
});

it('clamps a negative depth to the base layer', function () {
    expect(ModalStack::zIndexForDepth(-5))->toBe(ModalStack::BASE_Z_INDEX);
});

it('keeps the base layer at the default modal z-50 so a single modal is unchanged', function () {
    expect(ModalStack::BASE_Z_INDEX)->toBe(50);
});
