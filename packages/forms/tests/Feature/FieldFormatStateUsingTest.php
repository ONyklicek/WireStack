<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\DateTimePicker;
use NyonCode\WireForms\Components\Display\Placeholder;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * formatStateUsing() on a field: the read-path counterpart of
 * dehydrateStateUsing(), and the same word a table column and an infolist entry
 * already used for the same job. It shapes a stored value into the state the
 * input binds to, after the field type's own hydration.
 */

class FsuTicket extends Model
{
    protected $table = 'fsu_tickets';

    protected $guarded = [];

    public $timestamps = false;
}

class FsuHost extends Component
{
    use WithForms;

    public ?int $ticketId = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $record = $this->ticketId !== null ? FsuTicket::find($this->ticketId) : null;

        $this->form->fill($record?->attributesToArray() ?? []);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->ticketId !== null ? FsuTicket::find($this->ticketId) : FsuTicket::class)
            ->statePath('data')
            ->schema([
                // A display component has no state of its own; the fill pass steps over it.
                Placeholder::make('note')->content('Read only'),
                TextInput::make('code')->formatStateUsing(
                    static fn (mixed $state, ?FsuTicket $record): string => mb_strtoupper((string) $state).'/'.$record?->getKey(),
                ),
                DateTimePicker::make('opened_at')
                    ->format('Y-m-d H:i:s')
                    ->displayFormat('Y-m-d')
                    // Runs over what the picker already hydrated, not over the raw column.
                    ->formatStateUsing(static fn (mixed $state): string => 'at '.$state),
            ]);
    }

    public function render()
    {
        return '<div></div>';
    }
}

beforeEach(function () {
    Schema::create('fsu_tickets', function (Blueprint $t) {
        $t->id();
        $t->string('code');
        $t->string('opened_at')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('fsu_tickets');
});

it('formats a stored value into the state the input binds to', function () {
    $ticket = FsuTicket::create(['code' => 'abc-1', 'opened_at' => '2026-03-04 15:30:00']);

    $state = Livewire::test(FsuHost::class, ['ticketId' => $ticket->id])->get('data');

    expect($state['code'])->toBe('ABC-1/'.$ticket->id);
});

it('runs after the field own hydration rather than over the raw column', function () {
    $ticket = FsuTicket::create(['code' => 'abc-1', 'opened_at' => '2026-03-04 15:30:00']);

    $state = Livewire::test(FsuHost::class, ['ticketId' => $ticket->id])->get('data');

    // The picker hydrated '2026-03-04 15:30:00' into its own typed state first;
    // the owner callback then saw that, not the raw column value.
    expect($state['opened_at'])->toBe('at 2026-03-04T15:30');
});
