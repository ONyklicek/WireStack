<?php

declare(strict_types=1);

use NyonCode\WireBoost\Support\ComponentReflector;
use NyonCode\WireBoost\Support\TypeCatalog;

/**
 * Guards the AI_CODING_STANDARD "Fluent API" convention: every public fluent
 * (chainable) setter on a built-in component type carries a one-line docblock
 * summary, so describe-component-api never hands an agent a bare signature.
 *
 * The check runs through the same {@see ComponentReflector} the MCP tool uses,
 * so it stays in lockstep with the reflector's own denylist and fluent detection
 * rather than re-deriving them here.
 */
it('documents every fluent method on every built-in component type', function () {
    $catalog = new TypeCatalog;
    $reflector = new ComponentReflector;

    $undocumented = [];

    foreach ($catalog->categories() as $category) {
        foreach ($catalog->types($category) as $type) {
            foreach ($reflector->describeType($type['class'])['methods'] as $method) {
                if (($method['fluent'] ?? false) === true && ! isset($method['summary'])) {
                    $undocumented[$type['class'].'::'.$method['name']] = true;
                }
            }
        }
    }

    expect(array_keys($undocumented))->toBe(
        [],
        "These fluent setters are missing a one-line /** … */ docblock summary.\n".
        'Add one (see AI_CODING_STANDARD.md#Fluent API): '.
        implode(', ', array_keys($undocumented)),
    );
});
