<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use NyonCode\WireCore\Exceptions\IconSetRegistrationException;
use NyonCode\WireCore\Foundation\Icons\DefaultIconSet;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Foundation\Icons\IconSet;

/**
 * What `wire-core.icons` does with an entry it cannot use.
 *
 * It used to skip it. That is the quietest failure in the package: the set is
 * never registered, every icon addressed through its prefix renders the
 * missing-icon placeholder, and nothing anywhere connects the placeholder to the
 * line of config that caused it. Config is read when the manager is first
 * resolved, so throwing here cannot reach a request that would otherwise have
 * worked.
 */
it('refuses a class in icons.sets that is not an icon set', function () {
    config()->set('wire-core.icons.sets', ['lucide' => 'App\\Icons\\Typo']);

    app(IconManager::class);
})->throws(IconSetRegistrationException::class, 'it is not a class that exists');

it('names the contract when the class exists but does not implement it', function () {
    config()->set('wire-core.icons.sets', ['lucide' => stdClass::class]);

    app(IconManager::class);
})->throws(IconSetRegistrationException::class, IconSet::class);

it('refuses a non-default set declared without a string prefix', function () {
    config()->set('wire-core.icons.sets', [IconConfigTestSet::class]);

    app(IconManager::class);
})->throws(IconSetRegistrationException::class, 'string prefix key');

it('refuses an icons.paths entry that is not a readable directory', function () {
    config()->set('wire-core.icons.paths', ['brand' => '/no/such/icon/directory']);

    app(IconManager::class);
})->throws(IconSetRegistrationException::class, 'not a readable directory');

it('still registers a correctly declared set and directory', function () {
    // The other half: the guards refuse what is wrong without narrowing what works.
    $directory = sys_get_temp_dir().'/wire-icons-'.uniqid();
    mkdir($directory);
    file_put_contents($directory.'/logo.svg', '<svg viewBox="0 0 20 20"><path d="M0 0"/></svg>');

    config()->set('wire-core.icons.sets', ['test' => IconConfigTestSet::class]);
    config()->set('wire-core.icons.paths', ['brand' => $directory]);

    $manager = app(IconManager::class);

    expect($manager->has('brand-logo'))->toBeTrue()
        ->and($manager->has('test:square'))->toBeTrue();

    unlink($directory.'/logo.svg');
    rmdir($directory);
});

it('makes another set the unprefixed base while the shipped default entry stays listed', function () {
    // The shipped config lists Heroicons under 'default'. Pointing default_set
    // elsewhere used to refuse the whole config at boot: the entry has no
    // prefix of its own to move to, and 'default' is reserved.
    config()->set('wire-core.icons.default_set', 'test');
    config()->set('wire-core.icons.sets', [
        'default' => DefaultIconSet::class,
        'test' => IconConfigTestSet::class,
    ]);

    $manager = app(IconManager::class);

    expect($manager->has('square'))->toBeTrue()
        ->and($manager->has('default:square'))->toBeTrue()
        ->and($manager->has('outline:x-mark'))->toBeTrue();
});

it('refuses a default_set that names no listed set', function () {
    // It used to be ignored, leaving Heroicons as the base without a word.
    config()->set('wire-core.icons.default_set', 'lucide');
    config()->set('wire-core.icons.sets', ['default' => DefaultIconSet::class]);

    app(IconManager::class);
})->throws(IconSetRegistrationException::class, 'wire-core.icons.default_set is [lucide], but wire-core.icons.sets has no set under that key (it lists: default).');

it('says so when sets is empty', function () {
    config()->set('wire-core.icons.default_set', 'lucide');
    config()->set('wire-core.icons.sets', []);

    app(IconManager::class);
})->throws(IconSetRegistrationException::class, '(it lists: none)');

it('warns about a missing icon when warn_missing comes from the environment as "1"', function () {
    config()->set('wire-core.icons.warn_missing', '1');
    Log::spy();

    app(IconManager::class)->render('no-such-icon-anywhere');

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'no-such-icon-anywhere'));
});

final class IconConfigTestSet implements IconSet
{
    public function getPath(string $name): ?string
    {
        return $name === 'square' ? '<path d="M0 0h20v20H0z"/>' : null;
    }

    public function has(string $name): bool
    {
        return $name === 'square';
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return ['square'];
    }
}
