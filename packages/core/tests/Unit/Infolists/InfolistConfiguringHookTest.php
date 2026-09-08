<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Plugin\Hooks\InfolistConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Infolist;

/*
 * The read-only half of `form.configuring`.
 *
 * ADR 0030 §6 held this back for want of a consumer. Five module packages later
 * every one of them ships a resource with a detail page, all of them composed
 * inside code the application does not own — so "add a field to the users form"
 * was writable and "add a row to its detail" was not. This is that asymmetry
 * closed.
 */

class IchOrder extends Model
{
    protected $table = 'ich_orders';

    protected $guarded = [];

    public $timestamps = false;

    protected $attributes = ['id' => 1, 'reference' => 'INV-1', 'note' => 'internal'];
}

function ichAddNote(?string $for = null): void
{
    app(PluginManager::class)->hook(
        Hook::InfolistConfiguring,
        function (InfolistConfiguringPayload $payload): InfolistConfiguringPayload {
            $payload->schema = [...$payload->schema, TextEntry::make('note')];

            return $payload;
        },
        for: $for,
    );
}

it('renders an entry a hook added to a schema it does not own', function () {
    ichAddNote();

    $html = Infolist::make()
        ->record(new IchOrder)
        ->schema([TextEntry::make('reference')])
        ->toHtml();

    expect($html)->toContain('INV-1')->toContain('internal');
});

it('asks the hook once, however often the schema is read', function () {
    // `getSchema()` is read by the render and again by the action runtime
    // looking for an infolist action. A hook that ran per read would append the
    // same entry twice on the second.
    ichAddNote();

    $infolist = Infolist::make()->record(new IchOrder)->schema([TextEntry::make('reference')]);

    expect($infolist->getSchema())->toHaveCount(2)
        ->and($infolist->getSchema())->toHaveCount(2);
});

it('asks again when the schema is re-declared', function () {
    // A re-declared schema is a different infolist, so the configured copy stops
    // being an answer to it.
    ichAddNote();

    $infolist = Infolist::make()->record(new IchOrder)->schema([TextEntry::make('reference')]);

    expect($infolist->getSchema())->toHaveCount(2);

    $infolist->schema([TextEntry::make('reference'), TextEntry::make('reference')]);

    expect($infolist->getSchema())->toHaveCount(3);
});

it('scopes an infolist hook by the record model', function () {
    ichAddNote(for: IchOrder::class);

    $html = Infolist::make()
        ->record(new IchOrder)
        ->schema([TextEntry::make('reference')])
        ->toHtml();

    expect($html)->toContain('internal');
});

it('leaves an infolist over a plain array out of a model-scoped hook', function () {
    // An array record carries no model to scope by. Not a gap — there is nothing
    // for the scope to name — and running the callback anyway is the worse of
    // the two mistakes.
    ichAddNote(for: IchOrder::class);

    $html = Infolist::make()
        ->state(['reference' => 'INV-1', 'note' => 'internal'])
        ->schema([TextEntry::make('reference')])
        ->toHtml();

    expect($html)->toContain('INV-1')->not->toContain('internal');
});

it('carries the host that bound itself, so a page can be named', function () {
    $seen = null;

    app(PluginManager::class)->hook(
        Hook::InfolistConfiguring,
        function (InfolistConfiguringPayload $payload) use (&$seen): InfolistConfiguringPayload {
            $seen = $payload->hookTarget();

            return $payload;
        },
    );

    $host = new stdClass;

    $infolist = Infolist::make()->record(new IchOrder)->livewireComponent($host);

    $infolist->getSchema();

    expect($infolist->getLivewireComponent())->toBe($host)
        ->and($seen?->surface)->toBe('infolist')
        ->and($seen?->host)->toBe($host)
        ->and($seen?->model)->toBe(IchOrder::class);
});
