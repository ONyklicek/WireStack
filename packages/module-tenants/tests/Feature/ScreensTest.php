<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Tenancy\CurrentTenant;
use NyonCode\WireModuleTenants\Actions\RemoveMember;
use NyonCode\WireModuleTenants\Pages\CompanyProfile;
use NyonCode\WireModuleTenants\Pages\ListMembers;
use NyonCode\WireModuleTenants\Resources\CompanyResource;
use NyonCode\WireModuleTenants\Resources\MemberResource;

/*
 * The company's own screens inside a tenant zone: its profile and its members,
 * each drawn for an owner and a member differently, and the zone's address for
 * somebody in no company.
 */

beforeEach(function () {
    $this->owner = $this->person('Olga');
    $this->member = $this->person('Max');
    $this->stranger = $this->person('Sam');
    $this->tenant = $this->company('acme', $this->owner);
    $this->tenant->members()->attach($this->member->getKey(), ['role' => 'member']);
    $this->company('globex', $this->stranger);

    Route::middleware(['web'])->name('app.')->group(fn () => Route::wireTenantEntry('app', 'app/{tenant}'));
    Route::middleware(['web', 'wire.tenant'])->prefix('app/{tenant}')->name('app.')->group(fn () => Route::wireResources());
    Route::getRoutes()->refreshNameLookups();
});

it('lists the company members only, with the actions an owner has', function () {
    $html = $this->actingAs($this->owner)->get('/app/acme/members')->assertOk()->getContent();

    expect($html)->toContain('olga@example.com')->toContain('max@example.com')
        ->not->toContain('sam@example.com')
        ->toContain('Invite')
        ->toContain('Owner');
});

it('shows a member the list without an owner actions', function () {
    $html = $this->actingAs($this->member)->get('/app/acme/members')->assertOk()->getContent();

    expect($html)->toContain('olga@example.com')->not->toContain('Invite');
});

it('says so when a member action is refused, instead of failing the page', function () {
    $this->actingAs($this->owner)->get('/app/acme/members');

    $page = new class extends ListMembers
    {
        public function tryRemovingTheOwner(): void
        {
            $this->attempt(fn () => (new RemoveMember)($this->tenant(), $this->actor(), $this->actor()));
        }
    };

    $page->tryRemovingTheOwner();

    expect(app(CurrentTenant::class)->has())->toBeTrue();
});

it('lets an owner rename the company and move it to a new address', function () {
    $this->actingAs($this->owner)->get('/app/acme/company')->assertOk()->assertSee('Delete the company');

    Livewire::test(CompanyProfile::class)
        ->set('data.name', 'Acme Rockets')
        ->call('save')
        ->assertNoRedirect();

    expect($this->tenant->fresh()->name)->toBe('Acme Rockets');

    Livewire::test(CompanyProfile::class)
        ->set('data.slug', 'acme-rockets')
        ->call('save')
        ->assertRedirect(url('app/acme-rockets/company'));
});

it('shows a member the profile without a way to change it', function () {
    $this->actingAs($this->member)->get('/app/acme/company')->assertOk()->assertDontSee('Delete the company');

    Livewire::test(CompanyProfile::class)
        ->set('data.name', 'Hijacked')
        ->call('save')
        ->assertForbidden();
});

it('deletes the company for its owner once the name is typed', function () {
    $this->actingAs($this->owner)->get('/app/acme/company');

    Livewire::test(CompanyProfile::class)
        ->set('confirmation', 'wrong')
        ->call('deleteCompany')
        ->assertNoRedirect();

    expect($this->tenant->fresh()->trashed())->toBeFalse();

    Livewire::test(CompanyProfile::class)
        ->set('confirmation', 'Acme')
        ->call('deleteCompany')
        ->assertRedirect(url('/'));

    expect($this->tenant->fresh()->trashed())->toBeTrue();
});

it('keeps the screens out of the menu, and off, outside a company', function () {
    app(CurrentTenant::class)->leave();

    expect(CompanyResource::navigation()->isVisible())->toBeFalse()
        ->and(MemberResource::navigation()->isVisible())->toBeFalse();

    $this->actingAs($this->owner);

    Livewire::test(CompanyProfile::class)->assertStatus(404);
});

it('offers somebody in no company the registration, or tells them to ask', function () {
    $nobody = $this->person('Nobody');

    $this->actingAs($nobody)->get('/app')->assertOk()->assertSee('Register a company')->assertSee('tenants/register');

    config()->set('wire-module-tenants.registration', false);

    $this->actingAs($nobody)->get('/app')->assertOk()->assertSee('Ask a colleague');
});
