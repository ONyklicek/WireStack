<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Component;
use NyonCode\WireForms\Components\Checkbox;
use NyonCode\WireForms\Components\CheckboxList;
use NyonCode\WireForms\Components\ColorPicker;
use NyonCode\WireForms\Components\DateTimePicker;
use NyonCode\WireForms\Components\Field;
use NyonCode\WireForms\Components\FileUpload;
use NyonCode\WireForms\Components\KeyValue;
use NyonCode\WireForms\Components\OtpInput;
use NyonCode\WireForms\Components\Radio;
use NyonCode\WireForms\Components\Rating;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\Slider;
use NyonCode\WireForms\Components\Tags;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class FieldPreview extends Component
{
    use WithForms;

    public string $field = 'text-input';

    public array $data = [];

    public function mount(string $variant = 'text-input'): void
    {
        $this->field = $variant;
        $this->data = [
            'name' => 'Amelia Stone',
            'bio' => 'Owns product configuration, release notes, and customer rollouts across the workspace.',
            'role' => 'admin',
            'agree' => true,
            'permissions' => ['view', 'edit'],
            'plan' => 'pro',
            'notifications' => true,
            'brand_color' => '#f59e0b',
            'volume' => 65,
            'skills' => ['PHP', 'Laravel', 'Livewire'],
            'score' => 4,
            'code' => '283041',
            'metadata' => ['plan' => 'pro', 'seats' => '12', 'region' => 'eu-central'],
            'event_at' => '2026-06-15 14:30',
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([$this->fieldFor($this->field)]);
    }

    public function render()
    {
        return view('livewire.previews.field-preview');
    }

    protected function fieldFor(string $field): Field
    {
        return match ($field) {
            'textarea' => Textarea::make('bio')
                ->label('Internal note')
                ->helperText('Free-form notes shown to teammates.')
                ->rows(4),

            'select' => Select::make('role')
                ->label('Workspace role')
                ->helperText('Controls what this member can access.')
                ->options([
                    'admin' => 'Administrator',
                    'manager' => 'Manager',
                    'editor' => 'Editor',
                    'viewer' => 'Viewer',
                ]),

            'checkbox' => Checkbox::make('agree')
                ->label('I agree to the terms of service')
                ->helperText('Required before the workspace can be created.'),

            'checkbox-list' => CheckboxList::make('permissions')
                ->label('Permissions')
                ->helperText('Pick everything this role is allowed to do.')
                ->options([
                    'view' => 'View records',
                    'create' => 'Create records',
                    'edit' => 'Edit records',
                    'delete' => 'Delete records',
                ])
                ->columns(2),

            'radio' => Radio::make('plan')
                ->label('Billing plan')
                ->helperText('Switch plans at any time.')
                ->options([
                    'free' => 'Free',
                    'pro' => 'Pro',
                    'team' => 'Team',
                ]),

            'toggle' => Toggle::make('notifications')
                ->label('Email notifications')
                ->helperText('Send a summary whenever a record changes.')
                ->onLabel('On')
                ->offLabel('Off'),

            'color-picker' => ColorPicker::make('brand_color')
                ->label('Brand color')
                ->helperText('Used across buttons, links, and highlights.')
                ->swatches(['#ef4444', '#f97316', '#f59e0b', '#22c55e', '#3b82f6', '#a855f7']),

            'slider' => Slider::make('volume')
                ->label('Master volume')
                ->helperText('Drag to adjust the level.')
                ->min(0)
                ->max(100)
                ->step(1)
                ->suffix('%')
                ->showValue(),

            'tags' => Tags::make('skills')
                ->label('Skills')
                ->helperText('Type and press Enter to add a tag.')
                ->suggestions(['PHP', 'Laravel', 'Vue', 'React', 'TypeScript']),

            'rating' => Rating::make('score')
                ->label('Satisfaction')
                ->helperText('How happy are you with the result?')
                ->max(5),

            'otp-input' => OtpInput::make('code')
                ->label('Verification code')
                ->helperText('Enter the 6-digit code we sent you.')
                ->length(6)
                ->separator(3),

            'key-value' => KeyValue::make('metadata')
                ->label('Metadata')
                ->helperText('Arbitrary key / value pairs stored as JSON.')
                ->keyLabel('Key')
                ->valueLabel('Value'),

            'date-time-picker' => DateTimePicker::make('event_at')
                ->label('Event start')
                ->helperText('Pick a date and time.')
                ->mode('datetime'),

            'file-upload' => FileUpload::make('photo')
                ->label('Cover image')
                ->helperText('PNG or JPG up to 5 MB.')
                ->image(),

            default => TextInput::make('name')
                ->label('Full name')
                ->helperText('Shown on the member profile.')
                ->placeholder('e.g. Amelia Stone'),
        };
    }
}
