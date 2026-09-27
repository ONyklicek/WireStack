<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Pages;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Livewire\Component;
use NyonCode\WireCore\Notifications\NotificationManager;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;
use NyonCode\WireModuleTenants\Actions\DeleteTenant;
use NyonCode\WireModuleTenants\Resources\CompanyResource;
use NyonCode\WireModuleTenants\Support\Membership;
use NyonCode\WireModuleTenants\Support\Registration;

/**
 * The company's own page: its name and its slug, and — for its owner — the
 * way to delete it.
 *
 * A member sees it and cannot change it; the form is disabled for them, and
 * saving is refused again at the write, since a disabled field is markup.
 * Changing the slug changes every URL of the company, so a save that changes
 * it lands on the page's new address.
 */
class CompanyProfile extends Component
{
    use WithForms;

    public ?array $data = [];

    public string $confirmation = '';

    public function mount(): void
    {
        $tenant = $this->tenant();

        $this->form->fill([
            'name' => $tenant->getAttribute('name'),
            'slug' => $tenant->getAttribute('slug'),
        ]);
    }

    public function form(Form $form): Form
    {
        $tenant = $this->tenant();

        return $form
            ->statePath('data')
            ->disabled(! $this->isOwner())
            ->schema([
                TextInput::make('name')->label(__('wire-module-tenants::messages.company_name'))->required()->maxLength(120),
                TextInput::make('slug')
                    ->label(__('wire-module-tenants::messages.company_slug'))
                    ->helperText(__('wire-module-tenants::messages.company_slug_change'))
                    ->required()
                    ->maxLength(60)
                    ->rules(['alpha_dash', Rule::unique($tenant->getTable(), 'slug')->ignore($tenant->getKey()), Rule::notIn(Registration::reserved())])
                    ->validationMessages([
                        'not_in' => __('wire-module-tenants::messages.slug_reserved'),
                        'unique' => __('wire-module-tenants::messages.slug_taken'),
                    ]),
            ])
            ->successMessage(__('wire-module-tenants::messages.saved'))
            ->using(function (array $data) use ($tenant): array {
                abort_unless($this->isOwner(), 403);

                $tenant->forceFill(['name' => $data['name'], 'slug' => mb_strtolower((string) $data['slug'])])->save();

                return $data;
            });
    }

    public function save(): mixed
    {
        $before = (string) $this->tenant()->getAttribute('slug');

        $this->form->save();

        $after = (string) $this->tenant()->getAttribute('slug');

        return $before === $after ? null : $this->redirect((string) CompanyResource::url(parameters: ['tenant' => $after]));
    }

    public function deleteCompany(): mixed
    {
        $user = auth()->user();
        abort_unless($user instanceof Model, 403);

        if (! (new DeleteTenant)($this->tenant(), $this->confirmation, $user)) {
            NotificationManager::error(__('wire-module-tenants::messages.delete_mismatch'));

            return null;
        }

        return $this->redirect(url('/'));
    }

    public function isOwner(): bool
    {
        return Membership::isOwner($this->tenant(), auth()->user());
    }

    protected function tenant(): Model
    {
        return Membership::current() ?? abort(404);
    }

    public function render(): View
    {
        return view('wire-module-tenants::profile', [
            'title' => __('wire-module-tenants::messages.company'),
            'breadcrumbs' => [],
            'headerActions' => [],
            'owner' => $this->isOwner(),
            'companyName' => (string) $this->tenant()->getAttribute('name'),
        ]);
    }
}
