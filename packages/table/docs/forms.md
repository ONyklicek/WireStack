# Form Fields

Form fields are used inside action modals and multi-step wizards. All fields extend the base `Field` class.

## Base Field

Every field shares these methods:

```php
use NyonCode\WireForms\Components\TextInput;

TextInput::make('name')
    ->label('Full Name')
    ->placeholder('Enter your name')
    ->default('John Doe')
    ->default(fn () => auth()->user()->name)   // Closure support
    ->required()
    ->disabled()
    ->disabled(fn () => ! auth()->user()->isAdmin())
    ->readonly()
    ->autofocus()
    ->helperText('This will be displayed publicly.')
    ->hint('Max 255 characters', icon: 'info')
    ->prefix('Mr.')
    ->suffix('@example.com')
    ->prefixIcon('user')
    ->suffixIcon('mail')
    ->columnSpan(1)                    // Grid column span (1 or 2)
    ->columnSpanFull()                 // Full width (span 2)
    ->hidden()
    ->hidden(fn () => $someCondition)
    ->visible()
    ->rules(['required', 'string', 'max:255'])
    ->rules('required|string|max:255') // String syntax
    ->extraAttributes(['data-testid' => 'name-field'])
```

---

## Field Types

### TextInput

```php
use NyonCode\WireForms\Components\TextInput;

TextInput::make('name')
TextInput::make('email')->email()
TextInput::make('password')->password()
TextInput::make('phone')->tel()
TextInput::make('website')->url()
TextInput::make('quantity')->numeric()
TextInput::make('age')->integer()

// Constraints
TextInput::make('code')
    ->type('text')
    ->maxLength(10)
    ->minLength(3)
    ->pattern('[A-Z0-9]+')
    ->autocomplete('off')
    ->step('1')
    ->min('0')
    ->max('100')

// Add-ons
TextInput::make('price')
    ->prefix('CZK')
    ->suffix('.00')
    ->prefixIcon('currency')
```

### Textarea

```php
use NyonCode\WireForms\Components\Textarea;

Textarea::make('description')
    ->rows(5)
    ->cols(40)
    ->maxLength(1000)
    ->autosize()                       // Auto-resize based on content
```

### Select

```php
use NyonCode\WireForms\Components\Select;

Select::make('role')
    ->options([
        'admin' => 'Administrator',
        'editor' => 'Editor',
        'user' => 'User',
    ])
    ->searchable()                     // Enable search in dropdown
    ->multiple()                       // Multi-select
    ->native(false)                    // Custom styled dropdown

// Dynamic options from database
Select::make('category_id')
    ->options(fn () => Category::pluck('name', 'id')->toArray())
    ->placeholder('Choose category')

// Relationship support
Select::make('user_id')
    ->relationship('user', 'name')
    ->searchable()
```

### Checkbox

```php
use NyonCode\WireForms\Components\Checkbox;

Checkbox::make('agree_terms')
    ->label('I agree to the terms')
    ->default(false)
```

### CheckboxList

```php
use NyonCode\WireForms\Components\CheckboxList;

CheckboxList::make('permissions')
    ->options([
        'create' => 'Create',
        'read' => 'Read',
        'update' => 'Update',
        'delete' => 'Delete',
    ])
    ->columns(2)                       // Display in 2 columns
    ->searchable()                     // Enable search
    ->bulkToggleable()                 // Select/deselect all
    ->grouped()                        // Group by category
```

### Radio

```php
use NyonCode\WireForms\Components\Radio;

Radio::make('priority')
    ->options([
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ])
    ->inline()                         // Horizontal layout
    ->descriptions([
        'low' => 'No rush',
        'medium' => 'Standard priority',
        'high' => 'Urgent attention needed',
    ])
```

### Toggle

```php
use NyonCode\WireForms\Components\Toggle;

Toggle::make('is_active')
    ->onLabel('Active')
    ->offLabel('Inactive')
    ->onColor('success')
    ->offColor('danger')
    ->onIcon('check')
    ->offIcon('x')
```

### DateTimePicker

Unified date/time picker with `mode()` selector. Default mode is `datetime`.

```php
use NyonCode\WireForms\Components\DateTimePicker;

// Date only
DateTimePicker::make('birth_date')
    ->asDate()                         // alias for mode('date')
    ->minDate('1900-01-01')
    ->maxDate(now()->format('Y-m-d'))
    ->format('Y-m-d')                 // Storage format
    ->displayFormat('d.m.Y')          // Display format
    ->firstDayOfWeek(1)               // Monday

// DateTime (default mode)
DateTimePicker::make('starts_at')
    ->minDate('2024-01-01')
    ->maxDate('2025-12-31')
    ->withoutSeconds()
    ->timezone('Europe/Prague')

// Time only
DateTimePicker::make('start_time')
    ->asTime()                         // alias for mode('time')
    ->withoutSeconds()
    ->hoursStep(1)
    ->minutesStep(15)

// Explicit mode setter
DateTimePicker::make('event_at')->mode('datetime')   // default
DateTimePicker::make('due_date')->mode('date')
DateTimePicker::make('alarm')->mode('time')
```

### ColorPicker

```php
use NyonCode\WireForms\Components\ColorPicker;

ColorPicker::make('brand_color')
    ->hex()                            // #RRGGBB format
    ->hsl()                            // HSL format
    ->rgb()                            // RGB format
    ->rgba()                           // RGBA format
```

### FileUpload

```php
use NyonCode\WireForms\Components\FileUpload;

FileUpload::make('attachment')
    ->disk('public')
    ->directory('attachments')
    ->acceptedFileTypes(['application/pdf', 'image/*'])
    ->maxSize(10240)                   // KB
    ->multiple()
    ->maxFiles(5)
    ->image()                          // Image-only mode
    ->imageResizeMode('cover')
    ->imageResizeTargetWidth(1920)
    ->imageResizeTargetHeight(1080)
    ->imageCropAspectRatio('16:9')
```

### RichEditor

```php
use NyonCode\WireForms\Components\RichEditor;

RichEditor::make('content')
    ->toolbarButtons([
        'bold', 'italic', 'underline',
        'h2', 'h3',
        'bulletList', 'orderedList',
        'link', 'blockquote',
    ])
    ->disableToolbarButtons(['codeBlock'])
    ->fileAttachmentsDirectory('content-images')
```

### Hidden

```php
use NyonCode\WireForms\Components\Hidden;

Hidden::make('user_id')
    ->default(fn () => auth()->id())
```

---

## Layout Fields

Layout fields organize other fields visually without holding data.

### Section

```php
use NyonCode\WireForms\Components\Layout\Section;

Section::make('personal_info')
    ->label('Personal Information')
    ->description('Basic details about the user.')
    ->icon('user')
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email()->required(),
    ])
    ->collapsible()
    ->collapsed()                      // Start collapsed
    ->columns(2)                       // 2-column grid inside
```

### Fieldset

```php
use NyonCode\WireForms\Components\Layout\Fieldset;

Fieldset::make('address')
    ->label('Address')
    ->schema([
        TextInput::make('street'),
        TextInput::make('city'),
        TextInput::make('zip'),
    ])
    ->columns(3)
```

### Grid

```php
use NyonCode\WireForms\Components\Layout\Grid;

Grid::make('layout')
    ->schema([
        TextInput::make('first_name')->columnSpan(1),
        TextInput::make('last_name')->columnSpan(1),
        Textarea::make('bio')->columnSpanFull(),
    ])
    ->columns(2)
```

---

## Display Fields

Fields that display content without user input.

### Placeholder

```php
use NyonCode\WireForms\Components\Display\Placeholder;

Placeholder::make('notice')
    ->content('This action will send an email to the user.')
```

### Alert

```php
use NyonCode\WireForms\Components\Display\Alert;

Alert::make('warning')
    ->label('Warning')
    ->type('warning')                  // info, success, warning, danger
    ->message('This action cannot be undone.')
    ->icon('exclamation')
```

### Html

```php
use NyonCode\WireForms\Components\Display\Html;

Html::make('custom')
    ->content('<div class="text-red-500">Custom HTML content</div>')

// Static helpers
Html::divider()                        // Horizontal rule
Html::spacer()                         // Empty space
Html::heading('Section Title')         // H3 heading
Html::paragraph('Description text')    // Paragraph
```

### ViewField

```php
use NyonCode\WireForms\Components\Display\ViewField;

ViewField::make('preview')
    ->view('components.preview')
    ->viewData(['key' => 'value'])
```

---

## Using Fields in Actions

```php
Action::make('edit')
    ->form([
        Section::make('info')
            ->label('User Info')
            ->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required(),
            ])
            ->columns(2),

        Select::make('role')
            ->options(['admin' => 'Admin', 'user' => 'User'])
            ->required(),

        Toggle::make('is_active')
            ->default(true),
    ])
    ->fillFormUsing(fn (Model $record) => $record->only(['name', 'email', 'role', 'is_active']))
    ->formValidation([
        'name' => 'required|string|max:255',
        'email' => 'required|email',
        'role' => 'required|in:admin,user',
    ])
    ->action(fn (Model $record, array $data) => $record->update($data))
```
