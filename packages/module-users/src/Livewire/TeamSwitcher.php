<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use NyonCode\WireCore\Core\Plugin\Contracts\IdentifiesHookTarget;
use NyonCode\WireCore\Notifications\NotificationManager;
use NyonCode\WireModuleUsers\Support\Teams;

/**
 * The control in the top bar that says which team you are looking at.
 *
 * It reaches the chrome through `PageChrome::TOPBAR` rather than by editing the
 * shell's layout, which is the same seam the media picker uses at the other end
 * of the document: the shell sits above every module and cannot name their
 * views, and a module cannot reach into the shell's Blade.
 *
 * Switching redirects rather than re-rendering, and that is not laziness. The
 * team scopes every permission read on the request, so a page composed *before*
 * the switch — its menu, its actions, the rows a policy let through — is a page
 * built for the team you just left. A fresh request is the only honest answer.
 */
class TeamSwitcher extends Component implements IdentifiesHookTarget
{
    public int|string|null $current = null;

    public function mount(): void
    {
        $this->current = Teams::currentId();
    }

    public function switchTo(int|string $team): void
    {
        if (! Teams::switchTo($team)) {
            // Not a member, or teams are off: the control said otherwise, and
            // the control is markup.
            return;
        }

        $this->current = $team;

        NotificationManager::success(__('wire-module-users::messages.team_switched', [
            'team' => Teams::optionsFor()[$team] ?? '',
        ]));

        $this->redirect(request()->header('Referer') ?? url('/'), navigate: false);
    }

    public function hookKey(): ?string
    {
        return 'users.team-switcher';
    }

    public function render(): View
    {
        $teams = Teams::optionsFor();

        return view('wire-module-users::livewire.team-switcher', [
            'teams' => $teams,
            'currentLabel' => $teams[$this->current] ?? null,
        ]);
    }
}
