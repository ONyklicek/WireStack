<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use NyonCode\WireModuleUsers\Support\Impersonation;

/**
 * The way back, in the top bar for as long as somebody is acting as another
 * account: who they are signed in as, and one button to return.
 *
 * A redirect rather than a re-render, for the reason the team switcher gives:
 * every permission on the page was read for the account being left.
 */
class ImpersonationBanner extends Component
{
    public function stop(): mixed
    {
        Impersonation::stop();

        return $this->redirect((string) config('wire-module-users.impersonation.return_to', '/'));
    }

    public function render(): View
    {
        return view('wire-module-users::livewire.impersonation-banner', [
            'name' => (string) (auth()->user()?->getAttribute('name') ?? auth()->user()?->getAuthIdentifier() ?? ''),
        ]);
    }
}
