<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\DateTimePicker;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * Regressions in the relationship-repeater save path:
 *  #4 an unruled child field was dropped by validate() (only ruled children got
 *     a wildcard rule, unlike top-level fields which fall back to ['nullable']).
 *  #5 a child DehydratesState field (DateTimePicker/FileUpload) was never
 *     dehydrated (dehydrateFields matched only top-level keys), so its
 *     format/timezone / temp-file-storage step never ran.
 */

class RcsOrder extends Model
{
    protected $table = 'rcs_orders';

    protected $guarded = [];

    public $timestamps = false;

    public function lines(): HasMany
    {
        return $this->hasMany(RcsLine::class, 'order_id');
    }
}

class RcsLine extends Model
{
    protected $table = 'rcs_lines';

    protected $guarded = [];

    public $timestamps = false;
}

class RcsHost extends Component
{
    use WithForms;

    public ?int $orderId = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->model(RcsOrder::find($this->orderId) ?? RcsOrder::class)
            ->statePath('data')
            ->schema([
                Repeater::make('lines')->relationship('lines')->schema([
                    TextInput::make('label')->required(),
                    TextInput::make('note'),                      // #4: no rule
                    DateTimePicker::make('due_on')->format('Y-m-d'), // #5: child dehydration
                ]),
            ]);
    }

    public function save(): void
    {
        $this->form->save();
    }

    public function render()
    {
        return '<div></div>';
    }
}

beforeEach(function () {
    Schema::create('rcs_orders', function (Blueprint $t) {
        $t->id();
    });
    Schema::create('rcs_lines', function (Blueprint $t) {
        $t->id();
        $t->foreignId('order_id');
        $t->string('label');
        $t->string('note')->nullable();
        $t->date('due_on')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('rcs_lines');
    Schema::dropIfExists('rcs_orders');
});

it('persists an unruled repeater child field instead of dropping it', function () {
    Livewire::test(RcsHost::class)
        ->set('data.lines', [
            ['label' => 'First', 'note' => 'keep me', 'due_on' => '2026-03-09T14:30'],
        ])
        ->call('save');

    $line = RcsLine::first();

    expect($line)->not->toBeNull()
        ->and($line->label)->toBe('First')
        // #4: the unruled `note` survives validation.
        ->and($line->note)->toBe('keep me');
});

it('dehydrates a repeater child date field to its configured format', function () {
    Livewire::test(RcsHost::class)
        ->set('data.lines', [
            ['label' => 'First', 'note' => null, 'due_on' => '2026-03-09T14:30'],
        ])
        ->call('save');

    // #5: raw column stores the formatted date, not the raw wire value.
    $raw = DB::table('rcs_lines')->value('due_on');

    expect((string) $raw)->toStartWith('2026-03-09');
});
