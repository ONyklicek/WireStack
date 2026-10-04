<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Livewire\Component;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;
use NyonCode\WireModuleTenants\Actions\RegisterTenant as Register;
use NyonCode\WireModuleTenants\Support\Membership;
use NyonCode\WireModuleTenants\Support\Registration;

/**
 * Register a company — outside any company, at `{prefix}/register`.
 *
 * Whoever registers it is its owner, and lands on its first page. Who may is
 * `wire-module-tenants.registration`; the slug must be free and not one of the
 * segments the application's own routes answer.
 */
class RegisterTenant extends Component
{
    use WithForms;

    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(Registration::allows(auth()->user()), 403);

        $this->form->fill([]);
    }

    public function form(Form $form): Form
    {
        $model = Membership::tenantModel();

        return $form
            ->statePath('data')
            ->schema([
                TextInput::make('name')
                    ->label(__('wire-module-tenants::messages.company_name'))
                    ->required()
                    ->maxLength(120)
                    // Proposes the slug while it is still empty; once someone
                    // types their own, theirs stays.
                    ->afterStateUpdated(function ($state, $get, $set): void {
                        if (blank($get('slug'))) {
                            $set('slug', Registration::slugFor((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->label(__('wire-module-tenants::messages.company_slug'))
                    ->helperText(__('wire-module-tenants::messages.company_slug_help'))
                    ->required()
                    ->maxLength(60)
                    ->rules(['alpha_dash', Rule::unique((new $model)->getTable(), 'slug'), Rule::notIn(Registration::reserved())])
                    ->validationMessages([
                        'not_in' => __('wire-module-tenants::messages.slug_reserved'),
                        'unique' => __('wire-module-tenants::messages.slug_taken'),
                    ]),
            ])
            ->using(function (array $data): array {
                $user = auth()->user();
                abort_unless($user instanceof Model && Registration::allows($user), 403);

                (new Register)((string) $data['name'], mb_strtolower((string) $data['slug']), $user);

                return $data;
            });
    }

    public function save(): mixed
    {
        $this->form->save();

        return $this->redirect(Registration::homeOf(mb_strtolower((string) ($this->data['slug'] ?? ''))));
    }

    public function render(): View
    {
        return view('wire-module-tenants::register');
    }
}
