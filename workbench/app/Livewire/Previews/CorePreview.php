<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Attributes\On;
use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\ViewAction;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationAction;
use NyonCode\WireCore\Notifications\NotificationManager;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;
use Workbench\App\Models\User;

class CorePreview extends Component
{
    public string $variant = 'overview';

    public bool $showPreviewModal = false;

    public function mount(string $variant = 'overview'): void
    {
        $this->variant = $variant;
        $this->showPreviewModal = $variant === 'modal';
    }

    public function closePreviewModal(): void
    {
        $this->showPreviewModal = false;
    }

    // ─── open-on preview: JS-opened modal shells + a server roundtrip ───────

    public int $serverTicks = 0;

    public function tick(): void
    {
        $this->serverTicks++;
    }

    // ─── Toasts preview: server-side notification API ──────────────────────

    /**
     * Fires a toast straight through the manager WITHOUT passing $this — proving
     * the default CurrentComponentDriver resolves the active Livewire component.
     */
    public function sendServerToast(): void
    {
        NotificationManager::send(
            Notification::success('Order #1042 saved')
                ->title('Saved')
                ->action(NotificationAction::make('Undo', 'demo-undo')->color('primary'))
        );
    }

    public function sendPersistentToast(): void
    {
        NotificationManager::send(
            Notification::warning('Payment needs review before it can settle.')
                ->title('Action required')
                ->persistent()
                ->action('Review now', 'demo-undo')
        );
    }

    #[On('demo-undo')]
    public function demoUndo(): void
    {
        NotificationManager::send(Notification::info('Change reverted'));
    }

    public function render()
    {
        if ($this->variant === 'toasts') {
            return view('livewire.previews.core-toasts');
        }

        if ($this->variant === 'open-on') {
            return view('livewire.previews.core-open-on');
        }

        $record = User::query()->firstOrFail();

        $stats = StatsOverviewWidget::make()
            ->columns(3)
            ->stats([
                Stat::make('Open actions', '18')
                    ->description('+4 this week')
                    ->descriptionIcon('arrow-trending-up')
                    ->color('success')
                    ->icon('bolt'),
                Stat::make('Queued exports', '6')
                    ->description('2 waiting for review')
                    ->descriptionIcon('clock')
                    ->color('warning')
                    ->icon('arrow-down-tray'),
                Stat::make('Plugin hooks', '34')
                    ->description('All listeners healthy')
                    ->descriptionIcon('check')
                    ->color('primary')
                    ->icon('puzzle-piece'),
            ]);

        $actions = [
            Action::make('create')
                ->label('New action')
                ->icon('plus')
                ->color('primary'),
            ViewAction::make()
                ->label('Inspect')
                ->outlined(),
            DeleteAction::make()
                ->label('Archive')
                ->color('danger'),
        ];

        $footerActions = [
            Action::make('save')
                ->label('Save changes')
                ->icon('check')
                ->color('primary'),
            Action::make('cancel')
                ->label('Cancel')
                ->outlined()
                ->color('gray'),
        ];

        return view('livewire.previews.core-preview', [
            'record' => $record,
            'stats' => $stats,
            'actions' => $actions,
            'footerActions' => $footerActions,
        ]);
    }
}
