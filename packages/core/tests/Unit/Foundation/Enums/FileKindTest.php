<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Enums\FileKind;

it('reads the family from the mime type', function (string $mime, FileKind $expected) {
    expect(FileKind::for($mime))->toBe($expected);
})->with([
    'a photograph' => ['image/jpeg', FileKind::Image],
    'a vector' => ['image/svg+xml', FileKind::Image],
    'a video' => ['video/mp4', FileKind::Video],
    'a recording' => ['audio/mpeg', FileKind::Audio],
    'a pdf' => ['application/pdf', FileKind::Document],
    'a word file' => ['application/msword', FileKind::Document],
    // Sixty characters of vendor namespace whose only distinguishing part is
    // the last word — matched on the fragment, or a .pptm gets missed.
    'an ooxml document' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', FileKind::Document],
    'an ooxml spreadsheet' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', FileKind::Spreadsheet],
    'an ooxml presentation' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', FileKind::Presentation],
    'an old excel file' => ['application/vnd.ms-excel', FileKind::Spreadsheet],
    'an opendocument sheet' => ['application/vnd.oasis.opendocument.spreadsheet', FileKind::Spreadsheet],
    'a csv' => ['text/csv', FileKind::Spreadsheet],
    'a zip' => ['application/zip', FileKind::Archive],
    'a rar' => ['application/x-rar-compressed', FileKind::Archive],
    'json' => ['application/json', FileKind::Code],
    'xml' => ['application/xml', FileKind::Code],
    'a page' => ['text/html', FileKind::Code],
]);

it('falls back to the name when the mime type is missing', function () {
    expect(FileKind::for(null, 'cenik-q1.xlsx'))->toBe(FileKind::Spreadsheet)
        ->and(FileKind::for('', 'spot-jaro.mp4'))->toBe(FileKind::Video)
        ->and(FileKind::for(null, 'katalog.pdf'))->toBe(FileKind::Document);
});

it('falls back to the name when the mime type means nothing', function () {
    // What most archives, and half of all uploads from a strict browser, arrive as.
    expect(FileKind::for('application/octet-stream', 'tiskoviny.zip'))->toBe(FileKind::Archive)
        // A spreadsheet to every person who opens it, whatever the server said.
        ->and(FileKind::for('text/plain', 'export.csv'))->toBe(FileKind::Spreadsheet);
});

it('keeps text as a document when the name says nothing either', function () {
    expect(FileKind::for('text/plain', 'poznamky'))->toBe(FileKind::Document);
});

it('answers Other for something it has never heard of', function () {
    expect(FileKind::for('application/x-nyoncode', 'thing.wat'))->toBe(FileKind::Other)
        ->and(FileKind::for(null, null))->toBe(FileKind::Other);
});

it('takes the wordmark from the file name, not from the family', function () {
    // Both are spreadsheets and they must not both read "XLSX".
    expect(FileKind::extensionOf('cenik.xlsx'))->toBe('XLSX')
        ->and(FileKind::extensionOf('cenik.ods'))->toBe('ODS')
        ->and(FileKind::for(null, 'cenik.ods'))->toBe(FileKind::Spreadsheet);
});

it('refuses to print a fragment of a sentence as an extension', function () {
    expect(FileKind::extensionOf('README'))->toBeNull()
        ->and(FileKind::extensionOf('report.final version'))->toBeNull()
        ->and(FileKind::extensionOf('trailing.'))->toBeNull()
        ->and(FileKind::extensionOf(null))->toBeNull()
        ->and(FileKind::extensionOf('   '))->toBeNull();
});

it('gives every family a colour from the shared palette and an icon', function () {
    foreach (FileKind::cases() as $kind) {
        expect($kind->color())->toBeInstanceOf(Color::class)
            ->and($kind->icon())->toStartWith('outline:')
            ->and($kind->label())->not->toBe('');
    }
});

it('answers the image question from the family, so the two cannot disagree', function () {
    expect(FileKind::for('image/png')->isImage())->toBeTrue()
        ->and(FileKind::for('application/pdf')->isImage())->toBeFalse();
});
