<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Services\TableQueryCacheKey;

class TqckPost extends Model
{
    protected $table = 'tqck_posts';
}

$query = fn () => TqckPost::query();

it('namespaces a generated key by the SQL and bindings', function () use ($query) {
    $builder = new TableQueryCacheKey;

    $a = $builder->build($builder->namespaceFor($query()), []);
    $b = $builder->build($builder->namespaceFor($query()->where('id', 1)), []);

    expect($a)->toStartWith('wire_table:')
        ->and($a)->not->toBe($b);
});

it('separates entries that differ only in state the SQL cannot express', function () use ($query) {
    $builder = new TableQueryCacheKey;

    $page1 = $builder->build($builder->namespaceFor($query()), ['perPage' => 10, 'page' => '1']);
    $page2 = $builder->build($builder->namespaceFor($query()), ['perPage' => 10, 'page' => '2']);
    $bigger = $builder->build($builder->namespaceFor($query()), ['perPage' => 25, 'page' => '1']);

    expect([$page1, $page2, $bigger])->toHaveCount(3)
        ->and($page1)->not->toBe($page2)
        ->and($page1)->not->toBe($bigger);
});

it('treats a caller-supplied key as a namespace, not as the whole identity', function () {
    $builder = new TableQueryCacheKey;

    $sortAsc = $builder->build('reports', ['sort' => ['column' => 'name', 'direction' => 'asc']]);
    $sortDesc = $builder->build('reports', ['sort' => ['column' => 'name', 'direction' => 'desc']]);

    expect($sortAsc)->toStartWith('reports:')
        ->and($sortAsc)->not->toBe($sortDesc);
});

it('normalises key order and numeric strings so equivalent views share one entry', function () use ($query) {
    $builder = new TableQueryCacheKey;

    // The select posts "25"; the mount default is 25. Same view, same entry.
    $fromSelect = $builder->build($builder->namespaceFor($query()), ['page' => '1', 'perPage' => '25']);
    $fromMount = $builder->build($builder->namespaceFor($query()), ['perPage' => 25, 'page' => 1]);

    expect($fromSelect)->toBe($fromMount);
});

it('normalises nested state arrays', function () use ($query) {
    $builder = new TableQueryCacheKey;

    $a = $builder->build($builder->namespaceFor($query()), ['filters' => ['status' => ['value' => '2'], 'kind' => ['value' => 'a']]]);
    $b = $builder->build($builder->namespaceFor($query()), ['filters' => ['kind' => ['value' => 'a'], 'status' => ['value' => 2]]]);
    $c = $builder->build($builder->namespaceFor($query()), ['filters' => ['kind' => ['value' => 'a'], 'status' => ['value' => 3]]]);

    expect($a)->toBe($b)->and($a)->not->toBe($c);
});

it('keeps a boolean distinct from the number it would stringify to', function () use ($query) {
    $builder = new TableQueryCacheKey;

    $true = $builder->build($builder->namespaceFor($query()), ['flag' => true]);
    $one = $builder->build($builder->namespaceFor($query()), ['flag' => 1]);

    expect($true)->not->toBe($one);
});
