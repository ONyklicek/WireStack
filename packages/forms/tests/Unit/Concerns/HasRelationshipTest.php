<?php

declare(strict_types=1);

use NyonCode\WireForms\Concerns\HasRelationship;

class HasRelationshipHost
{
    use HasRelationship;
}

test('relationship stores name and title attribute fluently', function () {
    $host = new HasRelationshipHost;

    expect($host->getRelationship())->toBeNull()
        ->and($host->getTitleAttribute())->toBeNull();

    expect($host->relationship('author', 'name'))->toBe($host);

    expect($host->getRelationship())->toBe('author')
        ->and($host->getTitleAttribute())->toBe('name');
});

test('relationship title attribute is optional', function () {
    $host = (new HasRelationshipHost)->relationship('tags');

    expect($host->getRelationship())->toBe('tags')
        ->and($host->getTitleAttribute())->toBeNull();
});
