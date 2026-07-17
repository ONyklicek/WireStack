<?php

declare(strict_types=1);

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\FileUpload;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

// ─── URL resolution ───────────────────────────────────────────────

it('builds a plain, non-expiring URL for a public disk by default', function () {
    Storage::fake('media');

    $url = FileUpload::make('doc')->disk('media')->resolveFileUrl('a.png');

    expect($url)->toContain('/storage/a.png')
        ->and($url)->not->toContain('expiration=');
});

it('signs an expiring URL for a private disk', function () {
    Storage::fake('media');

    $url = FileUpload::make('doc')->disk('media')->visibility('private')->resolveFileUrl('a.png');

    expect($url)->toContain('expiration=');
});

it('honours a custom signed-url expiry', function () {
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('temporaryUrl')
        ->once()
        ->with('a.png', Mockery::on(fn ($e): bool => $e->greaterThan(now()->addMinutes(59)) && $e->lessThan(now()->addMinutes(61))))
        ->andReturn('/signed/a.png?expiration=1');
    Storage::set('media', $disk);

    $url = FileUpload::make('doc')->disk('media')->visibility('private')
        ->signedUrlExpiration(60)->resolveFileUrl('a.png');

    expect($url)->toBe('/signed/a.png?expiration=1');
});

it('degrades to null instead of fatalling when the disk cannot produce any URL', function () {
    // A private local disk with no temporary-url route throws on BOTH temporaryUrl
    // and url — the field must show a filename, not take the whole form down.
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('temporaryUrl')->andThrow(new RuntimeException('no temporary urls'));
    $disk->shouldReceive('url')->andThrow(new RuntimeException('This driver does not support retrieving URLs.'));
    Storage::set('vault', $disk);

    $url = FileUpload::make('doc')->disk('vault')->visibility('private')->resolveFileUrl('secret.pdf');

    expect($url)->toBeNull();
});

// ─── previewUrlUsing escape hatch ─────────────────────────────────

it('lets previewUrlUsing supply the URL, receiving the stored path', function () {
    $field = FileUpload::make('doc')->disk('media')->visibility('private')
        ->previewUrlUsing(fn (string $path): string => "/my/route/{$path}");

    // Wins outright — the disk is never consulted (it would otherwise need faking).
    expect($field->resolveFileUrl('reports/q3.pdf'))->toBe('/my/route/reports/q3.pdf');
});

it('treats a non-string previewUrlUsing result as no preview', function () {
    $field = FileUpload::make('doc')->previewUrlUsing(fn (string $path): ?string => null);

    expect($field->resolveFileUrl('a.png'))->toBeNull();
});

// ─── deleteStoredFile ─────────────────────────────────────────────

it('leaves the physical file on disk by default', function () {
    Storage::fake('media');
    Storage::disk('media')->put('a.png', 'x');

    FileUpload::make('doc')->disk('media')->deleteStoredFile('a.png');

    expect(Storage::disk('media')->exists('a.png'))->toBeTrue();
});

it('deletes the physical file when deletesFromDisk is enabled', function () {
    Storage::fake('media');
    Storage::disk('media')->put('a.png', 'x');

    FileUpload::make('doc')->disk('media')->deletesFromDisk()->deleteStoredFile('a.png');

    expect(Storage::disk('media')->exists('a.png'))->toBeFalse();
});

it('never deletes an external URL or data URI even when deletesFromDisk is on', function () {
    Storage::fake('media');

    // No disk interaction expected — a full URL / data: URI was never our file.
    FileUpload::make('doc')->disk('media')->deletesFromDisk()
        ->deleteStoredFile('https://cdn.example.com/a.png');
    FileUpload::make('doc')->disk('media')->deletesFromDisk()
        ->deleteStoredFile('data:image/png;base64,AAAA');

    expect(true)->toBeTrue();
});

it('runs a custom deleteUsing callback instead of the disk delete, and implies deletion', function () {
    Storage::fake('media');
    Storage::disk('media')->put('a.png', 'x');

    $seen = null;
    $field = FileUpload::make('doc')->disk('media')
        ->deleteUsing(function (string $path) use (&$seen): void {
            $seen = $path;
        });

    expect($field->shouldDeleteFromDisk())->toBeTrue();

    $field->deleteStoredFile('a.png');

    // Callback owns teardown entirely — the built-in disk delete never runs.
    expect($seen)->toBe('a.png')
        ->and(Storage::disk('media')->exists('a.png'))->toBeTrue();
});

// ─── removeUploadedFile end-to-end (host) ─────────────────────────

class FileUploadDeleteHost extends Component
{
    use WithForms;

    public array $data = ['docs' => ['uploads/a.png', 'uploads/b.png']];

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            FileUpload::make('docs')->multiple()->disk('media')->deletesFromDisk(),
        ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

it('removes the reference and deletes the file from disk when the field opts in', function () {
    Storage::fake('media');
    Storage::disk('media')->put('uploads/a.png', 'x');
    Storage::disk('media')->put('uploads/b.png', 'y');

    $component = Livewire::test(FileUploadDeleteHost::class);
    $component->call('removeUploadedFile', 'data.docs', 0);

    expect($component->get('data')['docs'])->toBe(['uploads/b.png'])
        ->and(Storage::disk('media')->exists('uploads/a.png'))->toBeFalse()
        ->and(Storage::disk('media')->exists('uploads/b.png'))->toBeTrue();
});
