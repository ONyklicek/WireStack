<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Install;

use Illuminate\Contracts\Console\Kernel;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;

/**
 * The symlink without which every uploaded file is a broken image.
 *
 * This module's installer already said it — "Run: php artisan storage:link (the
 * public disk needs it)" — and that sentence is the whole failure mode: uploads
 * work, the library lists them, the thumbnails generate, and every `<img>` on
 * the page is a 404 because `public/storage` was never made. Nothing errors.
 *
 * ## Only for a disk that needs one
 *
 * `storage:link` is about the `public` disk specifically. An application storing
 * media on S3 has nothing to link and is not half-installed, so the disk is read
 * first and the step is simply Done for anything else.
 */
final readonly class LinkPublicDisk implements SetupStep
{
    public function __construct(private Kernel $artisan) {}

    public function label(): string
    {
        return 'Media links';
    }

    public function state(): SetupState
    {
        if (! $this->needsLink()) {
            return SetupState::Done;
        }

        return $this->linked() ? SetupState::Done : SetupState::Pending;
    }

    public function summary(): string
    {
        if (! $this->needsLink()) {
            return 'the `'.$this->disk().'` disk serves its own files';
        }

        return $this->linked()
            ? 'public/storage is already linked'
            : 'link public/storage, or every upload is a silent 404';
    }

    public function apply(SetupConsole $console): SetupOutcome
    {
        if ($this->artisan->call('storage:link') !== 0) {
            $console->warn('php artisan storage:link did not finish — run it yourself to see why.');

            return SetupOutcome::Failed;
        }

        $console->note('Linked public/storage.');

        return SetupOutcome::Applied;
    }

    public function sort(): int
    {
        return 500;
    }

    private function disk(): string
    {
        return (string) config('wire-module-media.disk', 'public');
    }

    private function needsLink(): bool
    {
        return $this->disk() === 'public';
    }

    /**
     * Whether the link is already there.
     *
     * Read off `filesystems.links`, which is the same map `storage:link` works
     * from, so an application that moved its public path or named a second link
     * is answered correctly rather than against a hard-coded `public/storage`.
     */
    private function linked(): bool
    {
        /** @var array<string, string> $links */
        $links = (array) config('filesystems.links', []);

        foreach ($links as $link => $target) {
            if (str_contains($target, '/app/public') && (is_link($link) || is_dir($link))) {
                return true;
            }
        }

        return false;
    }
}
