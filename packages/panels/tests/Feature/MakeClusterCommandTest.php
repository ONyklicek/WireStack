<?php

declare(strict_types=1);

use App\Clusters\Settings;
use Illuminate\Support\Facades\File;
use NyonCode\WirePanels\Enums\SubNavigationPosition;

/*
 * make:wire-cluster and the member it is for, make:wire-page --cluster.
 *
 * Both files are loaded, not just read: a cluster is a page, and a member names
 * it by class — whether the two agree is a question PHP answers on load.
 */
afterEach(function () {
    File::deleteDirectory(app_path('Clusters'));
    File::deleteDirectory(app_path('Livewire'));
    File::deleteDirectory(resource_path('views/livewire'));
});

it('writes a cluster and says how to register it', function () {
    $this->artisan('make:wire-cluster', ['name' => 'Settings'])
        ->expectsOutputToContain("config('wire-panels.pages')")
        ->assertSuccessful();

    require_once app_path('Clusters/Settings.php');

    expect(Settings::key())->toBe('settings')
        ->and(Settings::subNavigationPosition())->toBe(SubNavigationPosition::Start);

    $this->artisan('make:wire-cluster', ['name' => 'Settings'])->expectsOutputToContain('already exists')->assertSuccessful();
});

it('writes a page that is a member of a cluster, named or given as a class', function () {
    $this->artisan('make:wire-page', ['name' => 'Taxes', '--cluster' => 'Settings'])->assertSuccessful();
    $this->artisan('make:wire-page', ['name' => 'Rates', '--cluster' => '\\Acme\\Billing'])->assertSuccessful();

    expect(File::get(app_path('Livewire/Pages/Taxes.php')))->toContain('protected static ?string $cluster = \\App\\Clusters\\Settings::class;')
        ->and(File::get(app_path('Livewire/Pages/Rates.php')))->toContain('protected static ?string $cluster = \\Acme\\Billing::class;');
});
