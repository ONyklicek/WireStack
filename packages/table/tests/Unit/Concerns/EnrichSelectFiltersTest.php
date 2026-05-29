<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\TableQueryService;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;

// ─── Test Enums ──────────────────────────────────────────────────────────────

enum EsfStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Pending = 'pending';
}

enum EsfPriority
{
    case Low;
    case High;
}

// ─── Test Model ──────────────────────────────────────────────────────────────

class EsfTicket extends Model
{
    protected $table = 'esf_tickets';

    protected $guarded = [];

    protected $casts = [
        'status' => EsfStatus::class,
        'priority' => EsfPriority::class,
        'count' => 'int',
    ];
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::create('esf_tickets', function (Blueprint $table) {
        $table->id();
        $table->string('status');
        $table->string('priority');
        $table->integer('count')->default(0);
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('esf_tickets');
});

// ─── Tests ───────────────────────────────────────────────────────────────────

it('auto-populates SelectFilter options from a backed enum cast', function () {
    $filter = SelectFilter::make('status');

    $table = Table::make()
        ->model(EsfTicket::class)
        ->columns([Column::make('status')])
        ->filters([$filter]);

    $service = new TableQueryService;
    $service->buildQuery(
        baseQuery: EsfTicket::query(),
        table: $table,
    );

    expect($filter->getOptions())->toBe([
        'active' => 'Active',
        'inactive' => 'Inactive',
        'pending' => 'Pending',
    ]);
});

it('uses case name as both key and label for unit enums', function () {
    $filter = SelectFilter::make('priority');

    $table = Table::make()
        ->model(EsfTicket::class)
        ->columns([Column::make('priority')])
        ->filters([$filter]);

    $service = new TableQueryService;
    $service->buildQuery(
        baseQuery: EsfTicket::query(),
        table: $table,
    );

    expect($filter->getOptions())->toBe([
        'Low' => 'Low',
        'High' => 'High',
    ]);
});

it('does not overwrite explicit options on a SelectFilter', function () {
    $filter = SelectFilter::make('status')->options([
        'active' => 'Custom Active Label',
    ]);

    $table = Table::make()
        ->model(EsfTicket::class)
        ->columns([Column::make('status')])
        ->filters([$filter]);

    $service = new TableQueryService;
    $service->buildQuery(
        baseQuery: EsfTicket::query(),
        table: $table,
    );

    expect($filter->getOptions())->toBe([
        'active' => 'Custom Active Label',
    ]);
});

it('leaves options empty when the cast is not an enum', function () {
    $filter = SelectFilter::make('count');

    $table = Table::make()
        ->model(EsfTicket::class)
        ->columns([Column::make('count')])
        ->filters([$filter]);

    $service = new TableQueryService;
    $service->buildQuery(
        baseQuery: EsfTicket::query(),
        table: $table,
    );

    expect($filter->getOptions())->toBe([]);
});

it('leaves options empty when the column has no cast', function () {
    $filter = SelectFilter::make('id');

    $table = Table::make()
        ->model(EsfTicket::class)
        ->columns([Column::make('id')])
        ->filters([$filter]);

    $service = new TableQueryService;
    $service->buildQuery(
        baseQuery: EsfTicket::query(),
        table: $table,
    );

    expect($filter->getOptions())->toBe([]);
});
