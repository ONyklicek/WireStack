<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Panels;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use NyonCode\WireCore\Panels\Concerns\WithEditablePanel;

/**
 * Base Livewire component for a standalone editable panel ("record panel").
 *
 * Extend it, hold your record (typically a public Eloquent property, which
 * Livewire re-hydrates from the database each request), and return the schema
 * from {@see panel()} with the record bound:
 *
 *   class EditOrderPanel extends PanelComponent
 *   {
 *       public Order $order;
 *
 *       public function panel(): Panel
 *       {
 *           return Panel::make()
 *               ->record($this->order)
 *               ->columns(2)
 *               ->schema([
 *                   ToggleEntry::make('is_paid')->label('Paid'),
 *                   SelectEntry::make('status')->options(OrderStatus::class),
 *                   TextEntry::make('reference'), // read-only infolist entry, mixed freely
 *               ]);
 *       }
 *   }
 *
 * Editable entries write directly to the record with optimistic UI + optimistic
 * locking through {@see WithEditablePanel::updatePanelEntry()}.
 */
abstract class PanelComponent extends Component
{
    use WithEditablePanel;

    public function render(): View
    {
        return view('wire-core::panels.livewire', [
            'panel' => $this->panel(),
        ]);
    }
}
