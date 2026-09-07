<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Mentions;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Exceptions\MentionRegistrationException;
use NyonCode\WireCore\Foundation\Support\MorphedModels;

/**
 * Render-time facts about mentionable models, for the models that cannot carry
 * them ({@see Contracts\Mentionable} is how a model you own carries its own).
 *
 * Keyed by class, never by alias: an application may register `Article::class`
 * and store `article`, or register the alias and store the class, and both have
 * to find each other. Every key is put through {@see MorphedModels::classFor()}
 * on the way in and on the way out, so the two spellings converge.
 */
final class MentionRegistry
{
    /** @var array<class-string<Model>, RegisteredMention> */
    private array $entries = [];

    /**
     * Declare how a model renders when mentioned. Registering the same model
     * twice returns the first entry so a second provider refines it rather than
     * silently replacing it.
     *
     * @param  class-string<Model>|string  $type  A model class or a morph alias.
     */
    public function register(string $type): RegisteredMention
    {
        $class = MorphedModels::classFor($type);

        if ($class === null) {
            throw MentionRegistrationException::unknownType($type);
        }

        return $this->entries[$class] ??= new RegisteredMention;
    }

    /**
     * The entry for a stored type, or null when the model answers for itself
     * (or for nothing at all).
     */
    public function for(?string $type): ?RegisteredMention
    {
        $class = MorphedModels::classFor($type);

        return $class === null ? null : ($this->entries[$class] ?? null);
    }

    /** @return array<class-string<Model>, RegisteredMention> */
    public function all(): array
    {
        return $this->entries;
    }
}
