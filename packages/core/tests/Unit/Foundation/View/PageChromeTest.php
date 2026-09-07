<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\View\PageChrome;

/*
 * Where a package puts a view the shell has to render once per page.
 *
 * It exists because of a dependency direction, not because of a feature: the
 * shell sits at the top of the graph and cannot name a module package's views,
 * and a module cannot reach into the shell's layout. So neither names the other.
 */

it('keeps what was registered, in the order it was registered', function () {
    $chrome = new PageChrome;

    $chrome->add('a::modal');
    $chrome->add('b::modal');

    expect($chrome->views())->toBe(['a::modal', 'b::modal'])
        ->and($chrome->has('a::modal'))->toBeTrue()
        ->and($chrome->has('c::modal'))->toBeFalse();
});

it('registers one view once, however many times it is asked', function () {
    // A provider runs twice more often than anyone expects — a package required
    // by two others, a test that boots the app again — and two copies of one
    // modal in a document means two things listening for one event.
    $chrome = new PageChrome;

    $chrome->add('a::modal');
    $chrome->add('a::modal');

    expect($chrome->views())->toBe(['a::modal']);
});

it('ignores an empty name rather than rendering nothing at all', function () {
    $chrome = new PageChrome;

    $chrome->add('');

    expect($chrome->views())->toBe([]);
});

it('is one instance for the whole request', function () {
    // The contract, not an optimisation: two instances would each hold half the
    // chrome and the layout would render whichever it happened to resolve.
    expect(app(PageChrome::class))->toBe(app(PageChrome::class));
});

it('keeps the regions apart', function () {
    // A modal only has to exist, so it goes at the end of the document. A team
    // switcher has to be seen, so it goes in the top bar. A way out belongs to
    // the person, so it goes in their menu. One registry answers all three,
    // because it is one dependency problem with three answers to "where".
    $chrome = new PageChrome;

    $chrome->add('a::modal');
    $chrome->add('a::switcher', PageChrome::TOPBAR);
    $chrome->add('a::sign-out', PageChrome::USER_MENU);

    expect($chrome->views())->toBe(['a::modal'])
        ->and($chrome->views(PageChrome::TOPBAR))->toBe(['a::switcher'])
        ->and($chrome->views(PageChrome::USER_MENU))->toBe(['a::sign-out'])
        ->and($chrome->has('a::switcher'))->toBeFalse()
        ->and($chrome->has('a::switcher', PageChrome::TOPBAR))->toBeTrue()
        ->and($chrome->has('a::sign-out', PageChrome::TOPBAR))->toBeFalse()
        ->and($chrome->has('a::sign-out', PageChrome::USER_MENU))->toBeTrue();
});

it('deduplicates within a region and not across them', function () {
    // A provider that runs twice must not put two copies of a modal in the
    // document — two modals listening for one event both answer it. The same
    // view deliberately placed in both regions is a different question.
    $chrome = new PageChrome;

    $chrome->add('a::thing');
    $chrome->add('a::thing');
    $chrome->add('a::thing', PageChrome::TOPBAR);

    expect($chrome->views())->toBe(['a::thing'])
        ->and($chrome->views(PageChrome::TOPBAR))->toBe(['a::thing']);
});

it('puts a lower sort first, whatever order the providers booted in', function () {
    // Provider order is composer's discovery order, not a contract — and the
    // user menu is the first region two packages contribute to. Registered
    // last, sorted first.
    $chrome = new PageChrome;

    $chrome->add('auth::sign-out', PageChrome::USER_MENU, 100);
    $chrome->add('users::profile', PageChrome::USER_MENU, 10);

    expect($chrome->views(PageChrome::USER_MENU))->toBe(['users::profile', 'auth::sign-out']);
});

it('keeps equal sorts in the order they were registered', function () {
    // The fallback, and the reason a region with one contributor never has to
    // think about sorting: nothing moves unless something asked it to.
    $chrome = new PageChrome;

    $chrome->add('a::first');
    $chrome->add('a::second');
    $chrome->add('a::third');

    expect($chrome->views())->toBe(['a::first', 'a::second', 'a::third']);
});

it('answers with nothing for a region no one has registered into', function () {
    expect((new PageChrome)->views('nowhere'))->toBe([]);
});
