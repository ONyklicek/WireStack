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
use NyonCode\WireForms\Components\TiptapEditor;
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
            'alignment' => 'center',
            'range' => 'week',
            'priority' => 'high',
            'size_sm' => 'b',
            'size_md' => 'b',
            'size_lg' => 'b',
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
            ->schema($this->schemaFor($this->field));
    }

    public function render()
    {
        return view('livewire.previews.field-preview');
    }

    /**
     * Schema for a preview key. Most previews render a single field; a few compose
     * several fields to showcase one axis (e.g. the size scale).
     *
     * @return array<int, Field>
     */
    protected function schemaFor(string $field): array
    {
        if ($field === 'radio-sizes') {
            return [
                Radio::make('size_sm')->label('Small')->options(['a' => 'Left', 'b' => 'Center', 'c' => 'Right'])
                    ->icons(['a' => 'bars-3-bottom-left', 'b' => 'bars-3', 'c' => 'bars-3-bottom-right'])
                    ->buttons()->inline()->sm(),
                Radio::make('size_md')->label('Medium (default)')->options(['a' => 'Left', 'b' => 'Center', 'c' => 'Right'])
                    ->icons(['a' => 'bars-3-bottom-left', 'b' => 'bars-3', 'c' => 'bars-3-bottom-right'])
                    ->buttons()->inline()->md(),
                Radio::make('size_lg')->label('Large')->options(['a' => 'Left', 'b' => 'Center', 'c' => 'Right'])
                    ->icons(['a' => 'bars-3-bottom-left', 'b' => 'bars-3', 'c' => 'bars-3-bottom-right'])
                    ->buttons()->inline()->lg(),
            ];
        }

        return [$this->fieldFor($field)];
    }

    protected function fieldFor(string $field): Field
    {
        return match ($field) {
            'textarea' => Textarea::make('bio')
                ->label('Internal note')
                ->helperText('Free-form notes shown to teammates.')
                ->rows(4),

            // Core editor: no opt-in extensions, so only the base ESM entry + the
            // shared core chunk load (the addon chunk is never injected).
            'tiptap' => TiptapEditor::make('bio')
                ->label('Rich text (core only)')
                ->helperText('Core editor — the tables/images addon chunk is not loaded.'),

            // Enables tables + images, so the field injects the opt-in addon entry
            // (which shares the same core chunk as the base).
            'tiptap-tables' => TiptapEditor::make('bio')
                ->label('Rich text with tables')
                ->withTables()
                ->withImages()
                ->helperText('Enables the opt-in extension addon chunk.'),

            'select' => Select::make('role')
                ->label('Workspace role')
                ->helperText('Controls what this member can access.')
                ->options([
                    'admin' => 'Administrator',
                    'manager' => 'Manager',
                    'editor' => 'Editor',
                    'viewer' => 'Viewer',
                ]),

            // Per-component breakpoint: sheet up to lg (1024) even when the global
            // default is sm — so it's a sheet on a tablet at ~900px.
            'select-bp-lg' => Select::make('role')
                ->label('Workspace role')
                ->helperText('Controls what this member can access.')
                ->mobileBreakpoint('lg')
                ->options([
                    'admin' => 'Administrator',
                    'manager' => 'Manager',
                    'editor' => 'Editor',
                    'viewer' => 'Viewer',
                ]),

            // Searchable selects default to the classic floating dropdown on
            // mobile (no explicit ->sheetOnMobile call needed).
            'select-floating' => Select::make('role')
                ->label('Workspace role')
                ->helperText('Controls what this member can access.')
                ->searchable()
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

            // Filament-style per-breakpoint columns: 1 on phones, 2 from md, 4 from xl.
            'checkbox-list-responsive' => CheckboxList::make('permissions')
                ->label('Permissions')
                ->helperText('Per-breakpoint columns: 1 / md:2 / xl:4.')
                ->options([
                    'view' => 'View records',
                    'create' => 'Create records',
                    'edit' => 'Edit records',
                    'delete' => 'Delete records',
                    'export' => 'Export records',
                    'import' => 'Import records',
                    'share' => 'Share records',
                    'archive' => 'Archive records',
                ])
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4]),

            'radio' => Radio::make('plan')
                ->label('Billing plan')
                ->helperText('Switch plans at any time.')
                ->options([
                    'free' => 'Free',
                    'pro' => 'Pro',
                    'team' => 'Team',
                ])
                ->descriptions([
                    'free' => 'For personal projects and quick trials.',
                    'pro' => 'For growing teams that need more room.',
                    'team' => 'Advanced controls, SSO, and audit logs.',
                ])
                ->icons([
                    'free' => 'gift',
                    'pro' => 'star',
                    'team' => 'user-group',
                ])
                ->cards(),

            'radio-color' => Radio::make('priority')
                ->label('Priority')
                ->helperText('Any accent color via ->color().')
                ->options([
                    'low' => 'Low',
                    'normal' => 'Normal',
                    'high' => 'High',
                ])
                ->icons([
                    'low' => 'arrow-down',
                    'normal' => 'minus',
                    'high' => 'arrow-up',
                ])
                ->cards()
                ->inline()
                ->color('danger'),

            'radio-segmented' => Radio::make('range')
                ->label('Date range')
                ->helperText('Pick the reporting window.')
                ->options([
                    'day' => 'Day',
                    'week' => 'Week',
                    'month' => 'Month',
                ])
                ->icons([
                    'day' => 'sun',
                    'week' => 'calendar-days',
                    'month' => 'calendar',
                ])
                ->segmented(),

            'radio-buttons' => Radio::make('alignment')
                ->label('Text alignment')
                ->helperText('Choose how the block is aligned.')
                ->options([
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right',
                ])
                ->icons([
                    'left' => 'bars-3-bottom-left',
                    'center' => 'bars-3',
                    'right' => 'bars-3-bottom-right',
                ])
                ->buttons()
                ->inline(),

            'toggle' => Toggle::make('notifications')
                ->label('Email notifications')
                ->helperText('Send a summary whenever a record changes.')
                ->onLabel('On')
                ->offLabel('Off'),

            'color-picker' => ColorPicker::make('brand_color')
                ->label('Brand color')
                ->helperText('Used across buttons, links, and highlights.')
                ->rgb()
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
                ->displayFormat('j. n. Y H:i')
                ->closeOnDateSelection()
                ->mode('datetime'),

            'file-upload' => FileUpload::make('photo')
                ->label('Cover image')
                ->helperText('PNG or JPG up to 5 MB.')
                ->image()
                ->imageCropAspectRatio('16:9')
                ->imageResizeTargetWidth(320)
                ->cropInteractively(),

            // The same processing WITHOUT the interactive frame: the ratio is a
            // ratio, cropped from the centre, and the file reaches the
            // wire:model input on its own. Kept as its own preview because that
            // path stopped being covered in a browser the moment the field
            // above opted into the frame.
            'file-upload-auto' => FileUpload::make('photo')
                ->label('Cover image (centre crop)')
                ->helperText('Cropped and resized without asking — no frame to place.')
                ->image()
                ->imageCropAspectRatio('16:9')
                ->imageResizeTargetWidth(320),

            default => TextInput::make('name')
                ->label('Full name')
                ->helperText('Shown on the member profile.')
                ->placeholder('e.g. Amelia Stone'),
        };
    }
}
