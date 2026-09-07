<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Audit\AuditEntry;
use NyonCode\WireModuleAudit\Support\Actors;
use NyonCode\WireModuleAudit\Support\AuditLog;
use NyonCode\WireModuleAudit\Tests\Fixtures\Invoice;
use NyonCode\WireModuleAudit\Tests\Fixtures\UnreachableAuditEntry;
use NyonCode\WireModuleAudit\Tests\Fixtures\User;

/*
 * The log asked about itself, and the actor behind a key.
 *
 * Two things every screen here needs before it draws anything, and both of them
 * used to be assumed. The filters ran their queries while the table was being
 * *composed*, so an installation that had this package and not core's migration
 * met a QueryException instead of a screen — and the actor column printed
 * `user_id`, a number to go and look up in the one table whose whole job is
 * saying who was responsible.
 */

function auditLogTable(): void
{
    Schema::create('audit_logs', function (Blueprint $table) {
        $table->id();
        $table->string('event');
        $table->string('auditable_type');
        $table->string('auditable_id')->nullable();
        $table->string('user_id')->nullable();
        $table->json('old_values')->nullable();
        $table->json('new_values')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamp('created_at')->useCurrent();
    });
}

function auditUsersTable(): void
{
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
    });

    config()->set('wire-core.audit.user_model', User::class);
}

function auditEntry(array $attributes = []): AuditEntry
{
    return AuditEntry::query()->create(array_merge([
        'event' => 'updated',
        'auditable_type' => Invoice::class,
        'auditable_id' => '7',
        'old_values' => ['status' => 'draft'],
        'new_values' => ['status' => 'sent'],
    ], $attributes));
}

afterEach(function () {
    Relation::morphMap([], false);
});

it('answers "nothing to read" when core\'s migration has not been run', function () {
    // wire-core publishes the audit_logs migration on demand, so this is an
    // ordinary state and not a broken installation: the module can be required,
    // registered and routed before the table exists.
    expect(AuditLog::available())->toBeFalse()
        ->and(AuditLog::query())->toBeNull()
        ->and(AuditLog::recordTypes())->toBe([])
        ->and(AuditLog::actorIds())->toBe([]);
});

it('builds the record-type filter from what the log holds', function () {
    // Not from a list somebody maintains: a filter over types nobody audited is
    // a filter that only ever returns nothing.
    auditLogTable();

    auditEntry();
    auditEntry(['auditable_id' => '8']);
    auditEntry(['auditable_type' => 'App\\Models\\Order', 'auditable_id' => '1']);

    expect(AuditLog::recordTypes())->toBe([
        'App\\Models\\Order' => 'Order',
        Invoice::class => 'Invoice',
    ]);
});

it('labels a morph alias in the filter, and keeps the stored value as the key', function () {
    // The key has to stay exactly what is stored, because that is what the
    // filter compares against.
    auditLogTable();
    Relation::morphMap(['invoice' => Invoice::class]);

    auditEntry(['auditable_type' => 'invoice']);

    expect(AuditLog::recordTypes())->toBe(['invoice' => 'Invoice']);
});

it('lists the actors the log holds, and no more than a select can hold', function () {
    auditLogTable();

    auditEntry(['user_id' => '3']);
    auditEntry(['user_id' => '3']);
    auditEntry(['user_id' => '4']);
    auditEntry(['user_id' => null]);

    expect(AuditLog::actorIds())->toBe(['3', '4'])
        ->and(AuditLog::actorIds(1))->toBe(['3']);
});

it('names the actor, and says which kind of "nobody" the empty ones are', function () {
    auditLogTable();
    auditUsersTable();

    User::query()->create(['id' => 3, 'name' => 'Amelia', 'email' => 'a@example.com']);
    User::query()->create(['id' => 5, 'name' => null, 'email' => 'only@example.com']);

    // Written by a job, a seeder or a console command: AuditLogger records those
    // on purpose rather than dropping the entry, so the screen says so.
    expect(Actors::label(auditEntry(['user_id' => null])))->toBe(__('wire-core::audit.system'))
        ->and(Actors::label(auditEntry(['user_id' => '3'])))->toBe('Amelia')
        // The first configured attribute they actually have.
        ->and(Actors::label(auditEntry(['user_id' => '5'])))->toBe('only@example.com')
        // Gone since — and then the key is the only thing left, so it stays
        // visible instead of the row pretending nobody did it.
        ->and(Actors::label(auditEntry(['user_id' => '99'])))
        ->toBe(__('wire-core::audit.unknown_user').' #99');
});

it('falls back to the key when a user has none of the configured attributes', function () {
    auditLogTable();
    auditUsersTable();

    User::query()->create(['id' => 8]);

    expect(Actors::label(auditEntry(['user_id' => '8'])))->toBe('#8');
});

it('reads the name off whichever attribute this application keeps it in', function () {
    // `name` is a convention, not a contract.
    auditUsersTable();
    config()->set('wire-module-audit.actor.attributes', ['email']);

    $user = new User(['name' => 'Amelia', 'email' => 'a@example.com']);

    expect(Actors::nameOf($user))->toBe('a@example.com');
});

it('cannot name actors at all where the configured user model is not there', function () {
    // Core's default points at App\Models\User, which a package installation may
    // simply not have — and a belongsTo over a missing class fatals rather than
    // returning nothing, so this decides both the eager load and the filter.
    auditLogTable();

    config()->set('wire-core.audit.user_model', 'App\\Models\\Nonexistent');

    expect(Actors::available())->toBeFalse()
        ->and(Actors::userModel())->toBeNull()
        ->and(Actors::options())->toBe([])
        ->and(Actors::label(auditEntry(['user_id' => '3'])))
        ->toBe(__('wire-core::audit.unknown_user').' #3');
});

it('resolves every actor in the filter in one query, keeping the ones who are gone', function () {
    auditLogTable();
    auditUsersTable();

    User::query()->create(['id' => 3, 'name' => 'Amelia']);

    auditEntry(['user_id' => '3']);
    auditEntry(['user_id' => '99']);

    expect(Actors::options())->toBe([
        '3' => 'Amelia',
        // Filtering by "the account that was deleted" is a question this screen
        // should still be able to answer.
        '99' => __('wire-core::audit.unknown_user').' #99',
    ]);
});

it('offers no actors when the log holds none', function () {
    auditLogTable();
    auditUsersTable();

    expect(Actors::options())->toBe([]);
});

it('lists keys rather than failing when the user table is not there either', function () {
    auditLogTable();
    config()->set('wire-core.audit.user_model', User::class);

    auditEntry(['user_id' => '3']);

    expect(Actors::options())->toBe(['3' => __('wire-core::audit.unknown_user').' #3']);
});

it('answers "nothing to read" for a database it cannot reach at all', function () {
    // No connection, no database, a table this user may not describe: all of
    // them mean the same thing here, and none is worth a stack trace on a screen
    // that was only drawing a filter.
    config()->set('wire-module-audit.model', UnreachableAuditEntry::class);

    expect(AuditLog::available())->toBeFalse()
        ->and(AuditLog::recordTypes())->toBe([]);
});
