<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\Concerns\InteractsWithActions;
use NyonCode\WireCore\Actions\ModalFooterAction;
use NyonCode\WireCore\Core\Actions\ActionResult;

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
});

/**
 * A minimal record used to exercise the form-agnostic pipeline's record-id
 * collection without a database connection.
 */
class CoreActionRecord extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

/**
 * A form-FREE host that composes ONLY the wire-core engine (no wire-forms
 * bridge). This proves actions work without wire-forms (AI_BLUEPRINT §8) and
 * exercises the engine's default extension points that both real hosts override.
 */
class CoreActionsHost extends Component
{
    use InteractsWithActions;

    /** @var array<int, array<string, mixed>> */
    public array $mountedActions = [];

    /** @var array<string, mixed> */
    public array $haltState = [];

    public string $log = '';

    /** @return array<string, Action> */
    protected function catalog(): array
    {
        return [
            'notify' => Action::make('notify')->action(fn () => ActionResult::success('Saved')),
            'configNotify' => Action::make('configNotify')->successNotification('Configured')->action(fn () => null),
            'configFailNotify' => Action::make('configFailNotify')->failureNotification('Failed')->action(fn () => new ActionResult(success: false)),
            'redirect' => Action::make('redirect')->successRedirect('/done')->action(fn () => null),
            'resultRedirect' => Action::make('resultRedirect')->action(fn () => ActionResult::redirect('/gone')),
            'halts' => Action::make('halts')->before(fn ($action) => $action->halt())->action(fn () => $this->log = 'ran'),
            'afterCb' => Action::make('afterCb')->after(fn () => $this->log .= ':after')->action(fn () => $this->log = 'ran'),
            'afterHalts' => Action::make('afterHalts')->after(fn ($action) => $action->halt())->action(fn () => $this->log = 'ran'),
            'beforeNoHalt' => Action::make('beforeNoHalt')->before(fn () => null)->action(fn () => $this->log = 'ran'),
            'noop' => Action::make('noop'),
            'defaults' => Action::make('defaults')->action(fn ($record = null, $extra = 'def') => $this->log = (string) $extra),
            'purge' => BulkAction::make('purge')->deselectRecordsAfterCompletion()->action(fn () => null),
            'footer' => Action::make('footer')
                ->requiresConfirmation()
                ->modalFooterActions([
                    ModalFooterAction::make('touch')->submitsForm()->action(fn ($set) => $set('touched', true)),
                ])
                ->action(fn () => null),
        ];
    }

    // ── engine frame seams (plain frames-array storage) ──
    protected function actionFrameCount(): int
    {
        return count($this->mountedActions);
    }

    protected function pushActionFrame(array $frame): void
    {
        $this->mountedActions[] = $frame;
        $this->actionStackVersion++;
        $this->actionModalConfigCache = [];
    }

    protected function popActionFrame(): void
    {
        array_pop($this->mountedActions);
        $this->actionStackVersion++;
        $this->actionModalConfigCache = [];
    }

    protected function mountActionByName(string $name, array $arguments): void
    {
        $action = $this->catalog()[$name] ?? null;

        if ($action === null) {
            return;
        }

        $this->pushActionFrame([
            'name' => $name,
            'currentStep' => 0,
            'isBulk' => false,
            'isHeaderAction' => true,
            'record' => null,
            'arguments' => $arguments,
            'data' => [],
        ]);
    }

    protected function actionFrameStatePath(int $depth): string
    {
        return "mountedActions.{$depth}.data";
    }

    protected function getActionFrameState(int $depth, string $key, mixed $default = null): mixed
    {
        return $this->mountedActions[$depth][$key] ?? $default;
    }

    protected function setActionFrameState(int $depth, string $key, mixed $value): void
    {
        if (isset($this->mountedActions[$depth])) {
            $this->mountedActions[$depth][$key] = $value;
        }
    }

    protected function readActionFrameData(int $depth): array
    {
        $data = $this->mountedActions[$depth]['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    protected function setActionFrameData(int $depth, array $data): void
    {
        if (isset($this->mountedActions[$depth])) {
            $this->mountedActions[$depth]['data'] = $data;
        }
    }

    protected function writeActionFrameData(int $depth, string $path, mixed $value): void
    {
        if (! isset($this->mountedActions[$depth])) {
            return;
        }

        $data = $this->readActionFrameData($depth);
        data_set($data, $path, $value);
        $this->mountedActions[$depth]['data'] = $data;
    }

    protected function setHaltModalState(string $key, mixed $value): void
    {
        $this->haltState[$key] = $value;
    }

    protected function resolveActionForFrame(int $depth): array
    {
        $name = $this->mountedActions[$depth]['name'] ?? null;

        return [$name ? ($this->catalog()[$name] ?? null) : null, null];
    }

    // ── test triggers ──
    public function fire(string $name): void
    {
        $this->executeActionPipeline($this->catalog()[$name], ['record' => new CoreActionRecord(['id' => 1]), 'data' => []], '1', 'row');
    }

    public function fireBulk(string $name = 'notify'): void
    {
        $records = collect([new CoreActionRecord(['id' => 1]), new CoreActionRecord(['id' => 2])]);

        $this->executeActionPipeline($this->catalog()[$name], ['records' => $records, 'data' => []], '__bulk__', 'bulk');
    }

    public function deselectAllRecords(): void
    {
        $this->log = 'deselected';
    }

    public function openFooter(): void
    {
        $this->mountedActions = [['name' => 'footer', 'show' => true, 'data' => []]];
        $this->actionModalConfigCache = $this->catalog()['footer']->getModalConfig();
    }

    /** Mounts a name that resolves to no action, then reads the modal config. */
    public function peekGhostConfig(): void
    {
        $this->mountedActions = [['name' => 'ghost', 'show' => true, 'data' => []]];
        $this->actionModalConfigCache = [];
        $this->getActionModalData();
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

it('runs a form-free action host with no wire-forms bridge and fires after hooks', function () {
    Livewire::test(CoreActionsHost::class)
        ->call('fire', 'afterCb')
        ->assertSet('log', 'ran:after');
});

it('resolves callback parameters by name and falls back to defaults', function () {
    Livewire::test(CoreActionsHost::class)
        ->call('fire', 'defaults')
        ->assertSet('log', 'def');
});

it('halts from a before() callback and opens the halt modal (no form bridge)', function () {
    Livewire::test(CoreActionsHost::class)
        ->call('fire', 'halts')
        ->assertSet('haltState.show', true)
        ->assertSet('log', '');
});

it('sends a notification from the action result through the default driver', function () {
    // The result carries a notification, which the NotificationStage lifts into
    // the context and the engine's default sendActionNotification() delivers.
    Livewire::test(CoreActionsHost::class)
        ->call('fire', 'notify')
        ->assertDispatched('table-notification');
});

it('fires the declarative success notification config', function () {
    Livewire::test(CoreActionsHost::class)
        ->call('fire', 'configNotify')
        ->assertDispatched('table-notification');
});

it('fires the declarative failure notification config on a failed result', function () {
    Livewire::test(CoreActionsHost::class)
        ->call('fire', 'configFailNotify')
        ->assertDispatched('table-notification');
});

it('redirects from the success-redirect config', function () {
    Livewire::test(CoreActionsHost::class)
        ->call('fire', 'redirect')
        ->assertRedirect('/done');
});

it('redirects from a redirect action result', function () {
    Livewire::test(CoreActionsHost::class)
        ->call('fire', 'resultRedirect')
        ->assertRedirect('/gone');
});

it('collects record ids for a bulk payload', function () {
    Livewire::test(CoreActionsHost::class)
        ->call('fireBulk')
        ->assertSet('log', '');
});

it('deselects records after a bulk action that opts in', function () {
    Livewire::test(CoreActionsHost::class)
        ->call('fireBulk', 'purge')
        ->assertSet('log', 'deselected');
});

it('runs a before() hook that does not halt', function () {
    Livewire::test(CoreActionsHost::class)
        ->call('fire', 'beforeNoHalt')
        ->assertSet('log', 'ran');
});

it('halts from an after() hook once the action has already run', function () {
    Livewire::test(CoreActionsHost::class)
        ->call('fire', 'afterHalts')
        ->assertSet('log', 'ran')
        ->assertSet('haltState.show', true);
});

it('succeeds for an action with no callback', function () {
    Livewire::test(CoreActionsHost::class)
        ->call('fire', 'noop')
        ->assertSet('log', '');
});

it('runs a modal footer action validating through the no-op form seam', function () {
    Livewire::test(CoreActionsHost::class)
        ->call('openFooter')
        ->call('callModalFooterAction', 'touch')
        ->assertSet('mountedActions.0.data.touched', true);
});

it('reports modal visibility and step index from the engine', function () {
    $component = Livewire::test(CoreActionsHost::class)
        ->call('openFooter')
        ->assertSet('mountedActions.0.show', true);

    expect($component->instance()->isActionModalVisible())->toBeTrue()
        ->and($component->instance()->getMountedActionStepIndex())->toBe(0);
});

it('skips modal config regeneration when the mounted name resolves to no action', function () {
    Livewire::test(CoreActionsHost::class)
        ->call('peekGhostConfig')
        ->assertSet('mountedActions.0.name', 'ghost');
});
