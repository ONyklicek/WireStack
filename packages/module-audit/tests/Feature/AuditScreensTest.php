<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Audit\AuditEntry;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireModuleAudit\Pages\ListAuditEntries;
use NyonCode\WireModuleAudit\Pages\ViewAuditEntry;
use NyonCode\WireModuleAudit\Tests\Fixtures\Invoice;
use NyonCode\WireModuleAudit\Tests\Fixtures\InvoiceResource;
use NyonCode\WireModuleAudit\Tests\Fixtures\User;

/*
 * The two screens, rendered.
 *
 * The engine, the table and the pruning command have shipped in wire-core for
 * versions; what was missing was a way to read any of it without SQL. What is
 * asserted here is the difference between showing the rows and showing what
 * happened: a name instead of a key, a label instead of a database string, the
 * before beside the after instead of two blocks of JSON to diff by eye.
 */

beforeEach(function () {
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

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::create('invoices', function (Blueprint $table) {
        $table->id();
        $table->string('number');
    });

    config()->set('wire-core.audit.user_model', User::class);

    User::query()->create(['id' => 3, 'name' => 'Amelia']);
    Invoice::query()->create(['id' => 7, 'number' => 'INV-7']);

    AuditEntry::query()->create([
        'event' => 'updated',
        'auditable_type' => Invoice::class,
        'auditable_id' => '7',
        'user_id' => '3',
        'old_values' => ['status' => 'draft'],
        'new_values' => ['status' => 'sent'],
        'metadata' => ['ip' => '203.0.113.7', 'user_agent' => 'Firefox'],
        'created_at' => now()->subMinute(),
    ]);
});

/** The module's pages, routed the way an application routes them. */
function auditRoutes(): void
{
    Route::middleware('web')->group(fn () => Route::wireResources());
}

it('registers itself as the audit module', function () {
    expect(app(PluginManager::class)->has('audit'))->toBeTrue()
        ->and(app(ResourceRegistry::class)->all())->toHaveKey('audit-log');
});

it('says what happened, to what, and by whom', function () {
    $html = Livewire::test(ListAuditEntries::class)->assertOk()->html();

    expect($html)
        // Not the stored `updated`.
        ->toContain('Updated')
        // Not `App\Models\Invoice`, and not `7` on its own.
        ->toContain('Invoice #7')
        // Not `3`, which is the number the column used to print — in the one
        // table whose whole purpose is saying who was responsible.
        ->toContain('Amelia')
        // And what they touched, without opening the entry.
        ->toContain('status');
});

it('reads the events the module shipped not knowing', function () {
    // `bulk_action` and `cell_updated` had no label, no colour and no filter
    // option: three of the five were handled and two rendered raw.
    AuditEntry::query()->create([
        'event' => 'bulk_action',
        'auditable_type' => Invoice::class,
        'new_values' => ['action' => 'archive', 'record_ids' => [7]],
    ]);

    $html = Livewire::test(ListAuditEntries::class)->html();

    expect($html)->toContain('Bulk action')
        // A bulk action ran over a type and no single record, so the row says
        // the type rather than inventing a `#`.
        ->and($html)->toContain('>Invoice<');
});

it('reads an application\'s own event type instead of printing a translation key', function () {
    AuditEntry::query()->create([
        'event' => 'invoice_sent',
        'auditable_type' => Invoice::class,
        'auditable_id' => '7',
    ]);

    expect(Livewire::test(ListAuditEntries::class)->html())->toContain('Invoice Sent');
});

it('filters by event, record type, actor and a span of days', function () {
    $filters = Livewire::test(ListAuditEntries::class)->instance()->getTable()->getFilters();

    expect(array_map(static fn ($filter): string => $filter->getName(), $filters))
        ->toBe(['event', 'auditable_type', 'user_id', 'created_at']);
});

it('offers the people the log actually holds as the actor filter', function () {
    // Resolved when the filter is drawn rather than every time the table is
    // composed — a table rebuilds on every search keystroke.
    $filters = Livewire::test(ListAuditEntries::class)->instance()->getTable()->getFilters();

    expect($filters[2]->getOptions())->toBe(['3' => 'Amelia']);
});

it('offers no actor filter where actors cannot be named', function () {
    config()->set('wire-core.audit.user_model', 'App\\Models\\Nonexistent');

    $filters = Livewire::test(ListAuditEntries::class)->instance()->getTable()->getFilters();

    expect(array_map(static fn ($filter): string => $filter->getName(), $filters))
        ->toBe(['event', 'auditable_type', 'created_at']);
});

it('links a row to the entry, and to the record the entry is about', function () {
    // The second link is the one that changes what the log is worth: a row says
    // something happened to invoice seven, and the next thing anybody wants is
    // invoice seven.
    app(ResourceRegistry::class)->register(InvoiceResource::class);
    auditRoutes();

    $html = Livewire::test(ListAuditEntries::class)->html();

    expect($html)->toContain('audit-log/1')
        ->and($html)->toContain('invoices/7');
});

it('draws no link where nothing is routed', function () {
    // The list shipped with no row action at all, so its own entry page was
    // routed and unreachable. The fix is not a button that leads nowhere.
    $html = Livewire::test(ListAuditEntries::class)->html();

    expect($html)->not->toContain('data-testid="action-view"')
        ->and($html)->not->toContain('data-testid="action-auditedRecord"');
});

it('draws no record link for a model this application has no screen for', function () {
    auditRoutes();

    $html = Livewire::test(ListAuditEntries::class)->html();

    expect($html)->toContain('data-testid="action-view"')
        ->and($html)->not->toContain('data-testid="action-auditedRecord"');
});

it('shows the before beside the after, and where the request came from', function () {
    $html = Livewire::test(ViewAuditEntry::class, ['record' => 1])->assertOk()->html();

    expect($html)
        ->toContain('draft')
        ->toContain('sent')
        // The field that moved is named, rather than the reader diffing two
        // blocks of JSON by eye.
        ->toContain('status')
        // Promised by the docs since the module shipped, and never rendered.
        ->toContain('203.0.113.7')
        ->toContain('Firefox');
});

it('draws the diff as one table, not a card per field repeating its headings', function () {
    // What this replaced: a `RepeatableEntry` that drew a bordered card per
    // changed field, each carrying its own "Field / Old / New" labels. On an
    // update touching eight columns that is eight cards and twenty-four
    // headings, which is the work the diff exists to have already done.
    //
    // **Three fields, not one.** With a single change the two shapes are
    // indistinguishable — one card carries the headings once, exactly like one
    // table — and this test passed against the old component until the fixture
    // had more than one row to repeat.
    AuditEntry::query()->create([
        'event' => 'updated',
        'auditable_type' => Invoice::class,
        'auditable_id' => '7',
        'old_values' => ['status' => 'draft', 'total' => 100, 'note' => null],
        'new_values' => ['status' => 'sent', 'total' => 250, 'note' => 'rush'],
        'created_at' => now(),
    ]);

    $html = Livewire::test(ViewAuditEntry::class, ['record' => 2])->assertOk()->html();

    expect($html)->toContain('status')->toContain('total')->toContain('note')
        ->and(substr_count($html, __('wire-core::audit.field')))->toBe(1)
        ->and(substr_count($html, __('wire-core::audit.old_value')))->toBe(1)
        ->and(substr_count($html, __('wire-core::audit.new_value')))->toBe(1);
});

it('reads top to bottom: what happened, what moved, where from', function () {
    // Three questions, three sections, in the order a reader arrives with them.
    // Flat, the IP and the user agent sat between the actor and the diff — so
    // the thing the page exists for was below the fold on a wide change.
    $html = Livewire::test(ViewAuditEntry::class, ['record' => 1])->assertOk()->html();

    $happened = strpos($html, __('wire-module-audit::messages.what_happened'));
    $changes = strpos($html, __('wire-module-audit::messages.changes'));
    $context = strpos($html, __('wire-module-audit::messages.context'));

    expect($happened)->toBeInt()
        ->and($changes)->toBeGreaterThan($happened)
        ->and($context)->toBeGreaterThan($changes);
});

it('writes a boolean change as true and false', function () {
    // The drift that made the consolidation worth doing: core's trail printed
    // `1` here, because the value rule lived in a Blade ternary. Both surfaces
    // read ChangeSet now.
    AuditEntry::query()->create([
        'event' => 'updated',
        'auditable_type' => Invoice::class,
        'auditable_id' => '7',
        'old_values' => ['paid' => false],
        'new_values' => ['paid' => true],
        'created_at' => now(),
    ]);

    $html = Livewire::test(ViewAuditEntry::class, ['record' => 2])->assertOk()->html();

    expect($html)->toContain('true')->toContain('false');
});

it('says so on an entry where nothing moved', function () {
    // A `created` row has no before/after pair at all, and an empty table would
    // read as a page that failed to load.
    AuditEntry::query()->create([
        'event' => 'created',
        'auditable_type' => Invoice::class,
        'auditable_id' => '9',
        'created_at' => now(),
    ]);

    $html = Livewire::test(ViewAuditEntry::class, ['record' => 2])->assertOk()->html();

    expect($html)->toContain(__('wire-module-audit::messages.no_changes'));
});

it('titles the entry by what it is about', function () {
    $title = Livewire::test(ViewAuditEntry::class, ['record' => 1])->instance()->getTitle();

    expect($title)->toBe('Invoice #7 · Updated');
});

it('titles an entry that is no longer there by the resource, rather than failing', function () {
    // A pruned entry, or a link somebody kept: the heading falls back instead of
    // the page dying on a title.
    $page = new ViewAuditEntry;
    $page->record = 999;

    expect($page->getTitle())->toBe(__('wire-module-audit::messages.entry'));
});

it('keeps a title the application set itself', function () {
    $page = new class extends ViewAuditEntry
    {
        protected ?string $title = 'Ours';
    };

    expect($page->getTitle())->toBe('Ours');
});

it('says an empty log is empty, rather than looking broken', function () {
    AuditEntry::query()->delete();

    expect(Livewire::test(ListAuditEntries::class)->html())->toContain('Nothing recorded yet');
});
