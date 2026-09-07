<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use NyonCode\WireCore\Audit\AuditEntry;
use NyonCode\WireCore\Notifications\DatabaseNotification;
use NyonCode\WireModuleMedia\Actions\MakeThumbnail;
use NyonCode\WireModuleMedia\Models\Media;
use NyonCode\WireModuleMedia\Models\MediaFolder;
use NyonCode\WireModuleSettings\Support\Settings;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\User;

/**
 * Rows for the module screens, so the workbench shows an admin with something in
 * it rather than five empty tables.
 *
 * Separate from `DatabaseSeeder` on purpose: these rows belong to packages an
 * application may not have installed, and a seeder that fails on a missing class
 * would take the whole workbench setup with it.
 */
class ModuleDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->first();

        if ($user === null) {
            return;
        }

        $this->auditTrail($user);
        $this->notifications($user);
        $this->media($user);

        Settings::fill([
            'company_name' => 'Nyon Industries',
            'support_email' => 'support@nyon.example',
            'dark_by_default' => false,
        ], 'branding');
    }

    private function auditTrail(User $user): void
    {
        foreach (Invoice::query()->take(3)->get()->values() as $index => $invoice) {
            AuditEntry::query()->create([
                'event' => ['created', 'updated', 'deleted'][$index % 3],
                'auditable_type' => Invoice::class,
                'auditable_id' => (string) $invoice->getKey(),
                'user_id' => (string) $user->getKey(),
                'old_values' => $index === 0 ? [] : ['status' => 'draft', 'total' => 1200],
                'new_values' => ['status' => 'sent', 'total' => 1450],
                'metadata' => ['ip' => '10.0.0.4'],
                'created_at' => now()->subDays($index)->subHours($index * 3),
            ]);
        }
    }

    private function notifications(User $user): void
    {
        // The third one carries a destination and an action button, because a
        // panel demonstrating only text demonstrates the half that always worked.
        // A link rather than an event: what a stored notification needs is
        // something that still works when the component that raised it is gone.
        // Spread over three days and across the four types, because a panel
        // demonstrating one grey row under one heading demonstrates neither the
        // day grouping nor the tint. Title and message differ for the same
        // reason they differ in a real application: the title is what it is, the
        // message is what happened.
        $rows = [
            ['success', 'Invoice INV-2026-003', 'Northwind Traders paid €4,180.00.', 0, null, '/previews/routed/invoices', null],
            ['warning', 'Quarterly close', 'The task is two days overdue and still unassigned.', 0, null, null, null],
            ['info', 'Weekly export', 'Finished in 41s — 12,480 rows.', 1, null, null, ['label' => 'Download', 'url' => '/previews/routed/invoices']],
            ['error', 'Payment declined', 'Globex Corporation — the card issuer refused the charge.', 2, 2, '/previews/routed/invoices', null],
        ];

        foreach ($rows as [$type, $title, $message, $daysAgo, $readDaysAgo, $url, $action]) {
            DatabaseNotification::query()->create([
                'id' => (string) Str::uuid(),
                'type' => 'workbench.demo',
                'notifiable_type' => User::class,
                'notifiable_id' => (string) $user->getKey(),
                'data' => array_filter([
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'url' => $url,
                    'actions' => $action === null ? null : [$action],
                ]),
                'read_at' => $readDaysAgo === null ? null : now()->subDays($readDaysAgo),
                'created_at' => now()->subDays($daysAgo)->subHours(3),
                'updated_at' => now()->subDays($daysAgo)->subHours(3),
            ]);
        }
    }

    private function media(User $user): void
    {
        // A tree with something at more than one level, because a library that
        // demonstrates folders with one folder demonstrates nothing.
        $brand = MediaFolder::createIn(null, 'Brand');
        $logos = MediaFolder::createIn($brand, 'Logos');
        $contracts = MediaFolder::createIn(null, 'Contracts');
        MediaFolder::createIn($brand, 'Photography');

        $files = [
            [$contracts, 'contracts/annual-report.pdf', 'annual-report.pdf', 'application/pdf', 284213],
            [$contracts, 'contracts/msa-2026.pdf', 'msa-2026.pdf', 'application/pdf', 118904],
            [$logos, 'photos/office.jpg', 'wordmark.jpg', 'image/jpeg', 1204558],
            [$logos, 'photos/team.png', 'mark-dark.png', 'image/png', 842119],
            [null, 'photos/cover.png', 'cover.png', 'image/png', 512044],
        ];

        foreach ($files as [$folder, $path, $name, $mime, $size]) {
            // Real bytes on the disk, not just a row: a library whose previews
            // are all broken images demonstrates the opposite of what it is for,
            // and the thumbnails cannot be made from a path that is not a file.
            if ($mime !== 'application/pdf') {
                Storage::disk('public')->put($path, $this->swatch($name));
            }

            $media = Media::query()->create([
                'folder_id' => $folder?->id,
                'disk' => 'public',
                'path' => $path,
                'name' => $name,
                'mime_type' => $mime,
                'size' => $size,
                // The swatch's own size. A real upload records this in
                // StoreUpload; seeding it means the detail page demonstrates the
                // Dimensions entry rather than showing a dash next to a picture
                // that plainly has some.
                'width' => $mime === 'application/pdf' ? null : 640,
                'height' => $mime === 'application/pdf' ? null : 480,
                'uploaded_by' => (string) $user->getKey(),
            ]);

            app(MakeThumbnail::class)($media);
        }
    }

    /**
     * A small PNG, coloured from the file's own name.
     *
     * Deterministic, so the drivers see the same library on every run, and
     * generated rather than committed so the repository carries no binaries for
     * a demo.
     */
    private function swatch(string $name): string
    {
        $hue = crc32($name) % 360;
        $image = imagecreatetruecolor(640, 480);

        [$r, $g, $b] = array_map(
            static fn (float $c): int => (int) round(($c + 0.35) * 190),
            [
                (1 + cos(deg2rad($hue))) / 2,
                (1 + cos(deg2rad($hue - 120))) / 2,
                (1 + cos(deg2rad($hue - 240))) / 2,
            ],
        );

        imagefilledrectangle($image, 0, 0, 640, 480, (int) imagecolorallocate($image, $r, $g, $b));
        imagefilledellipse($image, 320, 240, 260, 260, (int) imagecolorallocate($image, 255, 255, 255));

        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
