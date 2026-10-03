<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Query\Search\SearchConfig;
use NyonCode\WireCore\Core\Query\Search\WordSearch;

class WsPerson extends Model
{
    protected $table = 'ws_people';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function () {
    Schema::create('ws_people', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('city');
    });

    WsPerson::create(['name' => 'Jan Novak', 'city' => 'Praha']);
    WsPerson::create(['name' => 'Eva Novak', 'city' => 'Brno']);
    WsPerson::create(['name' => 'Petr 100%', 'city' => 'Praha']);
});

afterEach(function () {
    Schema::dropIfExists('ws_people');
});

/** @return array<int, string> */
function wsNames(string $term, array $columns = ['name', 'city'], ?SearchConfig $config = null): array
{
    $query = WsPerson::query()->orderBy('id');
    app(WordSearch::class)->apply($query, $columns, $term, $config);

    return $query->pluck('name')->all();
}

it('needs every word, each in any of the columns', function (string $term, array $names) {
    expect(wsNames($term))->toBe($names);
})->with([
    'words across columns' => ['praha novak', ['Jan Novak']],
    'any order' => ['novak praha', ['Jan Novak']],
    'one word' => ['novak', ['Jan Novak', 'Eva Novak']],
    'a word nobody has' => ['novak ostrava', []],
]);

it('changes nothing for a blank term or no columns', function () {
    expect(wsNames('   '))->toHaveCount(3)
        ->and(wsNames('novak', []))->toHaveCount(3);
});

it('escapes LIKE wildcards in a word', function () {
    expect(wsNames('100%'))->toBe(['Petr 100%']);
});

it('keeps the term whole when asked to be literal', function () {
    expect(wsNames('novak praha', config: SearchConfig::make()->literal()))->toBe([]);
});

it('searches a comparison as the text that was typed', function () {
    expect(wsNames('>100', config: SearchConfig::make()->ranges()))->toBe([]);
});

it('stays inside whatever the caller already constrained', function () {
    $query = WsPerson::query()->where('city', 'Brno');
    app(WordSearch::class)->apply($query, ['name', 'city'], 'novak');

    expect($query->pluck('name')->all())->toBe(['Eva Novak']);
});
