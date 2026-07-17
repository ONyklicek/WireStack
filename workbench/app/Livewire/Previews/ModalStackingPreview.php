<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ModalFooterAction;
use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Concerns\WithActions;

/**
 * Live preview of the nested-modal *live frame stack* — a standalone WithActions
 * host exposing six real configurations (basic stacking, create-and-select via
 * $setParent, deep $setFrame, inline registerActions, a slide-over parent, and a
 * stacked wizard). Each card mounts a real action; the modal-host renders the
 * whole live stack. Mirrors the shipped artifact, but this is the real runtime.
 */
class ModalStackingPreview extends Component
{
    use WithActions;

    public string $variant = 'gallery';

    /** Customers created inline during the create-and-select demos (feeds the Select). */
    public array $addedCustomers = [];

    public function mount(string $variant = 'gallery'): void
    {
        $this->variant = $variant;
    }

    /**
     * Config cards rendered by the view. Each entry mounts a real action.
     *
     * @return array<int, array<string, string>>
     */
    public function configs(): array
    {
        return [
            ['idx' => '01', 'entry' => 'stackBasic', 'name' => 'Stack a nested modal', 'desc' => 'The parent stays open, live, behind the child.'],
            ['idx' => '02', 'entry' => 'stackReturn', 'name' => 'Create & select', 'desc' => 'A sub-form fills a field on its parent — $setParent.'],
            ['idx' => '03', 'entry' => 'stackDeep', 'name' => 'Deep + $setFrame', 'desc' => 'Three levels; the deepest writes into the root.'],
            ['idx' => '04', 'entry' => 'stackInline', 'name' => 'Inline nested', 'desc' => 'Child declared inline via registerActions().'],
            ['idx' => '05', 'entry' => 'stackPanel', 'name' => 'Slide-over parent', 'desc' => 'A modal stacked over a slide-over.'],
            ['idx' => '06', 'entry' => 'stackTeam', 'name' => 'Wizard in the stack', 'desc' => 'A multi-step wizard stacked on a parent.'],
            ['idx' => '07', 'entry' => 'stackReplace', 'name' => 'Replace & cancel', 'desc' => 'Swap a modal in place, or cancel the whole stack.'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function customerOptions(): array
    {
        $names = array_values(array_unique(array_merge(['Alan Turing', 'Grace Hopper'], $this->addedCustomers)));

        return array_combine($names, $names);
    }

    /**
     * @return array<int, Action>
     */
    protected function actions(): array
    {
        return [
            // 01 — basic stacking
            Action::make('stackBasic')
                ->modalHeading('Edit order')->modalDescription('#ORD-1042 · basic stacking')
                ->form([
                    TextInput::make('reference')->default('ORD-1042'),
                    Select::make('priority')->options(['normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'])->default('normal'),
                ])
                ->modalFooterActions([
                    ModalFooterAction::make('addNote')->label('Add internal note')
                        ->action(fn ($component) => $component->mountAction('stackNote')),
                ])
                ->successNotification('Order saved')->action(fn () => null),
            Action::make('stackNote')
                ->modalHeading('Add note')->modalDescription('Stacked on top of “Edit order”.')
                ->form([
                    Textarea::make('note')->rows(3)->placeholder('Follow up with the customer…'),
                    // createOptionForm from a Select that is itself inside a stacked
                    // (depth 1) action modal — must render ABOVE this modal, not behind.
                    Select::make('assignee')->searchable()
                        ->options(fn () => $this->customerOptions())->placeholder('— nobody —')
                        ->createOptionForm([TextInput::make('name')->required()])
                        ->createOptionUsing(function (array $data) {
                            $name = trim((string) ($data['name'] ?? '')) ?: 'New assignee';
                            $this->addedCustomers[] = $name;

                            return $name;
                        }),
                ])
                ->action(fn () => null),

            // 02 — create-and-select ($setParent)
            Action::make('stackReturn')
                ->modalHeading('Edit order')->modalDescription('Pick a customer, or create one inline')
                ->form([
                    TextInput::make('reference')->default('ORD-1042'),
                    Select::make('customer')->options(fn () => $this->customerOptions())->placeholder('— none —'),
                ])
                ->modalFooterActions([
                    ModalFooterAction::make('newCustomer')->label('+ New customer')
                        ->action(fn ($component) => $component->mountAction('stackCreateCustomer')),
                ])
                ->successNotification('Order saved')->action(fn () => null),
            Action::make('stackCreateCustomer')
                ->modalHeading('Create customer')->modalDescription('Stacked over the order')
                ->form([TextInput::make('name')->required(), TextInput::make('email')->placeholder('ada@analytical.engine')])
                ->action(function (array $data, $setParent, $component) {
                    $name = trim((string) ($data['name'] ?? '')) ?: 'New customer';
                    $component->addedCustomers[] = $name;
                    $setParent('customer', $name);   // land it on the parent's Select, then close
                }),

            // 03 — deep stacking + $setFrame(0, …)
            Action::make('stackDeep')
                ->modalHeading('Edit order')->modalDescription('depth 0 — the root')
                ->form([
                    TextInput::make('reference')->default('ORD-1042'),
                    TextInput::make('tax')->label('Tax rate')->readOnly()->placeholder('— not set —'),
                ])
                ->modalFooterActions([
                    ModalFooterAction::make('addLine')->label('Add line item')
                        ->action(fn ($component) => $component->mountAction('stackAddLine')),
                ])
                ->successNotification('Order saved')->action(fn () => null),
            Action::make('stackAddLine')
                ->modalHeading('Add line item')->modalDescription('depth 1')
                ->form([TextInput::make('sku')->placeholder('WID-9'), TextInput::make('qty')->default('1')])
                ->modalFooterActions([
                    ModalFooterAction::make('applyTax')->label('Apply tax…')
                        ->action(fn ($component) => $component->mountAction('stackApplyTax')),
                ])
                ->action(fn () => null),
            Action::make('stackApplyTax')
                ->modalHeading('Apply tax')->modalDescription('depth 2 — writes the root')
                ->form([Select::make('rate')->options(['0%' => '0%', '15%' => '15%', '21%' => '21%'])->default('21%')])
                ->action(fn (array $data, $setFrame) => $setFrame(0, 'tax', $data['rate'] ?? '21%')),

            // 04 — inline nested action (registerActions)
            Action::make('stackInline')
                ->modalHeading('Edit order')->modalDescription('nested action declared inline')
                ->form([
                    TextInput::make('reference')->default('ORD-1042'),
                    Select::make('customer')->options(fn () => $this->customerOptions())->placeholder('— none —'),
                ])
                ->registerActions([
                    Action::make('stackInlineChild')
                        ->modalHeading('Create customer')->modalDescription('declared with registerActions()')
                        ->form([TextInput::make('name')->required()])
                        ->action(function (array $data, $setParent, $component) {
                            $name = trim((string) ($data['name'] ?? '')) ?: 'New customer';
                            $component->addedCustomers[] = $name;
                            $setParent('customer', $name);
                        }),
                ])
                ->modalFooterActions([
                    ModalFooterAction::make('newCustomer')->label('+ New customer')
                        ->action(fn ($component) => $component->mountAction('stackInlineChild')),
                ])
                ->successNotification('Order saved')->action(fn () => null),

            // 05 — slide-over parent with a modal stacked over it
            Action::make('stackPanel')
                ->modalHeading('Order details')->modalDescription('slide-over · depth 0')->slideOver()
                ->form([
                    TextInput::make('reference')->default('ORD-1042'),
                    Select::make('status')->options(['open' => 'Open', 'packed' => 'Packed', 'shipped' => 'Shipped'])->default('open'),
                    TextInput::make('carrier')->readOnly()->placeholder('— not set —'),
                ])
                ->modalFooterActions([
                    ModalFooterAction::make('pickCarrier')->label('Choose carrier')
                        ->action(fn ($component) => $component->mountAction('stackCarrier')),
                ])
                ->successNotification('Saved')->action(fn () => null),
            Action::make('stackCarrier')
                ->modalHeading('Choose carrier')->modalDescription('modal over the slide-over')
                ->form([Select::make('carrier')->options(['DHL' => 'DHL', 'FedEx' => 'FedEx', 'PPL' => 'PPL'])->default('DHL')])
                ->action(fn (array $data, $setParent) => $setParent('carrier', $data['carrier'] ?? 'DHL')),

            // 06 — a wizard, stacked
            Action::make('stackTeam')
                ->modalHeading('Team')->modalDescription('Invite a member')
                ->form([TextInput::make('lastInvite')->label('Last invited')->readOnly()->placeholder('— none —')])
                ->modalFooterActions([
                    ModalFooterAction::make('invite')->label('Invite member')
                        ->action(fn ($component) => $component->mountAction('stackInvite')),
                ])
                ->action(fn () => null),
            Action::make('stackInvite')
                ->modalHeading('Invite member')->modalDescription('2-step wizard · stacked')
                ->steps([
                    ModalStep::make('Person')->schema([TextInput::make('name')->required(), TextInput::make('email')]),
                    ModalStep::make('Access')->schema([Select::make('role')->options(['viewer' => 'Viewer', 'editor' => 'Editor', 'admin' => 'Admin'])->default('editor')]),
                ])
                ->action(fn (array $data, $setParent) => $setParent('lastInvite', ($data['name'] ?? 'New member').' ('.($data['role'] ?? 'editor').')')),

            // 07 — replaceMountedAction ($replace) + cancelParentActions ($cancelParents)
            Action::make('stackReplace')
                ->modalHeading('Edit order')->modalDescription('#ORD-1042 · replace & cancel')
                ->form([TextInput::make('reference')->default('ORD-1042')])
                ->modalFooterActions([
                    // Swaps THIS modal for the confirm dialog in place — no new layer.
                    ModalFooterAction::make('archive')->label('Archive…')
                        ->action(fn ($replace) => $replace('stackConfirmArchive')),
                    // Stacks a child so "Discard all" below has a stack to tear down.
                    ModalFooterAction::make('addLine')->label('Add line item')
                        ->action(fn ($component) => $component->mountAction('stackReplaceChild')),
                ])
                ->successNotification('Order saved')->action(fn () => null),
            Action::make('stackConfirmArchive')
                ->modalHeading('Archive this order?')->modalDescription('Replaced “Edit order” in place — still depth 0')
                ->form([Textarea::make('reason')->rows(2)->placeholder('Optional reason…')])
                ->successNotification('Order archived')->action(fn () => null),
            Action::make('stackReplaceChild')
                ->modalHeading('Add line item')->modalDescription('depth 1 — try “Discard all”')
                ->form([TextInput::make('sku')->placeholder('WID-9')])
                ->modalFooterActions([
                    // Closes this child AND its parent — the whole stack at once.
                    ModalFooterAction::make('discardAll')->label('Discard all')
                        ->action(fn ($cancelParents) => $cancelParents()),
                ])
                ->action(fn () => null),
        ];
    }

    public function render()
    {
        return view('livewire.previews.modal-stacking-preview');
    }
}
