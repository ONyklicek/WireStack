<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleMedia\Install\LinkPublicDisk;

/*
 * The symlink without which every uploaded file is a broken image.
 *
 * Uploads work, the library lists them, the thumbnails generate, and every
 * `<img>` on the page is a 404 because `public/storage` was never made. Nothing
 * errors.
 */

/**
 * A console that answers from a script and records what it was told.
 *
 * @param  array<int, string>  $answers
 * @param  array<int, string>  $said
 */
function lpdConsole(array $answers = [], array &$said = [], bool $interactive = true): SetupConsole
{
    return new class($answers, $said, $interactive) implements SetupConsole
    {
        /**
         * @param  array<int, string>  $answers
         * @param  array<int, string>  $said
         */
        public function __construct(private array $answers, private array &$said, private bool $interactive) {}

        public function ask(string $question, ?string $default = null): string
        {
            return array_shift($this->answers) ?? (string) $default;
        }

        public function secret(string $question): string
        {
            return array_shift($this->answers) ?? '';
        }

        public function confirm(string $question, bool $default = true): bool
        {
            return $default;
        }

        public function choose(string $question, array $options, ?string $default = null): string
        {
            return array_shift($this->answers) ?? (string) $default;
        }

        public function note(string $message): void
        {
            $this->said[] = $message;
        }

        public function warn(string $message): void
        {
            $this->said[] = $message;
        }

        public function isInteractive(): bool
        {
            return $this->interactive;
        }
    };
}

function lpdArtisan(int $exitCode = 0): Kernel
{
    $artisan = Mockery::mock(Kernel::class);
    $artisan->shouldReceive('call')->with('storage:link')->andReturn($exitCode);

    return $artisan;
}

beforeEach(function () {
    config()->set('wire-module-media.disk', 'public');
    config()->set('filesystems.links', [
        sys_get_temp_dir().'/wire-media-link-absent-'.uniqid() => storage_path('app/public'),
    ]);
});

it('is contributed by this module', function () {
    expect(SetupRegistry::instance()->all())->toContain(LinkPublicDisk::class);
});

it('is pending while the link is missing', function () {
    $step = new LinkPublicDisk(lpdArtisan());

    expect($step->state())->toBe(SetupState::Pending)
        ->and($step->summary())->toContain('every upload is a silent 404')
        ->and($step->label())->toBe('Media links')
        ->and($step->sort())->toBe(500);
});

it('is done once the link is there', function () {
    // Read off `filesystems.links`, which is the map storage:link works from, so
    // an application that moved its public path is answered correctly.
    config()->set('filesystems.links', [sys_get_temp_dir() => storage_path('app/public')]);

    $step = new LinkPublicDisk(lpdArtisan());

    expect($step->state())->toBe(SetupState::Done)
        ->and($step->summary())->toBe('public/storage is already linked');
});

it('has nothing to do for a disk that serves its own files', function () {
    // storage:link is about the `public` disk. An application on S3 is not
    // half-installed; it simply has no link to make.
    config()->set('wire-module-media.disk', 's3');

    $step = new LinkPublicDisk(lpdArtisan());

    expect($step->state())->toBe(SetupState::Done)
        ->and($step->summary())->toBe('the `s3` disk serves its own files');
});

it('makes the link', function () {
    $said = [];

    expect((new LinkPublicDisk(lpdArtisan()))->apply(lpdConsole([], $said)))->toBe(SetupOutcome::Applied)
        ->and(implode("\n", $said))->toContain('Linked public/storage');
});

it('fails rather than pretending, when the link cannot be made', function () {
    $said = [];

    expect((new LinkPublicDisk(lpdArtisan(1)))->apply(lpdConsole([], $said)))->toBe(SetupOutcome::Failed)
        ->and(implode("\n", $said))->toContain('storage:link did not finish');
});
