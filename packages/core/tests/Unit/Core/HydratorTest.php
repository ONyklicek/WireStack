<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Hydration\CastResolver;
use NyonCode\WireCore\Core\Hydration\Hydrator;
use NyonCode\WireCore\Core\Hydration\ValueTransformer;
use Workbench\App\Models\Task;

function hydrator(): Hydrator
{
    return new Hydrator(new ValueTransformer, new CastResolver);
}

it('hydrates all model attributes when none are specified', function () {
    $task = new Task;
    $task->forceFill(['title' => 'Write tests', 'completed' => 1]);

    $state = hydrator()->hydrate($task);

    expect($state)->toHaveKeys(['title', 'completed'])
        ->and($state['title'])->toBe('Write tests');
});

it('applies casts to attribute values', function () {
    $task = new Task;
    $task->forceFill(['completed' => 1]);

    $state = hydrator()->hydrate($task, ['completed']);

    // 'completed' is cast to boolean on the model.
    expect($state['completed'])->toBeTrue();
});

it('returns the raw value when there is no cast', function () {
    $task = new Task;
    $task->forceFill(['title' => 'Plain']);

    expect(hydrator()->hydrate($task, ['title']))->toBe(['title' => 'Plain']);
});

it('hydrates nested relation attributes via dot notation', function () {
    $child = new class extends Model
    {
        protected $guarded = [];
    };
    $child->forceFill(['city' => 'London']);

    $parent = new class extends Model
    {
        protected $guarded = [];
    };
    $parent->forceFill(['id' => 1]);
    $parent->setRelation('profile', $child);

    expect(hydrator()->hydrate($parent, ['profile.city']))
        ->toBe(['profile.city' => 'London']);
});

it('returns null for a missing relation in a dotted path', function () {
    $parent = new class extends Model
    {
        protected $guarded = [];
    };
    $parent->setRelation('profile', null);

    expect(hydrator()->hydrate($parent, ['profile.city']))
        ->toBe(['profile.city' => null]);
});

it('returns null when a relation segment is not a single model', function () {
    $parent = new class extends Model
    {
        protected $guarded = [];
    };
    $parent->setRelation('items', new Collection([new Task]));

    expect(hydrator()->hydrate($parent, ['items.name']))
        ->toBe(['items.name' => null]);
});
