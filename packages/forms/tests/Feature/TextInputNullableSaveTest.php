<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * What a cleared TextInput writes.
 *
 * A browser has no way to submit "nothing": an emptied <input> arrives as ''.
 * On a numeric column that is not a value at all — Postgres and strict-mode
 * MySQL reject it, SQLite quietly stores an empty string next to the decimals —
 * so a number input nullifies on its own. A text column is the opposite case:
 * '' is a value an author may mean, and a NOT NULL column would reject a null
 * written on their behalf, so there it takes ->nullable() to ask for it.
 */

class TextInputNullableProduct extends Model
{
    protected $table = 'text_input_nullable_products';

    protected $guarded = [];

    public $timestamps = false;
}

class TextInputNullableHost extends Component
{
    use WithForms;

    public ?TextInputNullableProduct $record = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(int $id): void
    {
        $this->record = TextInputNullableProduct::query()->find($id);
        $this->form->model($this->record)->fill($this->record->attributesToArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model($this->record)
            ->schema([
                TextInput::make('price')->numeric(),
                TextInput::make('quantity')->integer(),
                TextInput::make('note'),
                TextInput::make('reference')->nullable(),
            ]);
    }

    public function save(): void
    {
        $this->form->save();
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

beforeEach(function () {
    Schema::dropIfExists('text_input_nullable_products');
    Schema::create('text_input_nullable_products', function (Blueprint $table): void {
        $table->id();
        $table->decimal('price', 10, 2)->nullable();
        $table->integer('quantity')->nullable();
        $table->string('note')->nullable();
        $table->string('reference')->nullable();
    });
});

function textInputNullableProduct(): TextInputNullableProduct
{
    return TextInputNullableProduct::query()->create([
        'price' => 10.5,
        'quantity' => 3,
        'note' => 'hello',
        'reference' => 'REF-1',
    ]);
}

/** The raw column, not the model's — a cast would hide an empty string as 0.0. */
function textInputNullableColumn(TextInputNullableProduct $product, string $column): mixed
{
    return DB::table('text_input_nullable_products')->where('id', $product->id)->value($column);
}

it('writes null, not an empty string, when a numeric input is cleared', function () {
    $product = textInputNullableProduct();

    Livewire::test(TextInputNullableHost::class, ['id' => $product->id])
        ->set('data.price', '')
        ->call('save');

    expect(textInputNullableColumn($product, 'price'))->toBeNull();
});

it('writes null when an integer input is cleared', function () {
    $product = textInputNullableProduct();

    Livewire::test(TextInputNullableHost::class, ['id' => $product->id])
        ->set('data.quantity', '')
        ->call('save');

    expect(textInputNullableColumn($product, 'quantity'))->toBeNull();
});

it('keeps a figure a numeric input still holds', function () {
    $product = textInputNullableProduct();

    Livewire::test(TextInputNullableHost::class, ['id' => $product->id])
        ->set('data.price', '12.30')
        ->call('save');

    expect((float) textInputNullableColumn($product, 'price'))->toBe(12.30);
});

it('keeps a zero, which is a figure and not an empty value', function () {
    $product = textInputNullableProduct();

    Livewire::test(TextInputNullableHost::class, ['id' => $product->id])
        ->set('data.quantity', '0')
        ->call('save');

    expect(textInputNullableColumn($product, 'quantity'))->not->toBeNull()
        ->and((int) textInputNullableColumn($product, 'quantity'))->toBe(0);
});

it('leaves a cleared text input as an empty string, which is a value there', function () {
    $product = textInputNullableProduct();

    Livewire::test(TextInputNullableHost::class, ['id' => $product->id])
        ->set('data.note', '')
        ->call('save');

    expect(textInputNullableColumn($product, 'note'))->toBe('');
});

it('writes null for a cleared text input that asked for it with nullable()', function () {
    $product = textInputNullableProduct();

    Livewire::test(TextInputNullableHost::class, ['id' => $product->id])
        ->set('data.reference', '')
        ->call('save');

    expect(textInputNullableColumn($product, 'reference'))->toBeNull();
});

it('nullifies for a type set directly, not only for the numeric() preset', function () {
    expect(TextInput::make('price')->type('number')->dehydrateState(''))->toBeNull();
});

it('leaves every other value alone', function () {
    $field = TextInput::make('note')->nullable();

    expect($field->dehydrateState(null))->toBeNull()
        ->and($field->dehydrateState('0'))->toBe('0')
        ->and($field->dehydrateState([]))->toBe([]);
});

it('reports whether it was asked to store null', function () {
    expect(TextInput::make('note')->isNullable())->toBeFalse()
        ->and(TextInput::make('note')->nullable()->isNullable())->toBeTrue()
        ->and(TextInput::make('note')->nullable(false)->isNullable())->toBeFalse();
});
