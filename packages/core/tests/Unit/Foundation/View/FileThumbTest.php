<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/*
 * One answer to "what does this file look like", for every surface that shows a
 * file.
 *
 * Before this, six places decided it themselves and all six decided the same
 * impoverished thing: a picture, or one grey document icon. A catalogue, a price
 * list, a contract and a print archive rendered as four identical rectangles.
 * See ADR 0033.
 */

it('shows the picture when there is one', function () {
    $html = Blade::render(
        '<x-wire::file-thumb name="hero.jpg" mime="image/jpeg" url="/storage/thumb.webp" alt="Podzim" />'
    );

    expect($html)->toContain('<img')
        ->toContain('src="/storage/thumb.webp"')
        ->toContain('alt="Podzim"')
        // A grid of a hundred files must not fetch a hundred of them eagerly.
        ->toContain('loading="lazy"')
        ->toContain('decoding="async"');
});

it('shows the extension over the family colour when there is no picture', function () {
    $html = Blade::render('<x-wire::file-thumb name="katalog-2026.pdf" mime="application/pdf" />');

    expect($html)->not->toContain('<img')
        ->toContain('PDF')
        // The document family's hue, resolved through the shared palette rather
        // than written here as a class.
        ->toContain('bg-blue-100');
});

it('never renders an image tag for a file with no pixels, even given a url', function () {
    // `previewUrl()` falls back to the original for anything without a
    // thumbnail, so a PDF arrives here carrying a perfectly good URL. Rendering
    // it through <img> is a broken-image icon claiming the file is damaged.
    $html = Blade::render(
        '<x-wire::file-thumb name="smlouva.docx" mime="application/msword" url="/storage/smlouva.docx" />'
    );

    expect($html)->not->toContain('<img')
        ->toContain('DOCX');
});

it('tells two members of one family apart by their own extensions', function () {
    $xlsx = Blade::render('<x-wire::file-thumb name="cenik.xlsx" />');
    $ods = Blade::render('<x-wire::file-thumb name="cenik.ods" />');

    expect($xlsx)->toContain('XLSX')->not->toContain('ODS')
        ->and($ods)->toContain('ODS')->not->toContain('XLSX')
        // Same family, so the same colour.
        ->and($xlsx)->toContain('bg-green-100')
        ->and($ods)->toContain('bg-green-100');
});

it('falls back to the family label for a name with no usable extension', function () {
    $html = Blade::render('<x-wire::file-thumb name="README" mime="text/plain" />');

    expect($html)->toContain('Document');
});

it('drops the icon at row size, where only the letters fit', function () {
    $row = Blade::render('<x-wire::file-thumb name="katalog.pdf" size="sm" />');
    $tile = Blade::render('<x-wire::file-thumb name="katalog.pdf" size="lg" />');

    expect($row)->not->toContain('<svg')->toContain('PDF')
        ->and($tile)->toContain('<svg')->toContain('PDF');
});

it('takes the box from the caller', function () {
    $html = Blade::render('<x-wire::file-thumb name="katalog.pdf" class="rounded-xl" />');

    expect($html)->toContain('rounded-xl')
        // and still fills whatever it was put in
        ->toContain('h-full')
        ->toContain('w-full');
});
