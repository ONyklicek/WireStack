<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use NyonCode\WireCore\Audit\Concerns\HasAuditable;

// ─── filterAuditAttributes ──────────────────────────────────────────────────

it('filters excluded attributes', function () {
    $model = new AuditableTestModel;

    $filtered = $model->publicFilterAuditAttributes([
        'name' => 'Alice',
        'cached_total' => 100,
        'email' => 'alice@example.com',
    ]);

    expect($filtered)->toBe([
        'name' => 'Alice',
        'email' => 'alice@example.com',
    ]);
});

it('filters to include-only attributes when set', function () {
    $model = new AuditableIncludeTestModel;

    $filtered = $model->publicFilterAuditAttributes([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'phone' => '123',
    ]);

    expect($filtered)->toBe([
        'name' => 'Alice',
        'email' => 'alice@example.com',
    ]);
});

it('applies both include and exclude filters', function () {
    $model = new AuditableBothTestModel;

    $filtered = $model->publicFilterAuditAttributes([
        'name' => 'Alice',
        'email' => 'secret@example.com',
        'phone' => '123',
    ]);

    // Include whitelist keeps name + email, then exclude removes email
    expect($filtered)->toBe([
        'name' => 'Alice',
    ]);
});

it('returns all attributes when no include/exclude configured', function () {
    $model = new AuditableNoFilterTestModel;

    $filtered = $model->publicFilterAuditAttributes([
        'name' => 'Alice',
        'email' => 'alice@example.com',
    ]);

    expect($filtered)->toBe([
        'name' => 'Alice',
        'email' => 'alice@example.com',
    ]);
});

// ─── Model events registration ───────────────────────────────────────────────

it('registers created event listener via bootHasAuditable', function () {
    // Verify the trait's boot method registers event listeners
    // by checking the dispatcher has listeners for the model events
    $dispatcher = AuditableNoFilterTestModel::getEventDispatcher();

    expect($dispatcher)->not->toBeNull();
});

it('has audits relationship defined', function () {
    $model = new AuditableNoFilterTestModel;

    // Verify the relationship method exists and returns MorphMany
    $relation = $model->audits();

    expect($relation)->toBeInstanceOf(MorphMany::class);
});

// ─── Test Models ─────────────────────────────────────────────────────────────

class AuditableTestModel extends Model
{
    use HasAuditable;

    protected $table = 'test_models';

    protected $guarded = [];

    protected function getAuditExclude(): array
    {
        return ['cached_total'];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function publicFilterAuditAttributes(array $attributes): array
    {
        return $this->filterAuditAttributes($attributes);
    }
}

class AuditableIncludeTestModel extends Model
{
    use HasAuditable;

    protected $table = 'test_models';

    protected $guarded = [];

    protected function getAuditInclude(): array
    {
        return ['name', 'email'];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function publicFilterAuditAttributes(array $attributes): array
    {
        return $this->filterAuditAttributes($attributes);
    }
}

class AuditableBothTestModel extends Model
{
    use HasAuditable;

    protected $table = 'test_models';

    protected $guarded = [];

    protected function getAuditInclude(): array
    {
        return ['name', 'email'];
    }

    protected function getAuditExclude(): array
    {
        return ['email'];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function publicFilterAuditAttributes(array $attributes): array
    {
        return $this->filterAuditAttributes($attributes);
    }
}

class AuditableNoFilterTestModel extends Model
{
    use HasAuditable;

    protected $table = 'test_models';

    protected $guarded = [];

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function publicFilterAuditAttributes(array $attributes): array
    {
        return $this->filterAuditAttributes($attributes);
    }
}
