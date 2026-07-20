<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NyonCode\WireCore\Core\Hydration\CastResolver;
use NyonCode\WireCore\Core\Hydration\MutationPipeline;
use NyonCode\WireCore\Core\Hydration\ValueTransformer;

// =============================================================================
// ValueTransformer - transform()
// =============================================================================

it('transforms value to string cast', function () {
    $transformer = new ValueTransformer;

    expect($transformer->transform(123, 'string'))->toBe('123')
        ->and($transformer->transform(45.6, 'string'))->toBe('45.6');
});

it('transforms value to int cast', function () {
    $transformer = new ValueTransformer;

    expect($transformer->transform('42', 'int'))->toBe(42)
        ->and($transformer->transform(3.7, 'int'))->toBe(3);
});

it('transforms value to float cast', function () {
    $transformer = new ValueTransformer;

    expect($transformer->transform('3.14', 'float'))->toBe(3.14)
        ->and($transformer->transform(5, 'float'))->toBe(5.0);
});

it('transforms value to bool cast', function () {
    $transformer = new ValueTransformer;

    expect($transformer->transform(1, 'bool'))->toBeTrue()
        ->and($transformer->transform(0, 'bool'))->toBeFalse()
        ->and($transformer->transform('', 'bool'))->toBeFalse();
});

it('transforms Eloquent cast aliases', function () {
    $transformer = new ValueTransformer;

    expect($transformer->transform(1, 'boolean'))->toBeTrue()
        ->and($transformer->transform('42', 'integer'))->toBe(42)
        ->and($transformer->transform('3.14', 'double'))->toBe(3.14)
        ->and($transformer->transform('2024-06-15', 'datetime:Y-m-d'))->toBe('2024-06-15');
});

it('transforms value to array cast from json string', function () {
    $transformer = new ValueTransformer;

    $result = $transformer->transform('{"foo":"bar"}', 'array');

    expect($result)->toBe(['foo' => 'bar']);
});

it('transforms value to array cast from non-string', function () {
    $transformer = new ValueTransformer;

    $result = $transformer->transform(['a', 'b'], 'array');

    expect($result)->toBe(['a', 'b']);
});

it('transforms value to json cast from json string', function () {
    $transformer = new ValueTransformer;

    $result = $transformer->transform('["x","y"]', 'json');

    expect($result)->toBe(['x', 'y']);
});

it('transforms Carbon value to datetime cast', function () {
    $transformer = new ValueTransformer;
    $carbon = Carbon::parse('2024-06-15 10:30:00');

    $result = $transformer->transform($carbon, 'datetime');

    expect($result)->toBe($carbon->toISOString());
});

it('transforms non-Carbon value to datetime cast as string', function () {
    $transformer = new ValueTransformer;

    $result = $transformer->transform('2024-06-15', 'datetime');

    expect($result)->toBe('2024-06-15');
});

it('transforms Carbon value to date cast', function () {
    $transformer = new ValueTransformer;
    $carbon = Carbon::parse('2024-06-15');

    $result = $transformer->transform($carbon, 'date');

    expect($result)->toBe($carbon->toISOString());
});

it('transforms Carbon value to timestamp cast', function () {
    $transformer = new ValueTransformer;
    $carbon = Carbon::parse('2024-06-15 10:30:00');

    $result = $transformer->transform($carbon, 'timestamp');

    expect($result)->toBe($carbon->getTimestamp());
});

it('transforms non-Carbon value to timestamp cast as int', function () {
    $transformer = new ValueTransformer;

    $result = $transformer->transform(1718450000, 'timestamp');

    expect($result)->toBe(1718450000);
});

it('transforms Collection value to collection cast', function () {
    $transformer = new ValueTransformer;
    $collection = Collection::make([1, 2, 3]);

    $result = $transformer->transform($collection, 'collection');

    expect($result)->toBe([1, 2, 3]);
});

it('transforms non-Collection value to collection cast as array', function () {
    $transformer = new ValueTransformer;

    $result = $transformer->transform(['a', 'b'], 'collection');

    expect($result)->toBe(['a', 'b']);
});

it('transforms null to null for all cast types', function () {
    $transformer = new ValueTransformer;

    $casts = ['string', 'int', 'float', 'bool', 'array', 'json', 'datetime', 'date', 'timestamp', 'collection'];

    foreach ($casts as $cast) {
        expect($transformer->transform(null, $cast))->toBeNull("Failed for cast: {$cast}");
    }
});

// =============================================================================
// ValueTransformer - reverseTransform()
// =============================================================================

it('leaves an array value intact for an array cast (Eloquent encodes it once)', function () {
    // Pre-encoding to a JSON string here caused Model::setAttribute()'s 'array'
    // cast to encode a second time, persisting a double-encoded column.
    $transformer = new ValueTransformer;

    $result = $transformer->reverseTransform(['foo' => 'bar'], 'array');

    expect($result)->toBe(['foo' => 'bar']);
});

it('leaves an array value intact for a json cast (Eloquent encodes it once)', function () {
    $transformer = new ValueTransformer;

    $result = $transformer->reverseTransform(['x', 'y'], 'json');

    expect($result)->toBe(['x', 'y']);
});

it('reverse transforms datetime string to Carbon instance', function () {
    $transformer = new ValueTransformer;

    $result = $transformer->reverseTransform('2024-06-15 10:30:00', 'datetime');

    expect($result)->toBeInstanceOf(Carbon::class)
        ->and($result->toDateTimeString())->toBe('2024-06-15 10:30:00');
});

it('reverse transforms date string to Carbon instance', function () {
    $transformer = new ValueTransformer;

    $result = $transformer->reverseTransform('2024-06-15', 'date');

    expect($result)->toBeInstanceOf(Carbon::class)
        ->and($result->toDateString())->toBe('2024-06-15');
});

it('reverse transforms timestamp to Carbon instance', function () {
    $transformer = new ValueTransformer;
    $timestamp = 1718450000;

    $result = $transformer->reverseTransform($timestamp, 'timestamp');

    expect($result)->toBeInstanceOf(Carbon::class)
        ->and($result->getTimestamp())->toBe($timestamp);
});

it('reverse transforms array to Collection with collection cast', function () {
    $transformer = new ValueTransformer;

    $result = $transformer->reverseTransform([1, 2, 3], 'collection');

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->all())->toBe([1, 2, 3]);
});

// =============================================================================
// CastResolver
// =============================================================================

it('resolves a single cast for a model attribute', function () {
    $model = new class extends Model
    {
        protected $casts = ['is_active' => 'boolean', 'metadata' => 'array', 'amount' => 'float'];
    };

    $resolver = new CastResolver;

    expect($resolver->resolve(get_class($model), 'is_active'))->toBe('boolean')
        ->and($resolver->resolve(get_class($model), 'metadata'))->toBe('array')
        ->and($resolver->resolve(get_class($model), 'amount'))->toBe('float');
});

it('returns null for non-existent attribute cast', function () {
    $model = new class extends Model
    {
        protected $casts = ['is_active' => 'boolean'];
    };

    $resolver = new CastResolver;

    expect($resolver->resolve(get_class($model), 'non_existent'))->toBeNull();
});

it('resolves all casts for a model', function () {
    $model = new class extends Model
    {
        protected $casts = ['is_active' => 'boolean', 'metadata' => 'array', 'amount' => 'float'];
    };

    $resolver = new CastResolver;
    $casts = $resolver->resolveAll(get_class($model));

    expect($casts)->toHaveKey('is_active', 'boolean')
        ->toHaveKey('metadata', 'array')
        ->toHaveKey('amount', 'float');
});

it('resolves protected casts method definitions when getCasts omits them', function () {
    $model = new class extends Model
    {
        public function getCasts()
        {
            return [];
        }

        /**
         * @return array<string, string>
         */
        protected function casts(): array
        {
            return ['is_active' => 'boolean'];
        }
    };

    $resolver = new CastResolver;

    expect($resolver->resolve(get_class($model), 'is_active'))->toBe('boolean');
});

it('checks if a model has a cast for an attribute', function () {
    $model = new class extends Model
    {
        protected $casts = ['is_active' => 'boolean', 'metadata' => 'array'];
    };

    $resolver = new CastResolver;

    expect($resolver->hasCast(get_class($model), 'is_active'))->toBeTrue()
        ->and($resolver->hasCast(get_class($model), 'metadata'))->toBeTrue()
        ->and($resolver->hasCast(get_class($model), 'unknown'))->toBeFalse();
});

// =============================================================================
// MutationPipeline
// =============================================================================

it('applies a single before mutation', function () {
    $pipeline = new MutationPipeline;
    $pipeline->before(fn (mixed $value, string $attribute) => strtoupper($value));

    $result = $pipeline->applyBefore('hello', 'name');

    expect($result)->toBe('HELLO');
});

it('applies a single after mutation', function () {
    $pipeline = new MutationPipeline;
    $pipeline->after(fn (mixed $value, string $attribute) => trim($value));

    $result = $pipeline->applyAfter('  hello  ', 'name');

    expect($result)->toBe('hello');
});

it('applies multiple before mutations in order', function () {
    $pipeline = new MutationPipeline;
    $pipeline->before(
        fn (mixed $value, string $attribute) => $value.'_first',
        fn (mixed $value, string $attribute) => $value.'_second',
    );

    $result = $pipeline->applyBefore('start', 'field');

    expect($result)->toBe('start_first_second');
});

it('applies multiple after mutations in order', function () {
    $pipeline = new MutationPipeline;
    $pipeline->after(
        fn (mixed $value, string $attribute) => $value * 2,
        fn (mixed $value, string $attribute) => $value + 1,
    );

    $result = $pipeline->applyAfter(5, 'amount');

    expect($result)->toBe(11);
});

it('supports method chaining for before and after', function () {
    $pipeline = new MutationPipeline;

    $result = $pipeline
        ->before(fn (mixed $value, string $attribute) => $value.'_before')
        ->after(fn (mixed $value, string $attribute) => $value.'_after');

    expect($result)->toBeInstanceOf(MutationPipeline::class);
    expect($pipeline->applyBefore('x', 'attr'))->toBe('x_before');
    expect($pipeline->applyAfter('y', 'attr'))->toBe('y_after');
});

it('passes attribute name correctly to before mutations', function () {
    $pipeline = new MutationPipeline;
    $pipeline->before(fn (mixed $value, string $attribute) => "{$attribute}:{$value}");

    $result = $pipeline->applyBefore('test', 'my_field');

    expect($result)->toBe('my_field:test');
});

it('passes attribute name correctly to after mutations', function () {
    $pipeline = new MutationPipeline;
    $pipeline->after(fn (mixed $value, string $attribute) => "{$attribute}={$value}");

    $result = $pipeline->applyAfter('42', 'score');

    expect($result)->toBe('score=42');
});

it('returns value unchanged when no before mutations registered', function () {
    $pipeline = new MutationPipeline;

    $result = $pipeline->applyBefore('unchanged', 'field');

    expect($result)->toBe('unchanged');
});

it('returns value unchanged when no after mutations registered', function () {
    $pipeline = new MutationPipeline;

    $result = $pipeline->applyAfter('unchanged', 'field');

    expect($result)->toBe('unchanged');
});

it('keeps before and after mutations independent', function () {
    $pipeline = new MutationPipeline;
    $pipeline->before(fn (mixed $value, string $attribute) => $value.'_before');
    $pipeline->after(fn (mixed $value, string $attribute) => $value.'_after');

    expect($pipeline->applyBefore('x', 'f'))->toBe('x_before');
    expect($pipeline->applyAfter('x', 'f'))->toBe('x_after');
});
