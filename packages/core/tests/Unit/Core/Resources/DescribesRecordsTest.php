<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;

/*
 * The four answers a resource does not have to write.
 *
 * They matter more than the lines they save: the key is what the registry routes
 * on and the label is what a menu shows, so deriving them from two different
 * places is how they drift apart. Both come off modelClass() here.
 */
class DrOrderLine extends Model
{
    protected $guarded = [];
}

class DrPerson extends Model
{
    protected $guarded = [];
}

class DrOrderLineResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return DrOrderLine::class;
    }
}

class DrPersonResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return DrPerson::class;
    }
}

/** No model: a V2.0 DataSource-backed resource still needs a sane menu entry. */
class DrRevenueReportResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }
}

class DrCustomResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return DrOrderLine::class;
    }

    public static function pluralLabel(): string
    {
        return 'Line items';
    }
}

it('derives the key, label and plural from the model', function () {
    // `Dr` is this file's fixture prefix and rides along in the derivation,
    // which is itself the point: every answer comes off the model's class name,
    // so they cannot disagree about what the entity is called.
    expect(DrOrderLineResource::key())->toBe('dr-order-lines')
        ->and(DrOrderLineResource::label())->toBe('Dr Order Line')
        ->and(DrOrderLineResource::pluralLabel())->toBe('Dr Order Lines');
});

it('pluralises irregular nouns through the inflector', function () {
    // "Persons" would be the giveaway that the plural is a naive label.'s'.
    expect(DrPersonResource::pluralLabel())->toBe('Dr People')
        ->and(DrPersonResource::key())->toBe('dr-people');
});

it('falls back to the resource name when there is no model', function () {
    // Without this a DataSource-backed resource has to spell out all four
    // answers just to appear in a menu.
    expect(DrRevenueReportResource::key())->toBe('dr-revenue-reports')
        ->and(DrRevenueReportResource::label())->toBe('Dr Revenue Report')
        ->and(DrRevenueReportResource::pluralLabel())->toBe('Dr Revenue Reports');
});

it('lets a resource override one answer without restating the rest', function () {
    expect(DrCustomResource::pluralLabel())->toBe('Line items')
        ->and(DrCustomResource::label())->toBe('Dr Order Line')
        ->and(DrCustomResource::key())->toBe('dr-order-lines');
});
