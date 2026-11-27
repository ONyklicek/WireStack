<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireForms\Components\Display\Alert;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Concerns\WithActions;
use RuntimeException;

/**
 * A record page in the shape a panel's view page has: a key in a public
 * `$record`, resolved on demand, refusing to build without one — and actions in
 * every place a page keeps them, each with a form worth looking into.
 */
class PostPage extends Component
{
    use WithActions;

    public mixed $record = null;

    /** @var array<int, mixed> What each action form was handed as its record. */
    public static array $formContexts = [];

    public function infolist(Infolist $infolist): Infolist
    {
        $infolist->record($this->resolveRecord());

        return $infolist->schema([TextEntry::make('title')]);
    }

    protected function resolveRecord(): Post
    {
        if ($this->record === null) {
            throw new RuntimeException('[PostPage] was mounted without a record.');
        }

        return Post::query()->findOrFail($this->record);
    }

    /** @return array<int, Action|ActionGroup> */
    public function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->icon('heroicon-o-nope')
                ->form(function ($record): array {
                    static::$formContexts[] = $record;

                    return [
                        TextInput::make('note'),
                        Alert::make('hint')->color('purplish')->content('Publishing notifies subscribers.'),
                    ];
                })
                ->action(fn () => null),

            ActionGroup::make([
                Action::make('grouped')
                    ->form([Alert::make('careful')->icon('heroicon-o-not-registered')->content('!')])
                    ->action(fn () => null),
            ]),
        ];
    }

    protected function actions(): array
    {
        return [
            Action::make('onboard')
                ->steps([
                    ModalStep::make('Account')->schema([TextInput::make('email')]),
                    ModalStep::make('Plan')->schema([Alert::make('plan')->color('blu')->content('Pick one')]),
                ])
                ->action(fn () => null),

            // Its form calls a service that is not there outside a request.
            Action::make('dns')
                ->form(fn (): array => throw new RuntimeException('agent unreachable'))
                ->action(fn () => null),

            Action::make('archive')->action(fn () => null),
        ];
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
