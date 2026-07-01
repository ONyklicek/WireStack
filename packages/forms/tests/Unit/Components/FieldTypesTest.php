<?php

declare(strict_types=1);

use NyonCode\WireForms\Components\Checkbox;
use NyonCode\WireForms\Components\CheckboxList;
use NyonCode\WireForms\Components\ColorPicker;
use NyonCode\WireForms\Components\FileUpload;
use NyonCode\WireForms\Components\Hidden;
use NyonCode\WireForms\Components\Radio;
use NyonCode\WireForms\Components\RichEditor;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Components\Toggle;

// ─── Hidden ────────────────────────────────────────────────────

test('hidden field is hidden by default', function () {
    $field = Hidden::make('token');

    expect($field->isHidden())->toBeTrue();
});

// ─── Textarea ──────────────────────────────────────────────────

test('textarea default rows is 3', function () {
    $field = Textarea::make('bio');

    expect($field->getRows())->toBe(3);
});

test('textarea custom rows and cols', function () {
    $field = Textarea::make('bio')->rows(6)->cols(80);

    expect($field->getRows())->toBe(6)
        ->and($field->getCols())->toBe(80);
});

test('textarea autosize', function () {
    $field = Textarea::make('bio')->autosize();

    expect($field->isAutosize())->toBeTrue();
});

test('textarea min and max length', function () {
    $field = Textarea::make('bio')->minLength(10)->maxLength(500);

    expect($field->getMinLength())->toBe(10)
        ->and($field->getMaxLength())->toBe(500);
});

// ─── Checkbox ──────────────────────────────────────────────────

test('checkbox with description', function () {
    $field = Checkbox::make('agree')->description('I agree to terms');

    expect($field->getDescription())->toBe('I agree to terms');
});

test('checkbox inline', function () {
    $field = Checkbox::make('agree')->inline();

    expect($field->isInline())->toBeTrue();
});

// ─── Toggle ────────────────────────────────────────────────────

test('toggle default colors', function () {
    $field = Toggle::make('active');

    expect($field->getOnColor())->toBe('primary')
        ->and($field->getOffColor())->toBe('gray')
        ->and($field->isInline())->toBeTrue();
});

test('toggle custom labels and colors', function () {
    $field = Toggle::make('active')
        ->onLabel('Yes')
        ->offLabel('No')
        ->onColor('success')
        ->offColor('danger');

    expect($field->getOnLabel())->toBe('Yes')
        ->and($field->getOffLabel())->toBe('No')
        ->and($field->getOnColor())->toBe('success')
        ->and($field->getOffColor())->toBe('danger');
});

test('toggle icons', function () {
    $field = Toggle::make('active')
        ->onIcon('check')
        ->offIcon('x');

    expect($field->getOnIcon())->toBe('check')
        ->and($field->getOffIcon())->toBe('x');
});

// ─── Radio ─────────────────────────────────────────────────────

test('radio options', function () {
    $field = Radio::make('role')->options(['admin' => 'Admin', 'user' => 'User']);

    expect($field->getOptions())->toBe(['admin' => 'Admin', 'user' => 'User']);
});

test('radio inline', function () {
    $field = Radio::make('role')->inline();

    expect($field->isInline())->toBeTrue();
});

test('radio descriptions', function () {
    $field = Radio::make('role')->descriptions(['admin' => 'Full access']);

    expect($field->getDescriptions())->toBe(['admin' => 'Full access']);
});

test('radio defaults to the default variant', function () {
    $field = Radio::make('role');

    expect($field->getVariant())->toBe('default')
        ->and($field->isCards())->toBeFalse()
        ->and($field->isButtons())->toBeFalse()
        ->and($field->hasIndicator())->toBeTrue();
});

test('radio cards variant', function () {
    $field = Radio::make('plan')->cards();

    expect($field->isCards())->toBeTrue()
        ->and($field->getVariant())->toBe('cards');
});

test('radio cards can be disabled back to default', function () {
    $field = Radio::make('plan')->cards()->cards(false);

    expect($field->isCards())->toBeFalse()
        ->and($field->getVariant())->toBe('default');
});

test('radio segmented variant', function () {
    $field = Radio::make('plan')->segmented();

    expect($field->isSegmented())->toBeTrue()
        ->and($field->isButtons())->toBeFalse()
        ->and($field->getVariant())->toBe('segmented');
});

test('radio segmented can be disabled back to default', function () {
    $field = Radio::make('plan')->segmented()->segmented(false);

    expect($field->isSegmented())->toBeFalse()
        ->and($field->getVariant())->toBe('default');
});

test('radio buttons variant', function () {
    $field = Radio::make('plan')->buttons();

    expect($field->isButtons())->toBeTrue()
        ->and($field->isSegmented())->toBeFalse()
        ->and($field->getVariant())->toBe('buttons');
});

test('radio buttons can be disabled back to default', function () {
    $field = Radio::make('plan')->buttons()->buttons(false);

    expect($field->isButtons())->toBeFalse()
        ->and($field->getVariant())->toBe('default');
});

test('radio icons', function () {
    $field = Radio::make('plan')->icons(['pro' => 'star', 'free' => 'gift']);

    expect($field->getIcons())->toBe(['pro' => 'star', 'free' => 'gift']);
});

test('radio icons accept a closure', function () {
    $field = Radio::make('plan')->icons(fn () => ['pro' => 'star']);

    expect($field->getIcons())->toBe(['pro' => 'star']);
});

test('radio hide indicator', function () {
    $field = Radio::make('plan')->cards()->hideIndicator();

    expect($field->hasIndicator())->toBeFalse();
});

test('radio indicator can be toggled back on', function () {
    $field = Radio::make('plan')->hideIndicator()->indicator();

    expect($field->hasIndicator())->toBeTrue();
});

// ─── CheckboxList ──────────────────────────────────────────────

test('checkbox list options and columns', function () {
    $field = CheckboxList::make('permissions')
        ->options(['read' => 'Read', 'write' => 'Write'])
        ->columns(3);

    expect($field->getOptions())->toHaveCount(2)
        ->and($field->getColumns())->toBe(3);
});

test('checkbox list searchable', function () {
    $field = CheckboxList::make('tags')->searchable();

    expect($field->isSearchable())->toBeTrue();
});

test('checkbox list bulk toggleable', function () {
    $field = CheckboxList::make('tags')->bulkToggleable();

    expect($field->isBulkToggleable())->toBeTrue();
});

test('checkbox list groups', function () {
    $groups = ['backend' => ['php', 'python'], 'frontend' => ['js', 'css']];
    $field = CheckboxList::make('skills')->groups($groups);

    expect($field->isGrouped())->toBeTrue()
        ->and($field->getGroups())->toBe($groups);
});

// ─── ColorPicker ───────────────────────────────────────────────

test('color picker default format is hex', function () {
    $field = ColorPicker::make('color');

    expect($field->getFormat())->toBe('hex');
});

test('color picker format variants', function () {
    expect(ColorPicker::make('c')->hsl()->getFormat())->toBe('hsl')
        ->and(ColorPicker::make('c')->rgb()->getFormat())->toBe('rgb')
        ->and(ColorPicker::make('c')->rgba()->getFormat())->toBe('rgba');
});

// ─── FileUpload ────────────────────────────────────���───────────

test('file upload defaults', function () {
    $field = FileUpload::make('avatar');

    expect($field->isMultiple())->toBeFalse()
        ->and($field->isImage())->toBeFalse()
        ->and($field->getVisibility())->toBe('public');
});

test('file upload image mode', function () {
    $field = FileUpload::make('photo')->image();

    expect($field->isImage())->toBeTrue()
        ->and($field->getAcceptedFileTypes())->toBe(['image/*']);
});

test('file upload avatar mode', function () {
    $field = FileUpload::make('avatar')->avatar();

    expect($field->isAvatar())->toBeTrue()
        ->and($field->isImage())->toBeTrue();
});

test('file upload constraints', function () {
    $field = FileUpload::make('doc')
        ->maxSize(2048)
        ->minSize(10)
        ->maxFiles(5)
        ->minFiles(1)
        ->multiple();

    expect($field->getMaxSize())->toBe(2048)
        ->and($field->getMinSize())->toBe(10)
        ->and($field->getMaxFiles())->toBe(5)
        ->and($field->getMinFiles())->toBe(1)
        ->and($field->isMultiple())->toBeTrue();
});

test('file upload accepted types', function () {
    $field = FileUpload::make('doc')
        ->acceptedFileTypes(['.pdf', '.doc', '.docx']);

    expect($field->getAcceptedFileTypes())->toBe(['.pdf', '.doc', '.docx']);
});

test('file upload disk and directory', function () {
    $field = FileUpload::make('file')
        ->disk('s3')
        ->directory('documents');

    expect($field->getDisk())->toBe('s3')
        ->and($field->getDirectory())->toBe('documents');
});

test('file upload image resize', function () {
    $field = FileUpload::make('photo')
        ->image()
        ->imageResizeTargetWidth(800)
        ->imageResizeTargetHeight(600)
        ->imageCropAspectRatio('4:3');

    expect($field->getImageResizeTargetWidth())->toBe(800)
        ->and($field->getImageResizeTargetHeight())->toBe(600)
        ->and($field->getImageCropAspectRatio())->toBe('4:3');
});

test('file upload preserve filenames', function () {
    $field = FileUpload::make('file')->preserveFilenames();

    expect($field->shouldPreserveFilenames())->toBeTrue();
});

// ─── RichEditor ────────────────────────────────────────��───────

test('rich editor max length', function () {
    $field = RichEditor::make('content')->maxLength(10000);

    expect($field->getMaxLength())->toBe(10000);
});

test('rich editor custom toolbar', function () {
    $field = RichEditor::make('content')->toolbarButtons(['bold', 'italic']);

    expect($field->getToolbarButtons())->toBe(['bold', 'italic']);
});

test('rich editor disable toolbar buttons', function () {
    $field = RichEditor::make('content')
        ->toolbarButtons(['bold', 'italic', 'underline'])
        ->disableToolbarButtons(['underline']);

    expect($field->getToolbarButtons())->toBe(['bold', 'italic']);
});

test('rich editor disable all toolbar buttons', function () {
    $field = RichEditor::make('content')->disableAllToolbarButtons();

    expect($field->getToolbarButtons())->toBe([]);
});

test('rich editor file attachments directory', function () {
    $field = RichEditor::make('content')->fileAttachmentsDirectory('attachments');

    expect($field->getFileAttachmentsDirectory())->toBe('attachments');
});
