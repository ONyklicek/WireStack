<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use NyonCode\WireCore\Audit\AuditEntry;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireModuleAudit\AuditModule;
use NyonCode\WireModuleAudit\Exceptions\AuditModuleException;
use NyonCode\WireModuleAudit\Pages\ListAuditEntries;
use NyonCode\WireModuleAudit\Pages\ViewAuditEntry;
use NyonCode\WireModuleAudit\Resources\AuditResource;
use NyonCode\WireModuleAudit\Support\AuditLog;
use NyonCode\WireModuleAudit\Tests\Fixtures\Invoice;

/*
 * What this module declares before anything is rendered — the shape the
 * registries, the router and the menu read.
 */

it('names its pages, its label and its menu entry', function () {
    expect(AuditResource::pages())->toBe([
        'index' => ListAuditEntries::class,
        'view' => ViewAuditEntry::class,
    ])
        ->and(AuditResource::key())->toBe('audit-log')
        ->and(AuditResource::label())->not->toBe('')
        ->and(AuditResource::pluralLabel())->not->toBe('')
        ->and(AuditResource::navigation()->getGroup())->toBe('system');
});

it('has no form and no create page, because an editable audit entry is not one', function () {
    expect(AuditResource::pages())->not->toHaveKey('create')
        ->and(class_implements(AuditResource::class))
        ->not->toContain(ProvidesResourceForm::class);
});

it('puts the configured ability in front of both pages, or neither', function () {
    // One statement: `ResourceRoutes` turns it into `can:` middleware and the
    // list's own buttons read it back off the same declaration, so a hidden
    // button and a guarded route cannot drift apart.
    expect(AuditResource::permission())->toBeNull();

    config()->set('wire-module-audit.permission', 'audit.view');

    $pages = AuditResource::pages();

    expect(AuditResource::permission())->toBe('audit.view')
        ->and($pages['index'])->toBeInstanceOf(RoutePage::class)
        ->and($pages['index']->getPermission())->toBe('audit.view')
        ->and($pages['view']->getPermission())->toBe('audit.view');

    // An empty string is a setting somebody cleared, not an ability nobody has.
    config()->set('wire-module-audit.permission', '');

    expect(AuditResource::permission())->toBeNull();
});

it('takes the model from configuration, and answers null when it is blank', function () {
    expect(AuditResource::modelClass())->toBe(AuditEntry::class);

    config()->set('wire-module-audit.model', '');

    expect(AuditResource::modelClass())->toBeNull()
        // And a filter over a model nobody configured is empty rather than a crash.
        ->and(AuditLog::recordTypes())->toBe([])
        ->and(AuditLog::actorIds())->toBe([])
        ->and(AuditLog::query())->toBeNull()
        ->and(AuditLog::available())->toBeFalse();
});

it('refuses a model that is not an audit entry, instead of showing an empty log', function () {
    // The screens read the trail through this class — its casts, its user()
    // relation, its change diff. A class that only has similar columns would
    // render as "nothing ever happened", which is the one thing an audit screen
    // must never say by mistake.
    config()->set('wire-module-audit.model', Invoice::class);

    expect(fn () => AuditLog::model())->toThrow(AuditModuleException::class, 'does not extend');
});

it('lets an application rename the menu group it ships', function () {
    config()->set('wire-module-audit.navigation.label', 'Operations');

    expect((new AuditModule)->navigation()?->getLabel())->toBe('Operations');
});

it('falls back to its own heading when the application renames nothing', function () {
    expect((new AuditModule)->navigation()?->getLabel())->not->toBe('')
        ->and((new AuditModule)->getId())->toBe('audit')
        ->and((new AuditModule)->resources())->toBe([AuditResource::class]);
});

it('reads the trail through the model core writes it with, unless told otherwise', function () {
    // Core's setting is where the docs say to put an extended entry; the
    // screens follow it rather than keeping a second answer of their own.
    config()->set('wire-module-audit.model', null);
    config()->set('wire-core.audit.model', AdAuditEntry::class);

    expect(AuditLog::model())->toBe(AdAuditEntry::class);

    config()->set('wire-module-audit.model', AuditEntry::class);

    expect(AuditLog::model())->toBe(AuditEntry::class);
});

it('hides its menu entry from somebody the configured ability refuses', function () {
    expect(AuditResource::navigation()->isVisible())->toBeTrue();

    config()->set('wire-module-audit.permission', 'audit.view');
    Gate::define('audit.view', static fn (): bool => false);

    expect(AuditResource::navigation()->isVisible())->toBeFalse()
        ->and(AuditResource::authorized())->toBeFalse();

    Gate::define('audit.view', static fn (?object $user = null): bool => true);

    expect(AuditResource::authorized())->toBeTrue();
});

it('fails closed when the ability cannot be asked', function () {
    config()->set('wire-module-audit.permission', 'audit.view');
    Gate::before(static fn () => throw new RuntimeException('no guard'));

    expect(AuditResource::authorized())->toBeFalse();
});

it('reads recording the way core\'s logger does', function (mixed $value, bool $on) {
    config()->set('wire-core.audit.enabled', $value);

    expect(AuditLog::recording())->toBe($on);
})->with([
    'true' => [true, true],
    'env 1' => ['1', true],
    'false' => [false, false],
    'env 0' => ['0', false],
]);

it('treats recording as on when core\'s switch is absent, as core does', function () {
    config()->set('wire-core.audit', []);

    expect(AuditLog::recording())->toBeTrue();
});

class AdAuditEntry extends AuditEntry {}
